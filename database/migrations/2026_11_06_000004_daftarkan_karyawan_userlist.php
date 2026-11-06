<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Daftar karyawan resmi (sheet "Userlist" Equipment1.xlsx dari Pak Nardi): nama, No ID, jabatan.
 * Password awal = No ID. Aman dijalankan di laptop mana pun:
 * - karyawan yang sudah ada (No ID / nama sama) tidak digandakan;
 * - password yang sudah diatur TIDAK ditimpa; jabatan hanya diisi bila masih kosong.
 */
return new class extends Migration
{
    private const KARYAWAN = [
        ['ANTON HIRMAWAN', '200917', 'Manager'],
        ['SAMADI', '20001', 'Supervisor'],
        ['LAURA BERTON SARAGIH', '201128', 'Supervisor'],
        ['DAMASTUS PALIMAN', '100399', 'Electrical Technician'],
        ['ABI SANTOSO', '201105', 'Electrical Technician'],
        ['BOY HERTANTO', '200361', 'Mechanical Technician'],
        ['AMRIH SUGIHARTO', '200384', 'Mechanical Technician'],
        ['DEDE RIDWAN KAMIL', '200859', 'Mechanical Technician'],
        ['SUNARDI', '200156', 'Store Keeper'],
        ['AHMAD HUSEN', '201359', 'Engineering'],
        ['KEVIN FERNADO', '201370', 'Engineering Drafter'],
        ['JIKI JULIANTO', '201054', 'Workshop'],
    ];

    public function up(): void
    {
        $sekarang = now();
        foreach (self::KARYAWAN as [$nama, $noId, $jabatan]) {
            $k = DB::table('karyawans')->where('nomor_kartu', $noId)->first()
                ?? DB::table('karyawans')->where('nama', $nama)->first();

            if (!$k) {
                DB::table('karyawans')->insert([
                    'nama' => $nama, 'nomor_kartu' => $noId, 'bagian' => $jabatan, 'aktif' => true,
                    'pin_hash' => Hash::make($noId), 'pin_gagal' => 0, 'pin_diatur_at' => $sekarang,
                    'created_at' => $sekarang, 'updated_at' => $sekarang,
                ]);
                continue;
            }

            $ubah = [];
            if (empty($k->nomor_kartu)) $ubah['nomor_kartu'] = $noId;
            if (empty($k->bagian)) $ubah['bagian'] = $jabatan;
            if (empty($k->pin_hash)) $ubah += ['pin_hash' => Hash::make($noId), 'pin_gagal' => 0, 'pin_diatur_at' => $sekarang];
            if ($ubah) {
                DB::table('karyawans')->where('id', $k->id)->update($ubah + ['updated_at' => $sekarang]);
            }
        }
    }

    public function down(): void
    {
        // Data karyawan tidak dihapus saat rollback.
    }
};
