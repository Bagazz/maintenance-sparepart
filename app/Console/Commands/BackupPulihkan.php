<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Pulihkan dari file backup. Data saat ini DITIMPA, jadi sebelum memulihkan dibuat backup pengaman dulu.
 *   php artisan backup:pulihkan backup-20261022-120000.zip
 *   php artisan backup:pulihkan D:\Backup\backup-....zip --database=uji_pulih   (coba ke database lain)
 */
class BackupPulihkan extends Command
{
    protected $signature = 'backup:pulihkan {file : Nama file di folder backup, atau path lengkap}
                            {--database= : Database tujuan (bawaan: database aplikasi)}
                            {--tanpa-foto : Jangan pulihkan foto barang}
                            {--force : Tanpa pertanyaan konfirmasi}';

    protected $description = 'Pulihkan database (dan foto) dari file backup zip';

    public function handle(BackupService $backup): int
    {
        $file = $this->argument('file');
        $path = is_file($file) ? $file : $backup->path(basename($file));
        if (!$path) {
            $this->error("File backup tidak ditemukan: $file");
            return self::FAILURE;
        }

        $db = $this->option('database') ?: config('database.connections.mysql.database');
        $utama = $db === config('database.connections.mysql.database');

        if ($utama) {
            $this->warn("PERHATIAN: semua data di database \"$db\" akan diganti dengan isi " . basename($path) . '.');
            if (!$this->option('force') && !$this->confirm('Lanjutkan pemulihan?')) {
                $this->line('Dibatalkan.');
                return self::FAILURE;
            }
            $this->line('Membuat backup pengaman dari data saat ini...');
            $pengaman = $backup->jalankan('sebelum-pemulihan');
            $this->line('Backup pengaman: ' . $pengaman['file']);
        }

        try {
            $h = $backup->pulihkan($path, $db, !$this->option('tanpa-foto') && $utama);
        } catch (\Throwable $e) {
            $this->error('Pemulihan GAGAL: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Pemulihan selesai ke database \"$db\" dari backup " . ($h['manifest']['dibuat'] ?? basename($path)) . " ({$h['foto']} foto).");

        return self::SUCCESS;
    }
}
