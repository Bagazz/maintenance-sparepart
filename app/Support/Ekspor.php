<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gaya ekspor yang sama untuk semua laporan (Excel, CSV, PDF):
 * kop dengan logo Detpak, nama perusahaan, judul dokumen, pengaturan cetak A4 dan nomor halaman.
 *
 * Logo memakai JPEG (bukan PNG) supaya PDF tetap memuat logo walau ekstensi GD PHP tidak aktif.
 */
class Ekspor
{
    public const BIRU = '004E92';
    public const MERAH = 'E4002B';
    public const ABU = '64748B';
    public const GARIS = 'CBD5E1';
    public const PERUSAHAAN = 'PT DETPAK INDONESIA';
    public const DIVISI = 'Maintenance · Paper Division';
    public const FORMAT_RP = '#,##0';

    public static function logo(): string
    {
        return public_path('images/detpak-logo.jpg');
    }

    /** Buku Excel baru dengan properti dokumen & huruf standar. */
    public static function bukuBaru(string $judul): Spreadsheet
    {
        $buku = new Spreadsheet();
        $buku->getProperties()->setCreator(self::PERUSAHAAN . ' · Spare Part Maintenance')
            ->setCompany(self::PERUSAHAAN)->setTitle($judul);
        $buku->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        return $buku;
    }

    /**
     * Kop di atas lembar: logo (baris 1–2), nama perusahaan, judul, keterangan.
     * Mengembalikan nomor baris kosong pertama setelah kop (sudah diberi jarak satu baris).
     *
     * @param  string[]  $keterangan  baris-baris keterangan di bawah judul (periode, filter, dsb.)
     */
    public static function kopExcel(Worksheet $s, string $judul, array $keterangan, string $kolomAkhir): int
    {
        $logo = new Drawing();
        $logo->setName('Detpak')->setDescription('PT Detpak Indonesia')->setPath(self::logo())
            ->setHeight(36)->setCoordinates('A1')->setOffsetX(2)->setOffsetY(4)->setWorksheet($s);
        $s->getRowDimension(1)->setRowHeight(18);
        $s->getRowDimension(2)->setRowHeight(18);

        $s->setCellValue('A3', self::PERUSAHAAN . ' · ' . mb_strtoupper(self::DIVISI));
        $s->getStyle('A3')->getFont()->setBold(true)->setSize(8)->getColor()->setRGB(self::ABU);
        $s->setCellValue('A4', $judul);
        $s->getStyle('A4')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::BIRU);
        $s->getRowDimension(4)->setRowHeight(21);

        $r = 5;
        foreach (array_filter($keterangan, fn ($k) => $k !== null && $k !== '') as $baris) {
            $s->setCellValue("A{$r}", $baris);
            $s->getStyle("A{$r}")->getFont()->setSize(9)->getColor()->setRGB(self::ABU);
            $r++;
        }
        // Garis biru tipis di bawah kop.
        $s->getStyle("A{$r}:{$kolomAkhir}{$r}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::BIRU);

        return $r + 1;
    }

    /** Baris judul kolom tabel: latar biru, teks putih tebal, rata tengah, boleh turun baris. */
    public static function gayaHeader(Worksheet $s, string $rentang): void
    {
        $s->getStyle($rentang)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BIRU]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    /** Garis tipis abu di seluruh tabel. */
    public static function garisTabel(Worksheet $s, string $rentang): void
    {
        $s->getStyle($rentang)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GARIS);
    }

    /** Pengaturan cetak: A4, muat selebar halaman, judul kolom diulang tiap halaman, kaki halaman bernomor. */
    public static function cetakExcel(Worksheet $s, string $judulDokumen, bool $mendatar = true, ?int $barisHeader = null): void
    {
        $s->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation($mendatar ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)->setFitToHeight(0);
        if ($barisHeader) {
            $s->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($barisHeader, $barisHeader);
        }
        $s->getPageMargins()->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setFooter(0.25);
        $kiri = str_replace('&', '&&', 'PT Detpak Indonesia · ' . $judulDokumen);
        $s->getHeaderFooter()->setOddFooter('&L&8' . $kiri . '&R&8' . __('Page :p of :n', ['p' => '&P', 'n' => '&N']));
    }

    /** Unduhan .xlsx. */
    public static function unduhXlsx(Spreadsheet $buku, string $namaFile): StreamedResponse
    {
        $buku->setActiveSheetIndex(0);

        return response()->streamDownload(fn () => (new Xlsx($buku))->save('php://output'), $namaFile,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Isi CSV: UTF-8 dengan BOM (huruf khusus terbaca benar di Excel), pemisah koma, baris pertama = judul kolom.
     * Angka ditulis polos (tanpa pemisah ribuan) supaya bisa langsung dihitung / diimpor ke sistem lain.
     */
    public static function isiCsv(array $header, iterable $baris): string
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $header);
        foreach ($baris as $r) {
            fputcsv($f, array_map(fn ($v) => is_float($v) ? (fmod($v, 1.0) == 0.0 ? (string) (int) $v : (string) round($v, 2)) : $v, array_values($r)));
        }
        rewind($f);
        $isi = stream_get_contents($f);
        fclose($f);

        return $isi;
    }

    public static function unduhCsv(string $namaFile, array $header, iterable $baris): StreamedResponse
    {
        $isi = self::isiCsv($header, $baris);

        return response()->streamDownload(fn () => print($isi), $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Beberapa tabel sekaligus: satu ZIP berisi satu CSV per tabel. @param array<string, array{0: array, 1: iterable}> $tabel */
    public static function unduhCsvZip(string $namaFile, array $tabel): StreamedResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $no = 0;
        foreach ($tabel as $nama => [$header, $baris]) {
            $zip->addFromString(sprintf('%02d-%s.csv', ++$no, self::slug($nama)), self::isiCsv($header, $baris));
        }
        $zip->close();

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $namaFile, ['Content-Type' => 'application/zip']);
    }

    public static function slug(string $teks): string
    {
        return trim(preg_replace('/[^A-Za-z0-9]+/', '-', \Illuminate\Support\Str::ascii($teks)), '-');
    }
}
