<?php

namespace App\Services;

use App\Support\Ekspor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Purchase Request (Excel) dari daftar Perlu Restock.
 * Kolom "Qty order" bisa diubah di Excel; total baris & total keseluruhan memakai rumus sehingga ikut berubah.
 */
class PurchaseRequestExcel
{
    private const BIRU = '004E92';
    private const MERAH = 'E4002B';

    /** Pemasok & tanggal pembelian terakhir per part (dari sheet Receipt ERP). */
    public function pembelianTerakhir(Collection $idPart): Collection
    {
        if ($idPart->isEmpty()) {
            return collect();
        }

        $terakhir = DB::table('inventory_transactions')->where('type', 'IN')->whereIn('inventory_id', $idPart)
            ->selectRaw('MAX(id) as id')->groupBy('inventory_id');

        return DB::table('inventory_transactions as t')->joinSub($terakhir, 'x', 'x.id', '=', 't.id')
            ->get(['t.inventory_id', 't.keterangan', 't.tanggal_transaksi', 't.created_at'])
            ->mapWithKeys(fn ($r) => [$r->inventory_id => (object) [
                'supplier' => trim(str_replace('Purchasing dari:', '', (string) $r->keterangan)),
                'tanggal'  => $r->tanggal_transaksi ?? substr((string) $r->created_at, 0, 10),
            ]]);
    }

    /** Prioritas: habis = urgent, stok cukup < 30 hari = tinggi, selain itu normal. */
    public static function prioritas(object $b): array
    {
        $hari = $b->pakai_90 > 0 ? (int) floor($b->stock / ($b->pakai_90 / 90)) : null;

        return match (true) {
            $b->stock <= 0 => [__('Urgent'), self::MERAH, $hari],
            $hari < 30     => [__('High'), 'D97706', $hari],
            default        => [__('Normal'), '475569', $hari],
        };
    }

    /** Judul kolom (sama untuk Excel & CSV). */
    public function header(): array
    {
        return [__('No'), __('Part No'), __('Description'), __('Bin'), __('Stock'), __('Used 90 days'), __('Lasts (days)'), __('Priority'),
            __('Suggested qty'), __('Order qty'), __('Last price (Rp)'), __('Est. total (Rp)'), __('Last supplier'), __('Last purchase'), __('Notes')];
    }

    /** Baris data mentah (sama untuk Excel & CSV). Kolom terakhir = warna prioritas (khusus Excel, dibuang di CSV). */
    public function baris(Collection $barang): array
    {
        $pembelian = $this->pembelianTerakhir($barang->pluck('id'));

        return $barang->values()->map(function ($b, $i) use ($pembelian) {
            [$label, $warna, $hari] = self::prioritas($b);
            $beli = $pembelian[$b->id] ?? null;

            return [
                $i + 1, (string) $b->part_no, $b->deskripsi, $b->lokasi_rak, (int) $b->stock, (int) $b->pakai_90, $hari, $label,
                (int) $b->saran_beli, (int) $b->saran_beli, (float) $b->harga_beli_terakhir, (float) $b->saran_beli * (float) $b->harga_beli_terakhir,
                $beli->supplier ?? '-', $beli->tanggal ?? '-', '', '_warna' => $warna,
            ];
        })->all();
    }

    /** CSV: baris data tanpa kolom khusus Excel. */
    public function barisCsv(Collection $barang): array
    {
        return array_map(fn ($r) => array_values(array_diff_key($r, ['_warna' => 1])), $this->baris($barang));
    }

    public function buat(Collection $barang, array $info): Spreadsheet
    {
        $buku = Ekspor::bukuBaru($info['nomor']);
        $s = $buku->getActiveSheet();
        $s->setTitle('Purchase Request');
        $s->setShowGridlines(false);
        $kolom = [
            'A' => [__('No'), 5], 'B' => [__('Part No'), 16], 'C' => [__('Description'), 44], 'D' => [__('Bin'), 12],
            'E' => [__('Stock'), 8], 'F' => [__('Used 90 days'), 10], 'G' => [__('Lasts (days)'), 10], 'H' => [__('Priority'), 10],
            'I' => [__('Suggested qty'), 11], 'J' => [__('Order qty'), 11], 'K' => [__('Last price (Rp)'), 15], 'L' => [__('Est. total (Rp)'), 17],
            'M' => [__('Last supplier'), 28], 'N' => [__('Last purchase'), 13], 'O' => [__('Notes'), 24],
        ];
        foreach ($kolom as $k => [, $lebar]) {
            $s->getColumnDimension($k)->setWidth($lebar);
        }

        // ---------- Kop (logo Detpak) ----------
        $mulai = Ekspor::kopExcel($s, 'PURCHASE REQUEST — ' . mb_strtoupper(__('Maintenance Spare Parts')), [], 'O');

        $kop = [
            [__('PR No'), $info['nomor'], __('Department'), 'Maintenance — Paper Division'],
            [__('Date'), $info['tanggal'], __('Requested by'), $info['pemohon']],
            [__('Warehouse'), 'M03 — Maintenance Store', __('Basis'), $info['dasar']],
        ];
        foreach ($kop as $i => [$l1, $v1, $l2, $v2]) {
            $r = $mulai + $i;
            $s->setCellValue("A$r", $l1)->mergeCells("A$r:B$r");
            $s->setCellValueExplicit("C$r", $v1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $s->setCellValue("E$r", $l2)->mergeCells("E$r:G$r");
            $s->setCellValue("H$r", $v2)->mergeCells("H$r:O$r");
            $s->getStyle("A$r")->getFont()->setBold(true);
            $s->getStyle("E$r")->getFont()->setBold(true);
        }

        // ---------- Tabel ----------
        $hdr = $mulai + count($kop) + 1;
        foreach ($kolom as $k => [$judul]) {
            $s->setCellValue("{$k}{$hdr}", $judul);
        }
        $s->getStyle("A{$hdr}:O{$hdr}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BIRU]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $s->getRowDimension($hdr)->setRowHeight(30);

        $r = $hdr;
        foreach ($this->baris($barang) as $isi) {
            $r++;
            $warna = $isi['_warna'];
            $nilai = array_values(array_diff_key($isi, ['_warna' => 1]));
            $nilai[11] = "=J{$r}*K{$r}";   // total baris = rumus, ikut berubah bila Qty order diubah
            $nilai[14] = null;
            $s->fromArray($nilai, null, "A{$r}", true); // true: angka 0 (stok habis) tetap ditulis, bukan dikosongkan
            // Part no disimpan sebagai teks supaya nol di depan / angka panjang tidak berubah.
            $s->setCellValueExplicit("B{$r}", (string) $nilai[1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $s->getStyle("H{$r}")->getFont()->setBold(true)->getColor()->setRGB($warna);
            if ($nilai[4] <= 0) {
                $s->getStyle("E{$r}")->getFont()->setBold(true)->getColor()->setRGB(self::MERAH);
            }
        }
        $akhir = max($r, $hdr + 1);

        // Kolom yang boleh diisi pembelian/atasan diberi warna kuning muda.
        $s->getStyle("J" . ($hdr + 1) . ":J{$akhir}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF7D6']],
        ]);
        $s->getStyle("O" . ($hdr + 1) . ":O{$akhir}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7D6');
        $s->getStyle("E" . ($hdr + 1) . ":L{$akhir}")->getNumberFormat()->setFormatCode('#,##0');
        $s->getStyle("A" . ($hdr + 1) . ":A{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle("C" . ($hdr + 1) . ":C{$akhir}")->getAlignment()->setWrapText(true);
        $s->getStyle("A{$hdr}:O{$akhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        $s->getStyle("A" . ($hdr + 1) . ":O{$akhir}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // ---------- Total ----------
        $t = $akhir + 1;
        $s->setCellValue("A{$t}", __('TOTAL'))->mergeCells("A{$t}:I{$t}");
        $s->setCellValue("J{$t}", "=SUM(J" . ($hdr + 1) . ":J{$akhir})");
        $s->setCellValue("L{$t}", "=SUM(L" . ($hdr + 1) . ":L{$akhir})");
        $s->getStyle("A{$t}:O{$t}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0F8']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BIRU]]],
        ]);
        $s->getStyle("A{$t}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $s->getStyle("J{$t}:L{$t}")->getNumberFormat()->setFormatCode('#,##0');

        // ---------- Catatan & tanda tangan ----------
        $c = $t + 2;
        $s->setCellValue("A{$c}", __('Notes:'));
        $s->getStyle("A{$c}")->getFont()->setBold(true);
        foreach ([
            __('Order qty (yellow) can be changed; totals recalculate automatically.'),
            __('Priority: Urgent = out of stock, High = stock lasts < 30 days, Normal = stock below 3 months of usage.'),
            __('Prices are the latest ERP purchase prices and may differ from the current quotation.'),
        ] as $i => $teks) {
            $s->setCellValue('B' . ($c + $i), '• ' . $teks)->mergeCells('B' . ($c + $i) . ':O' . ($c + $i));
        }

        $tt = $c + 5;
        foreach ([['B', 'D', __('Requested by')], ['F', 'I', __('Checked by')], ['K', 'M', __('Approved by')]] as [$dari, $sampai, $judul]) {
            $s->setCellValue("{$dari}{$tt}", $judul)->mergeCells("{$dari}{$tt}:{$sampai}{$tt}");
            $s->mergeCells("{$dari}" . ($tt + 1) . ":{$sampai}" . ($tt + 4));
            $s->getStyle("{$dari}" . ($tt + 4) . ":{$sampai}" . ($tt + 4))->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
            $s->setCellValue("{$dari}" . ($tt + 5), __('Name / date'))->mergeCells("{$dari}" . ($tt + 5) . ":{$sampai}" . ($tt + 5));
            $s->getStyle("{$dari}{$tt}:{$sampai}" . ($tt + 5))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $s->getStyle("{$dari}{$tt}")->getFont()->setBold(true);
            $s->getStyle("{$dari}" . ($tt + 5))->getFont()->setSize(8)->getColor()->setRGB('64748B');
        }
        $s->setCellValue("B" . ($tt + 6), $info['pemohon'])->mergeCells("B" . ($tt + 6) . ":D" . ($tt + 6));
        $s->getStyle("B" . ($tt + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ---------- Tampilan & cetak ----------
        $s->freezePane('D' . ($hdr + 1));
        if ($akhir > $hdr && $barang->isNotEmpty()) {
            Ekspor::tabelExcel($s, "A{$hdr}:O{$akhir}", 'TabelPR');   // tabel otomatis: filter tiap kolom, baris berselang warna
        }
        Ekspor::cetakExcel($s, 'Purchase Request ' . $info['nomor'], true, $hdr);
        $s->setSelectedCell('J' . ($hdr + 1));

        return $buku;
    }
}
