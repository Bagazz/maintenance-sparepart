<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Laporan bulanan Presdir versi Excel: ringkasan + lembar rincian untuk ditelusuri lebih lanjut. */
class LaporanBulananExcel
{
    private const BIRU = '004E92';
    private const RP = '#,##0';

    public function __construct(private AnalitikBiayaService $a)
    {
    }

    public function buat(array $d): Spreadsheet
    {
        $buku = new Spreadsheet();
        $buku->getProperties()->setCreator('Spare Part Maintenance · PT Detpak Indonesia')
            ->setTitle(__('Spare Part Monthly Report') . ' ' . $d['judul']);
        $buku->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $this->ringkasan($buku->getActiveSheet(), $d);

        $this->tabel($buku->createSheet(), __('12-month trend'), [__('Month'), __('Purchases'), __('Usage'), __('Purchases − usage'), __('Cumulative held')],
            collect($d['tren'])->map(fn ($t) => [$t['label'], $t['beli'], $t['pakai'], $t['beli'] - $t['pakai'], $t['tertahan']])->all(),
            [14, 18, 18, 18, 18], ['B', 'C', 'D', 'E'], true);

        $this->tabel($buku->createSheet(), __('Machines'), ['#', __('Machine / Job'), __('Transactions'), 'Qty', __('Cost')],
            $this->a->topJob($d['awal'], $d['akhir'], 50)->values()->map(fn ($m, $i) => [$i + 1, $m->job, (int) $m->frek, (int) $m->qty, (float) $m->rp])->all(),
            [5, 34, 13, 10, 18], ['E'], true, [2]);

        $this->tabel($buku->createSheet(), __('Purchases'), [__('Date'), __('Part No'), __('Description'), __('Supplier'), 'Qty', __('Value'), __('Stock at purchase'), __('Used since purchase'), __('Assessment')],
            $d['kualitas']['baris']->map(fn ($b) => [
                $b->tgl->format('Y-m-d'), $b->part_no, $b->deskripsi, $b->supplier, $b->qty, $b->rp,
                $b->stok_saat_beli ?? '?', $b->pakai_sejak, __(AnalitikBiayaService::STATUS_BELI[$b->status][0]),
            ])->values()->all(),
            [12, 15, 44, 28, 8, 16, 12, 12, 16], ['F'], true, [2]);

        $this->tabel($buku->createSheet(), __('Restock'), [__('Part No'), __('Description'), __('Bin'), __('Stock'), __('Used 90 days'), __('Suggested buy'), __('Last price (Rp)'), __('Estimated Rp')],
            $this->a->queryPerluRestock()->orderByRaw('inventories.stock / p.pakai_90')->orderByDesc('p.pakai_90')->get()
                ->map(fn ($b) => [$b->part_no, $b->deskripsi, $b->lokasi_rak, (int) $b->stock, (int) $b->pakai_90, (int) $b->saran_beli, (float) $b->harga_beli_terakhir, (float) $b->saran_rp])->all(),
            [15, 44, 12, 9, 12, 12, 16, 16], ['G', 'H'], true, [1]);

        $m = $d['mengendap'];
        $mengendap = $m['dead_top']->map(fn ($x) => [__('Dead stock'), $x->part_no, $x->deskripsi, (int) $x->stock, (float) $x->rp])
            ->concat($m['over_top']->map(fn ($x) => ['Overstock', $x->part_no, $x->deskripsi, (int) $x->stock, (float) $x->rp]))->all();
        $this->tabel($buku->createSheet(), __('Idle money'), [__('Type'), __('Part No'), __('Description'), __('Stock'), __('Value')],
            $mengendap, [14, 15, 48, 9, 16], ['E'], false, [2]);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    private function ringkasan(Worksheet $s, array $d): void
    {
        $s->setTitle(__('Summary'));
        foreach (['A' => 38, 'B' => 20, 'C' => 20, 'D' => 20] as $k => $w) {
            $s->getColumnDimension($k)->setWidth($w);
        }

        $s->setCellValue('A1', 'PT DETPAK INDONESIA · MAINTENANCE PAPER DIVISION');
        $s->setCellValue('A2', __('Spare Part Monthly Report') . ' — ' . $d['judul'] . ($d['sebagian'] ? ' (' . __('to date') . ')' : ''));
        $s->setCellValue('A3', __('Period :dari – :sampai · compared with :lalu and :fy', [
            'dari' => $d['awal']->translatedFormat('d M Y'), 'sampai' => $d['akhir']->translatedFormat('d M Y'),
            'lalu' => $d['label_lalu'], 'fy' => $d['label_fy'],
        ]));
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('64748B');
        $s->getStyle('A2')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::BIRU);
        $s->getStyle('A3')->getFont()->getColor()->setRGB('64748B');

        $s->setCellValue('A5', __('Key points this month'));
        $s->getStyle('A5')->getFont()->setBold(true);
        $r = 6;
        foreach ($d['sorotan'] as $poin) {
            $s->setCellValue("A{$r}", '• ' . $poin)->mergeCells("A{$r}:D{$r}");
            $s->getStyle("A{$r}")->getAlignment()->setWrapText(true);
            $s->getRowDimension($r)->setRowHeight(28);
            $r++;
        }

        $r++;
        $hdr = $r;
        $s->fromArray(['', $d['judul'], $d['label_lalu'], __(':fy to date', ['fy' => $d['label_fy']])], null, "A{$hdr}");
        $this->gayaHeader($s, "A{$hdr}:D{$hdr}");
        $baris = [
            [__('Purchases'), $d['ini']['beli'], $d['lalu']['beli'], $d['fy']['beli']],
            [__('Usage'), $d['ini']['pakai'], $d['lalu']['pakai'], $d['fy']['pakai']],
            [__('Money held (purchases − usage)'), '=B' . ($hdr + 1) . '-B' . ($hdr + 2), '=C' . ($hdr + 1) . '-C' . ($hdr + 2), '=D' . ($hdr + 1) . '-D' . ($hdr + 2)],
            [__('Purchases to review'), $d['ini']['berisiko'], null, null],
            [__('Issued without kiosk scan'), $d['kerugian']['erp_tanpa_scan_rp'], null, null],
            [__('Missing from stock counts'), $d['kerugian']['opname']['kurang_rp'], null, null],
            [__('Restock needed now (estimated)'), $d['restock']['rp'], null, null],
            [__('Dead stock / not moving'), $d['mengendap']['dead_rp'], null, null],
            ['Overstock', $d['mengendap']['over_rp'], null, null],
        ];
        $s->fromArray($baris, null, 'A' . ($hdr + 1), true);
        $akhir = $hdr + count($baris);
        $s->getStyle('B' . ($hdr + 1) . ":D{$akhir}")->getNumberFormat()->setFormatCode(self::RP);
        $s->getStyle('A' . ($hdr + 3) . ':D' . ($hdr + 3))->getFont()->setBold(true);
        $s->getStyle("A{$hdr}:D{$akhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');

        if (!$d['cakupan']['cukup_tahun']) {
            $n = $akhir + 2;
            $s->setCellValue("A{$n}", '* ' . __('Only :n days of ERP data', ['n' => $d['cakupan']['hari']]) . ' — ' . __('some figures are still provisional.'))->mergeCells("A{$n}:D{$n}");
            $s->getStyle("A{$n}")->getFont()->setItalic(true)->getColor()->setRGB('B45309');
        }

        $s->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    }

    /** Satu lembar tabel: header biru, format Rupiah, filter, header dibekukan, baris total opsional. */
    private function tabel(Worksheet $s, string $judul, array $header, array $baris, array $lebar, array $kolomRp, bool $total, array $kolomTeks = []): void
    {
        $s->setTitle(mb_substr($judul, 0, 31));
        $s->fromArray($header, null, 'A1');
        $kolomAkhir = chr(ord('A') + count($header) - 1);
        $this->gayaHeader($s, "A1:{$kolomAkhir}1");
        foreach ($lebar as $i => $w) {
            $s->getColumnDimension(chr(ord('A') + $i))->setWidth($w);
        }

        $r = 1;
        foreach ($baris as $isi) {
            $r++;
            $s->fromArray($isi, null, "A{$r}", true);
            // Part no / nama mesin sebagai teks supaya angka panjang tidak berubah format.
            foreach ($kolomTeks as $k) {
                $sel = chr(ord('A') + $k - 1) . $r;
                $s->setCellValueExplicit($sel, (string) $isi[$k - 1], DataType::TYPE_STRING);
            }
        }
        $akhir = max(2, $r);
        foreach ($kolomRp as $k) {
            $s->getStyle("{$k}2:{$k}" . ($akhir + 1))->getNumberFormat()->setFormatCode(self::RP);
        }

        if ($total && $r > 1) {
            $t = $r + 1;
            $s->setCellValue("A{$t}", __('Total'));
            foreach ($kolomRp as $k) {
                $s->setCellValue("{$k}{$t}", "=SUM({$k}2:{$k}{$r})");
            }
            $s->getStyle("A{$t}:{$kolomAkhir}{$t}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0F8']],
            ]);
        }
        if ($r === 1) {
            $s->setCellValue('A2', __('No data.'));
        }

        $s->freezePane('A2');
        $s->setAutoFilter("A1:{$kolomAkhir}{$akhir}");
        $s->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    }

    private function gayaHeader(Worksheet $s, string $rentang): void
    {
        $s->getStyle($rentang)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BIRU]],
        ]);
    }
}
