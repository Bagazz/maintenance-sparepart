<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
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

    /**
     * Batas baris PDF. dompdf butuh ±0,5 MB memori per baris tabel (400 baris ≈ 200 MB, ±4 detik),
     * jadi PDF = laporan cetak berisi catatan terbaru; daftar lengkap tanpa batas ada di Excel & CSV.
     */
    public const MAKS_BARIS_PDF = 400;

    /**
     * Unduh satu laporan tabel dalam format pdf | xlsx | csv dengan isi yang sama persis.
     *
     * @param array $kolom [[judul, jenis]] jenis: tanggal | angka | rp | teks
     * @param iterable $baris nilai mentah (tanggal 'Y-m-d', angka/Rupiah numerik)
     * @param array $total [indeks kolom => nilai] (indeks 0 = teks "Total")
     * @param array $ringkasan [label => nilai numerik/teks]
     */
    public static function unduhTabel(string $format, string $judul, string $namaFile, array $kolom, iterable $baris, array $total, array $ringkasan, array $keterangan, string $namaTabel, string $kertas = 'landscape')
    {
        $baris = collect($baris)->values();
        $header = array_column($kolom, 0);
        $jenis = array_column($kolom, 1);

        if ($format === 'csv') {
            return self::unduhCsv($namaFile . '.csv', $header, $baris);
        }
        if ($format === 'xlsx') {
            return self::unduhXlsx(self::bukuTabel($judul, $keterangan, $kolom, $baris->all(), $total, $ringkasan, $namaTabel), $namaFile . '.xlsx');
        }

        // ---- PDF ---- (memori & waktu ditambah khusus untuk pembuatan PDF)
        ini_set('memory_limit', '512M');
        set_time_limit(120);
        $terpotong = $baris->count() > self::MAKS_BARIS_PDF;
        $tampil = fn ($v, $j) => match ($j) {
            'tanggal' => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d M Y') : (string) ($v ?: '-'),
            'angka', 'rp' => is_numeric($v) ? number_format((float) $v, 0, ',', '.') : (string) $v,
            default => (string) $v,
        };
        $barisPdf = $baris->take(self::MAKS_BARIS_PDF)->map(fn ($r) => array_map($tampil, array_values($r), $jenis))->all();
        $kanan = array_keys(array_filter($jenis, fn ($j) => in_array($j, ['angka', 'rp'], true)));
        $totalPdf = collect($total)->map(fn ($v, $i) => $i === 0 ? $v : $tampil($v, $jenis[$i]))->all();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('ekspor.tabel-pdf', [
            'judulDok'   => $judul,
            'keterangan' => $keterangan,
            'kaki'       => $judul,
            'ringkasan'  => collect($ringkasan)->map(fn ($v) => is_numeric($v) ? number_format((float) $v, 0, ',', '.') : $v)->all(),
            'header'     => $header,
            'baris'      => $barisPdf,
            'kanan'      => $kanan,
            'total'      => $terpotong ? [] : $totalPdf,
            'catatan'    => $terpotong ? __('Showing the latest :n of :total records. Download the Excel or CSV for the complete list.', ['n' => number_format(self::MAKS_BARIS_PDF, 0, ',', '.'), 'total' => number_format($baris->count(), 0, ',', '.')]) : null,
        ])->setOption('enable_font_subsetting', true)->setPaper('a4', $kertas)->download($namaFile . '.pdf');   // font dipangkas: file kecil
    }

    /** Unduhan .xlsx. */
    public static function unduhXlsx(Spreadsheet $buku, string $namaFile): StreamedResponse
    {
        $buku->setActiveSheetIndex(0);

        return response()->streamDownload(fn () => (new Xlsx($buku))->save('php://output'), $namaFile,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Isi CSV yang langsung rapi per kolom saat dibuka di Excel:
     * UTF-16LE dengan BOM + pemisah TAB. Format ini dibaca Excel dengan benar di pengaturan regional apa pun
     * (Windows Indonesia memakai pemisah ";" sehingga CSV koma biasa masuk ke satu kolom) dan huruf khusus (· −) tetap utuh.
     * Angka ditulis polos tanpa pemisah ribuan; Rupiah dibulatkan ke rupiah penuh. Tanggal format ISO (YYYY-MM-DD).
     */
    public static function isiCsv(array $header, iterable $baris): string
    {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, $header, "\t");
        foreach ($baris as $r) {
            fputcsv($f, array_map(fn ($v) => is_float($v) ? (string) (int) round($v) : (is_string($v) ? str_replace(["\r", "\n", "\t"], ' ', $v) : $v), array_values($r)), "\t");
        }
        rewind($f);
        $isi = str_replace("\n", "\r\n", stream_get_contents($f));
        fclose($f);

        return "\xFF\xFE" . mb_convert_encoding($isi, 'UTF-16LE', 'UTF-8');
    }

    public static function unduhCsv(string $namaFile, array $header, iterable $baris): StreamedResponse
    {
        $isi = self::isiCsv($header, $baris);

        return response()->streamDownload(fn () => print($isi), $namaFile, ['Content-Type' => 'text/csv; charset=UTF-16LE']);
    }

    /**
     * Ubah rentang data menjadi Tabel Excel (Format as Table): tombol filter di tiap judul kolom,
     * baris berselang warna, ikut melebar bila baris ditambah. Rentang termasuk baris judul kolom.
     */
    public static function tabelExcel(Worksheet $s, string $rentang, string $nama): void
    {
        $tabel = new Table($rentang, preg_replace('/[^A-Za-z0-9_]/', '', $nama));
        $tabel->setStyle((new TableStyle())->setTheme(TableStyle::TABLE_STYLE_MEDIUM2)->setShowRowStripes(true));
        $s->addTable($tabel);
    }

    /** Lebar kolom otomatis dari isi terpanjang (judul & data), dibatasi supaya tetap muat dicetak. */
    public static function lebarOtomatis(Worksheet $s, array $header, array $baris, int $min = 8, int $maks = 50): void
    {
        foreach (array_values($header) as $i => $h) {
            $panjang = mb_strlen((string) $h) + 2;
            foreach (array_slice($baris, 0, 2000) as $r) {
                $v = array_values($r)[$i] ?? '';
                $teks = is_float($v) || is_int($v) ? number_format((float) $v, 0, ',', '.') : (string) $v;
                $panjang = max($panjang, mb_strlen($teks) + 2);
            }
            $s->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth(max($min, min($maks, $panjang)));
        }
    }

    /**
     * Satu lembar laporan tabel yang lengkap & rapi: kop berlogo, kotak ringkasan, Tabel Excel otomatis,
     * format per kolom (tanggal, angka, Rupiah, teks), baris total, siap cetak A4.
     *
     * @param array $kolom [[judul, jenis]] jenis: tanggal | angka | rp | teks
     * @param array $baris nilai mentah per baris (tanggal 'Y-m-d')
     * @param array $total [indeks kolom => nilai/teks] (opsional)
     * @param array $ringkasan [label => nilai] ditampilkan di atas tabel (opsional)
     */
    public static function bukuTabel(string $judul, array $keterangan, array $kolom, array $baris, array $total = [], array $ringkasan = [], string $namaTabel = 'Data'): Spreadsheet
    {
        $buku = self::bukuBaru($judul);
        $s = $buku->getActiveSheet();
        $s->setTitle(mb_substr($judul, 0, 31));
        $s->setShowGridlines(false);
        $header = array_column($kolom, 0);
        $jenis = array_column($kolom, 1);
        $kolomAkhir = Coordinate::stringFromColumnIndex(count($header));

        self::lebarOtomatis($s, $header, $baris);
        $r = self::kopExcel($s, $judul, $keterangan, $kolomAkhir);

        // Kotak ringkasan (label kecil di atas, nilai tebal di bawah), dua kolom per kotak.
        if ($ringkasan) {
            $k = 1;
            foreach ($ringkasan as $label => $nilai) {
                $c1 = Coordinate::stringFromColumnIndex($k);
                $c2 = Coordinate::stringFromColumnIndex(min($k + 1, count($header)));
                $s->setCellValue("{$c1}{$r}", mb_strtoupper($label))->mergeCells("{$c1}{$r}:{$c2}{$r}");
                $s->setCellValue($c1 . ($r + 1), $nilai)->mergeCells($c1 . ($r + 1) . ':' . $c2 . ($r + 1));
                $s->getStyle("{$c1}{$r}")->getFont()->setBold(true)->setSize(8)->getColor()->setRGB(self::ABU);
                $s->getStyle($c1 . ($r + 1))->getFont()->setBold(true)->setSize(13);
                $s->getStyle($c1 . ($r + 1))->getNumberFormat()->setFormatCode(self::FORMAT_RP);
                $s->getStyle($c1 . ($r + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $s->getStyle("{$c1}{$r}:{$c2}" . ($r + 1))->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF4FB']],
                    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::GARIS]]],
                ]);
                $k += 2;
                if ($k > count($header)) break;
            }
            $r += 3;
        }

        $hdr = $r;
        $s->fromArray($header, null, "A{$hdr}");
        self::gayaHeader($s, "A{$hdr}:{$kolomAkhir}{$hdr}");
        $s->getRowDimension($hdr)->setRowHeight(30);

        foreach ($baris as $isi) {
            $r++;
            foreach (array_values($isi) as $i => $v) {
                $sel = Coordinate::stringFromColumnIndex($i + 1) . $r;
                match ($jenis[$i] ?? 'teks') {
                    'tanggal' => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)
                        ? $s->setCellValue($sel, ExcelDate::stringToExcel((string) $v))
                        : $s->setCellValueExplicit($sel, (string) $v, DataType::TYPE_STRING),
                    'angka', 'rp' => is_numeric($v) ? $s->setCellValue($sel, $v) : $s->setCellValueExplicit($sel, (string) $v, DataType::TYPE_STRING),
                    default => $s->setCellValueExplicit($sel, (string) $v, DataType::TYPE_STRING),
                };
            }
        }
        $pertama = $hdr + 1;

        if ($r === $hdr) {
            $s->setCellValue("A{$pertama}", __('No data for this period.'))->mergeCells("A{$pertama}:{$kolomAkhir}{$pertama}");
            $s->getStyle("A{$pertama}")->getFont()->setItalic(true)->getColor()->setRGB(self::ABU);
        } else {
            foreach ($jenis as $i => $j) {
                $c = Coordinate::stringFromColumnIndex($i + 1);
                $gaya = $s->getStyle("{$c}{$pertama}:{$c}" . ($r + 1));
                match ($j) {
                    'tanggal' => $gaya->getNumberFormat()->setFormatCode('dd mmm yyyy'),
                    'angka', 'rp' => $gaya->getNumberFormat()->setFormatCode(self::FORMAT_RP),
                    default => null,
                };
                if (in_array($j, ['tanggal', 'angka', 'rp'], true)) {
                    $s->getStyle("{$c}{$hdr}")->getAlignment()->setHorizontal(in_array($j, ['angka', 'rp'], true) ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
                }
            }
            // Teks panjang (mis. deskripsi) turun baris di dalam kolomnya — tidak terpotong kolom sebelah.
            $s->getStyle("A{$pertama}:{$kolomAkhir}{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            self::tabelExcel($s, "A{$hdr}:{$kolomAkhir}{$r}", $namaTabel);

            if ($total) {
                $t = $r + 1;
                foreach ($total as $i => $v) {
                    $c = Coordinate::stringFromColumnIndex($i + 1);
                    $s->setCellValue("{$c}{$t}", $i === 0 ? $v : "=SUBTOTAL(109,{$c}{$pertama}:{$c}{$r})");
                }
                $s->getStyle("A{$t}:{$kolomAkhir}{$t}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE8F5']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::BIRU]]],
                ]);
            }
        }

        $s->freezePane('A' . ($hdr + 1));
        self::cetakExcel($s, $judul, true, $hdr);
        $s->setSelectedCell('A' . ($hdr + 1));

        return $buku;
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
