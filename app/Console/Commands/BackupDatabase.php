<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/** Backup database + foto ke satu file zip. Dijalankan harian oleh Windows Task Scheduler. */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--pemicu=terjadwal : Dicatat di manifest (terjadwal / manual)}';

    protected $description = 'Backup database spare part + foto barang ke file zip (dengan pembersihan backup lama)';

    public function handle(BackupService $backup): int
    {
        try {
            $h = $backup->jalankan($this->option('pemicu'));
        } catch (\Throwable $e) {
            $this->error('Backup GAGAL: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Backup berhasil: {$h['file']} (" . number_format($h['ukuran'] / 1024, 0, ',', '.') . " KB, {$h['foto']} foto)");
        $this->line('Folder: ' . $backup->folder());
        if ($h['salinan'] === true) {
            $this->line('Disalin ke: ' . config('backup.salin_ke'));
        } elseif (is_string($h['salinan'])) {
            $this->warn('Gagal menyalin ke folder kedua: ' . $h['salinan']);
        }
        if ($h['dihapus']) {
            $this->line("{$h['dihapus']} backup lama dihapus (lebih dari " . config('backup.simpan_hari') . ' hari).');
        }

        return self::SUCCESS;
    }
}
