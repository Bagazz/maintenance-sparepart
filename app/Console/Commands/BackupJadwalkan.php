<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Daftarkan / hapus tugas Windows Task Scheduler untuk backup harian.
 * Jalankan sekali di PC yang menjalankan aplikasi:  php artisan backup:jadwalkan
 * Bila PC mati saat jam backup, tugas dijalankan begitu PC menyala (StartWhenAvailable).
 */
class BackupJadwalkan extends Command
{
    public const NAMA_TUGAS = 'Detpak Sparepart - Backup Harian';

    protected $signature = 'backup:jadwalkan {--jam= : Jam backup HH:MM (bawaan dari BACKUP_TIME, 12:00)} {--hapus : Hapus jadwal} {--cek : Tampilkan status jadwal}';

    protected $description = 'Daftarkan backup harian ke Windows Task Scheduler';

    public function handle(): int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->warn('Bukan Windows. Tambahkan ke crontab:  0 12 * * * cd ' . base_path() . ' && php artisan backup:database');
            return self::FAILURE;
        }

        $nama = self::NAMA_TUGAS;
        if ($this->option('cek')) {
            return $this->ps("Get-ScheduledTask -TaskName '$nama' | Get-ScheduledTaskInfo | Format-List LastRunTime,LastTaskResult,NextRunTime");
        }
        if ($this->option('hapus')) {
            return $this->ps("Unregister-ScheduledTask -TaskName '$nama' -Confirm:\$false", 'Jadwal backup dihapus.');
        }

        $jam = $this->option('jam') ?: config('backup.jam');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam)) {
            $this->error('Format jam harus HH:MM, mis. 12:00');
            return self::FAILURE;
        }

        $php = str_replace("'", "''", PHP_BINARY);
        $dir = str_replace("'", "''", base_path());
        $skrip = implode('; ', [
            "\$aksi = New-ScheduledTaskAction -Execute '$php' -Argument 'artisan backup:database' -WorkingDirectory '$dir'",
            "\$pemicu = New-ScheduledTaskTrigger -Daily -At '$jam'",
            "\$atur = New-ScheduledTaskSettingsSet -StartWhenAvailable -DontStopIfGoingOnBatteries -AllowStartIfOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)",
            "Register-ScheduledTask -TaskName '$nama' -Description 'Backup database & foto aplikasi Spare Part Maintenance PT Detpak Indonesia' -Action \$aksi -Trigger \$pemicu -Settings \$atur -Force | Out-Null",
        ]);

        return $this->ps($skrip, "Backup harian dijadwalkan setiap pukul $jam (tugas \"$nama\" di Task Scheduler).");
    }

    private function ps(string $skrip, ?string $pesanOk = null): int
    {
        $p = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command', $skrip], null, null, null, 60);
        $p->run();
        if (!$p->isSuccessful()) {
            $this->error(trim($p->getErrorOutput() ?: $p->getOutput()));
            return self::FAILURE;
        }
        $this->line(trim($p->getOutput()));
        if ($pesanOk) {
            $this->info($pesanOk);
        }

        return self::SUCCESS;
    }
}
