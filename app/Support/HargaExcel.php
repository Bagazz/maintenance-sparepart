<?php

namespace App\Support;

/**
 * Menentukan harga satuan (Rp) dari satu baris sheet master ERP (M0331agt).
 *
 * Kolom sheet:  G = unit cost USD, H = konversi IDR (kurs Excel), I = "Unit cost IDR"
 * Urutan prioritas:
 *   1. I  — harga IDR yang sudah dicek manager (paling akurat, mis. per meter untuk UOM MT)
 *   2. H  — USD x kurs yang dipakai Excel
 *   3. G  — USD x KURS_USD_IDR
 * Sel berisi "#N/A" atau teks lain dianggap kosong.
 */
class HargaExcel
{
    /** Kurs yang dipakai file Excel manager (median kolom H / G). */
    public const KURS_USD_IDR = 17745;

    public static function angka($nilai): ?float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return $nilai > 0 ? (float) $nilai : null;
        }

        $teks = trim((string) $nilai);
        if ($teks === '' || str_starts_with($teks, '#')) {
            return null;
        }

        // Format Indonesia "36.306,270" -> 36306.270
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $teks)) {
            $teks = str_replace(['.', ','], ['', '.'], $teks);
        }

        return is_numeric($teks) && (float) $teks > 0 ? (float) $teks : null;
    }

    /** @param array $baris baris dengan kunci kolom huruf (A, B, ...) */
    public static function dariBarisMaster(array $baris): float
    {
        if ($idr = self::angka($baris['I'] ?? null)) {
            return round($idr, 2);
        }

        if ($idr = self::angka($baris['H'] ?? null)) {
            return round($idr, 2);
        }

        if ($usd = self::angka($baris['G'] ?? null)) {
            return round($usd * self::KURS_USD_IDR, 2);
        }

        return 0;
    }
}
