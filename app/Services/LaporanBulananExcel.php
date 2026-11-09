<?php

namespace App\Services;

use App\Support\Ekspor;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Laporan bulanan Presdir versi Excel (ringkasan + lembar rincian) dan CSV (satu file per tabel, dalam ZIP).
 * Isi tabel didefinisikan sekali di tabelRincian() sehingga Excel dan CSV selalu sama.
 */
class LaporanBulananExcel
{
    public function __construct(private AnalitikBiayaService $a)
    {
    }

    public function buat(array $d): Spreadsheet
    {
        $judulDok = __('Spare Part Monthly Report') . ' — ' . $d['judul'];
        $buku = Ekspor::bukuBaru($judulDok);

        $this->ringkasan($buku->getActiveSheet(), $d, $judulDok);

        foreach ($this->tabelRincian($d) as $t) {
            $this->tabel($buku->createSheet(), $t, $d, $judulDok);
        }

        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    /** Semua tabel untuk CSV: [nama => [header, baris]]. Nilai mentah (tanpa format) supaya bisa dihitung ulang. */
    public function tabelCsv(array $d): array
    {
        $hasil = [
            __('Summary')          => [[__('Metric'), $d['judul'], $d['label_lalu'], __(':fy to date', ['fy' => $d['label_fy']])], $this->barisRingkasan($d, false)],
            __('Key points this month') => [[__('Key point')], array_map(fn ($p) => [$p], $d['sorotan'])],
        ];
        foreach ($this->tabelRincian($d) as $t) {
            $hasil[$t['judul']] = [$t['header'], $t['baris']];
        }

        return $hasil;
    }

    /** Definisi lembar rincian: judul, header, baris, lebar kolom, kolom Rupiah, baris total, kolom teks (1-based). */
    private function tabelRincian(array $d): array
    {
        $m = $d['mengendap'];

        return [
            [
                'judul' => __('12-month trend'),
                'header' => [__('Month'), __('Purchases'), __('Usage'), __('Purchases − usage'), __('Cumulative held')],
                'baris' => collect($d['tren'])->map(fn ($t) => [$t['label'], (float) $t['beli'], (float) $t['pakai'], (float) ($t['beli'] - $t['pakai']), (float) $t['tertahan']])->all(),
                'lebar' => [14, 18, 18, 18, 18], 'rp' => ['B', 'C', 'D', 'E'], 'total' => ['B', 'C', 'D'], 'teks' => [],
            ],
            [
                'judul' => __('Machines'),
                'header' => ['#', __('Machine / Job'), __('Transactions'), 'Qty', __('Cost')],
                'baris' => $this->a->topJob($d['awal'], $d['akhir'], 50)->values()->map(fn ($m, $i) => [$i + 1, $m->job, (int) $m->frek, (int) $m->qty, (float) $m->rp])->all(),
                'lebar' => [5, 40, 13, 10, 18], 'rp' => ['E'], 'total' => ['C', 'D', 'E'], 'teks' => [2],
            ],
            [
                'judul' => __('Purchases'),
                'header' => [__('Date'), __('Part No'), __('Description'), __('Supplier'), 'Qty', __('Value'), __('Stock at purchase'), __('Used since purchase'), __('Assessment')],
                'baris' => $d['kualitas']['baris']->map(fn ($b) => [
                    $b->tgl->format('Y-m-d'), (string) $b->part_no, $b->deskripsi, $b->supplier, (int) $b->qty, (float) $b->rp,
                    $b->stok_saat_beli ?? '?', (int) $b->pakai_sejak, __(AnalitikBiayaService::STATUS_BELI[$b->status][0]),
                ])->values()->all(),
                'lebar' => [13, 15, 44, 28, 8, 16, 12, 12, 18], 'rp' => ['F'], 'total' => ['E', 'F'], 'teks' => [2], 'tanggal' => ['A'],
            ],
            [
                'judul' => __('Restock'),
                'header' => [__('Part No'), __('Description'), __('Bin'), __('Stock'), __('Used 90 days'), __('Suggested buy'), __('Last price (Rp)'), __('Estimated Rp')],
                'baris' => $this->a->queryPerluRestock()->orderByRaw('inventories.stock / p.pakai_90')->orderByDesc('p.pakai_90')->get()
                    ->map(fn ($b) => [(string) $b->part_no, $b->deskripsi, $b->lokasi_rak, (int) $b->stock, (int) $b->pakai_90, (int) $b->saran_beli, (float) $b->harga_beli_terakhir, (float) $b->saran_rp])->all(),
                'lebar' => [15, 44, 13, 9, 12, 12, 16, 16], 'rp' => ['G', 'H'], 'total' => ['F', 'H'], 'teks' => [1],
            ],
            [
                'judul' => __('Idle money'),
                'header' => [__('Type'), __('Part No'), __('Description'), __('Stock'), __('Value')],
                'baris' => $m['dead_top']->map(fn ($x) => [__('Dead stock'), (string) $x->part_no, $x->deskripsi, (int) $x->stock, (float) $x->rp])
                    ->concat($m['over_top']->map(fn ($x) => ['Overstock', (string) $x->part_no, $x->deskripsi, (int) $x->stock, (float) $x->rp]))->values()->all(),
                'lebar' => [14, 15, 48, 9, 16], 'rp' => ['E'], 'total' => ['E'], 'teks' => [2],
            ],
        ];
    }

    /** Baris ringkasan; $rumus = true untuk Excel (baris "uang tertahan" memakai rumus). */
    private function barisRingkasan(array $d, bool $rumus, int $barisPertama = 0): array
    {
        $tertahan = $rumus
            ? ['=B' . $barisPertama . '-B' . ($barisPertama + 1), '=C' . $barisPertama . '-C' . ($barisPertama + 1), '=D' . $barisPertama . '-D' . ($barisPertama + 1)]
            : [(float) $d['ini']['beli'] - $d['ini']['pakai'], (float) $d['lalu']['beli'] - $d['lalu']['pakai'], (float) $d['fy']['beli'] - $d['fy']['pakai']];

        return [
            [__('Purchases'), (float) $d['ini']['beli'], (float) $d['lalu']['beli'], (float) $d['fy']['beli']],
            [__('Usage'), (float) $d['ini']['pakai'], (float) $d['lalu']['pakai'], (float) $d['fy']['pakai']],
            [__('Money held (purchases − usage)'), ...$tertahan],
            [__('Purchases to review'), (float) $d['ini']['berisiko'], null, null],
            [__('Issued without kiosk scan'), (float) $d['kerugian']['erp_tanpa_scan_rp'], null, null],
            [__('Missing from stock counts'), (float) $d['kerugian']['opname']['kurang_rp'], null, null],
            [__('Restock needed now (estimated)'), (float) $d['restock']['rp'], null, null],
            [__('Dead stock / not moving'), (float) $d['mengendap']['dead_rp'], null, null],
            ['Overstock', (float) $d['mengendap']['over_rp'], null, null],
        ];
    }

    private function keteranganKop(array $d): array
    {
        return [
            __('Period :dari – :sampai · compared with :lalu and :fy', [
                'dari' => $d['awal']->translatedFormat('d M Y'), 'sampai' => $d['akhir']->translatedFormat('d M Y'),
                'lalu' => $d['label_lalu'], 'fy' => $d['label_fy'],
            ]) . ($d['sebagian'] ? ' · ' . __('to date') : ''),
            __('Generated :tgl', ['tgl' => now()->translatedFormat('d M Y H:i')]) . ' · ' . __('All amounts in Rupiah (Rp)'),
        ];
    }

    private function ringkasan(Worksheet $s, array $d, string $judulDok): void
    {
        $s->setTitle(mb_substr(__('Summary'), 0, 31));
        foreach (['A' => 40, 'B' => 20, 'C' => 20, 'D' => 20] as $k => $w) {
            $s->getColumnDimension($k)->setWidth($w);
        }
        $s->setShowGridlines(false);

        $r = Ekspor::kopExcel($s, $judulDok, $this->keteranganKop($d), 'D');

        $s->setCellValue("A{$r}", __('Key points this month'));
        $s->getStyle("A{$r}")->getFont()->setBold(true)->getColor()->setRGB(Ekspor::BIRU);
        $r++;
        foreach ($d['sorotan'] as $poin) {
            $s->setCellValue("A{$r}", '• ' . $poin)->mergeCells("A{$r}:D{$r}");
            $s->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            // Sel gabungan tidak menyesuaikan tinggi sendiri: tinggi = jumlah baris teks (±95 huruf per baris di A:D).
            $s->getRowDimension($r)->setRowHeight(max(1, (int) ceil(mb_strlen('• ' . $poin) / 95)) * 13.5 + 3);
            $r++;
        }

        $r++;
        $hdr = $r;
        $s->fromArray([__('Metric'), $d['judul'], $d['label_lalu'], __(':fy to date', ['fy' => $d['label_fy']])], null, "A{$hdr}");
        Ekspor::gayaHeader($s, "A{$hdr}:D{$hdr}");
        $s->getStyle("B{$hdr}:D{$hdr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $baris = $this->barisRingkasan($d, true, $hdr + 1);
        $s->fromArray($baris, null, 'A' . ($hdr + 1), true);
        $akhir = $hdr + count($baris);
        $s->getStyle('B' . ($hdr + 1) . ":D{$akhir}")->getNumberFormat()->setFormatCode(Ekspor::FORMAT_RP);
        $s->getStyle('A' . ($hdr + 3) . ':D' . ($hdr + 3))->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF4FB']],
        ]);
        Ekspor::garisTabel($s, "A{$hdr}:D{$akhir}");
        Ekspor::tabelExcel($s, "A{$hdr}:D{$akhir}", 'TabelRingkasan');

        if (!$d['cakupan']['cukup_tahun']) {
            $n = $akhir + 2;
            $s->setCellValue("A{$n}", '* ' . __('Only :n days of ERP data', ['n' => $d['cakupan']['hari']]) . ' — ' . __('some figures are still provisional.'))->mergeCells("A{$n}:D{$n}");
            $s->getStyle("A{$n}")->getFont()->setItalic(true)->getColor()->setRGB('B45309');
        }

        Ekspor::cetakExcel($s, $judulDok, false);
    }

    /** Satu lembar rincian: kop + logo, header biru, format Rupiah, filter, header dibekukan, baris total. */
    private function tabel(Worksheet $s, array $t, array $d, string $judulDok): void
    {
        $s->setTitle(mb_substr($t['judul'], 0, 31));
        $kolomAkhir = chr(ord('A') + count($t['header']) - 1);
        foreach ($t['lebar'] as $i => $w) {
            $s->getColumnDimension(chr(ord('A') + $i))->setWidth($w);
        }

        $hdr = Ekspor::kopExcel($s, $t['judul'] . ' — ' . $d['judul'], $this->keteranganKop($d), $kolomAkhir);
        $s->fromArray($t['header'], null, "A{$hdr}");
        Ekspor::gayaHeader($s, "A{$hdr}:{$kolomAkhir}{$hdr}");

        $r = $hdr;
        foreach ($t['baris'] as $isi) {
            $r++;
            $s->fromArray($isi, null, "A{$r}", true);
            // Part no / nama mesin sebagai teks supaya angka panjang tidak berubah format.
            foreach ($t['teks'] as $k) {
                $s->setCellValueExplicit(chr(ord('A') + $k - 1) . $r, (string) $isi[$k - 1], DataType::TYPE_STRING);
            }
            // Tanggal sebagai tanggal Excel (bisa diurutkan/difilter), tampil "02 Sep 2026".
            foreach ($t['tanggal'] ?? [] as $k) {
                $s->setCellValue("{$k}{$r}", \PhpOffice\PhpSpreadsheet\Shared\Date::stringToExcel($s->getCell("{$k}{$r}")->getValue()));
                $s->getStyle("{$k}{$r}")->getNumberFormat()->setFormatCode('dd mmm yyyy');
            }
        }
        $pertama = $hdr + 1;

        if ($r === $hdr) {
            $s->setCellValue("A{$pertama}", __('No data for this period.'))->mergeCells("A{$pertama}:{$kolomAkhir}{$pertama}");
            $s->getStyle("A{$pertama}")->getFont()->setItalic(true)->getColor()->setRGB(Ekspor::ABU);
            Ekspor::garisTabel($s, "A{$hdr}:{$kolomAkhir}{$pertama}");
        } else {
            foreach ($t['rp'] as $k) {
                $s->getStyle("{$k}{$pertama}:{$k}" . ($r + 1))->getNumberFormat()->setFormatCode(Ekspor::FORMAT_RP);
            }
            $t2 = $r + 1;
            $s->setCellValue("A{$t2}", __('Total'));
            foreach ($t['total'] as $k) {
                $s->setCellValue("{$k}{$t2}", "=SUM({$k}{$pertama}:{$k}{$r})");
                $s->getStyle("{$k}{$t2}")->getNumberFormat()->setFormatCode(Ekspor::FORMAT_RP);
            }
            $s->getStyle("A{$t2}:{$kolomAkhir}{$t2}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF4FB']],
            ]);
            Ekspor::garisTabel($s, "A{$hdr}:{$kolomAkhir}{$t2}");
            Ekspor::tabelExcel($s, "A{$hdr}:{$kolomAkhir}{$r}", 'Tabel' . Ekspor::slug($t['judul']));   // tabel otomatis: filter tiap kolom, baris berselang warna
            // Teks panjang (deskripsi, pemasok) turun baris di dalam kolomnya — tidak terpotong kolom sebelah.
            $s->getStyle("A{$pertama}:{$kolomAkhir}{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        }

        $s->freezePane('A' . ($hdr + 1));
        Ekspor::cetakExcel($s, $judulDok, true, $hdr);
    }
}
