<?php

namespace App\Http\Controllers;

use App\Console\Commands\BackupJadwalkan;
use App\Services\BackupService;
use Symfony\Component\Process\Process;

/** Halaman Backup (admin): status backup harian, daftar file, backup sekarang, unduh. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backup)
    {
    }

    public function index()
    {
        return view('backup.index', [
            'status'  => $this->backup->status(),
            'daftar'  => $this->backup->daftar(),
            'folder'  => $this->backup->folder(),
            'jadwal'  => $this->jadwal(),
        ]);
    }

    public function jalankan()
    {
        try {
            $h = $this->backup->jalankan('manual');
        } catch (\Throwable $e) {
            return back()->with('error', __('Backup failed:') . ' ' . $e->getMessage());
        }

        return back()->with('success', __('Backup created: :file (:kb KB).', ['file' => $h['file'], 'kb' => number_format($h['ukuran'] / 1024, 0, ',', '.')]));
    }

    public function unduh(string $nama)
    {
        $path = $this->backup->path($nama);
        abort_unless($path, 404);

        return response()->download($path);
    }

    /** Jadwal Windows Task Scheduler: waktu berikutnya, atau null bila belum terdaftar / bukan Windows. */
    private function jadwal(): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }
        $nama = BackupJadwalkan::NAMA_TUGAS;
        $p = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command',
            "\$i = Get-ScheduledTask -TaskName '$nama' -ErrorAction SilentlyContinue | Get-ScheduledTaskInfo; if (\$i) { \$i.NextRunTime.ToString('yyyy-MM-ddTHH:mm:ss') + '|' + \$i.LastRunTime.ToString('yyyy-MM-ddTHH:mm:ss') + '|' + \$i.LastTaskResult }"], null, null, null, 20);
        $p->run();
        $out = trim($p->getOutput());
        if (!$p->isSuccessful() || $out === '') {
            return null;
        }
        [$berikut, $terakhir, $hasil] = array_pad(explode('|', $out), 3, null);

        return [
            'berikut'  => $berikut ? \Illuminate\Support\Carbon::parse($berikut) : null,
            'terakhir' => $terakhir && !str_starts_with($terakhir, '1999') ? \Illuminate\Support\Carbon::parse($terakhir) : null,
            'hasil'    => $hasil,
        ];
    }
}
