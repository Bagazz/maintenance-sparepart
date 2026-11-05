<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backup harian: satu file zip berisi database.sql (mysqldump) + foto barang/rak + manifest.json.
 * Status terakhir disimpan di status.json supaya halaman Backup & dashboard bisa memperingatkan.
 */
class BackupService
{
    public const POLA_NAMA = '/^backup-\d{8}-\d{6}\.zip$/';
    private const MINIMAL_DISIMPAN = 3;

    public function folder(): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, config('backup.folder')), DIRECTORY_SEPARATOR);
    }

    private function fileStatus(): string
    {
        return $this->folder() . DIRECTORY_SEPARATOR . 'status.json';
    }

    /** Cari exe MySQL: .env -> XAMPP -> PATH. */
    public function alat(string $nama): string
    {
        if ($dari = config("backup.$nama")) {
            return $dari;
        }
        foreach (['C:\\xampp\\mysql\\bin\\', '/opt/lampp/bin/', '/usr/bin/'] as $dir) {
            foreach (["$nama.exe", $nama] as $f) {
                if (is_file($dir . $f)) {
                    return $dir . $f;
                }
            }
        }

        return $nama;
    }

    /** Variabel lingkungan untuk proses mysql: password lewat MYSQL_PWD supaya tidak tampil di daftar proses. */
    private function envMysql(): array
    {
        return ['MYSQL_PWD' => (string) config('database.connections.mysql.password')];
    }

    private function argKoneksi(): array
    {
        $c = config('database.connections.mysql');

        return ['--host=' . $c['host'], '--port=' . $c['port'], '--user=' . $c['username'], '--default-character-set=utf8mb4'];
    }

    /** Buat backup sekarang. Mengembalikan ringkasan; melempar exception bila gagal (status gagal tetap dicatat). */
    public function jalankan(string $pemicu = 'manual'): array
    {
        File::ensureDirectoryExists($this->folder());
        $waktu = now();
        $nama = 'backup-' . $waktu->format('Ymd-His') . '.zip';
        $zipPath = $this->folder() . DIRECTORY_SEPARATOR . $nama;
        $sqlPath = $this->folder() . DIRECTORY_SEPARATOR . 'tmp-' . $waktu->format('Ymd-His') . '.sql';

        try {
            // 1) Dump database (konsisten tanpa mengunci tabel: --single-transaction untuk InnoDB).
            $proses = new Process(array_merge(
                [$this->alat('mysqldump')],
                $this->argKoneksi(),
                ['--single-transaction', '--routines', '--triggers', '--skip-lock-tables', '--result-file=' . $sqlPath, config('database.connections.mysql.database')]
            ), base_path(), $this->envMysql(), null, 600);
            $proses->run();
            if (!$proses->isSuccessful()) {
                throw new \RuntimeException('mysqldump: ' . trim($proses->getErrorOutput() ?: $proses->getOutput()));
            }
            $ekor = $this->baris($sqlPath, -1);
            if (!is_file($sqlPath) || filesize($sqlPath) < 1000 || !str_contains($ekor, 'Dump completed')) {
                throw new \RuntimeException(__('Database dump is incomplete.'));
            }

            // 2) Zip: database + foto + manifest.
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException(__('Cannot create the backup file.'));
            }
            $zip->addFile($sqlPath, 'database.sql');
            $jumlahFoto = 0;
            $dirFoto = storage_path('app/public');
            if (is_dir($dirFoto)) {
                foreach (File::allFiles($dirFoto) as $f) {
                    $zip->addFile($f->getPathname(), 'foto/' . str_replace('\\', '/', $f->getRelativePathname()));
                    $jumlahFoto++;
                }
            }
            $zip->addFromString('manifest.json', json_encode([
                'aplikasi' => config('app.name') . ' — Spare Part Maintenance PT Detpak Indonesia',
                'dibuat'   => $waktu->toIso8601String(),
                'pemicu'   => $pemicu,
                'database' => config('database.connections.mysql.database'),
                'baris'    => $this->hitungBaris(),
                'foto'     => $jumlahFoto,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->close();

            // 3) Pastikan zip bisa dibuka & database.sql utuh di dalamnya.
            $cek = new ZipArchive();
            if ($cek->open($zipPath) !== true || $cek->statName('database.sql')['size'] !== filesize($sqlPath)) {
                throw new \RuntimeException(__('The backup file failed verification.'));
            }
            $cek->close();
        } catch (\Throwable $e) {
            @unlink($zipPath);
            $this->simpanStatus(['ok' => false, 'waktu' => $waktu->toIso8601String(), 'pemicu' => $pemicu, 'pesan' => $e->getMessage()]);
            Log::error('Backup gagal: ' . $e->getMessage());
            throw $e;
        } finally {
            @unlink($sqlPath);
        }

        // 4) Salinan kedua (gagal menyalin tidak menggagalkan backup, tapi dicatat).
        $salinan = null;
        if ($tujuan = config('backup.salin_ke')) {
            try {
                File::ensureDirectoryExists($tujuan);
                File::copy($zipPath, rtrim($tujuan, '\\/') . DIRECTORY_SEPARATOR . $nama);
                $salinan = true;
            } catch (\Throwable $e) {
                $salinan = $e->getMessage();
                Log::warning('Backup tersimpan, tetapi gagal disalin ke ' . $tujuan . ': ' . $e->getMessage());
            }
        }

        $dihapus = $this->bersihkan();
        $hasil = [
            'ok' => true, 'waktu' => $waktu->toIso8601String(), 'pemicu' => $pemicu, 'file' => $nama,
            'ukuran' => filesize($zipPath), 'foto' => $jumlahFoto, 'salinan' => $salinan, 'dihapus' => $dihapus,
        ];
        $this->simpanStatus($hasil);
        Log::info("Backup berhasil: $nama (" . round($hasil['ukuran'] / 1024) . ' KB)');

        return $hasil;
    }

    /** Hapus backup lama (lebih tua dari simpan_hari), tetapi selalu sisakan 3 terbaru. Di folder utama & salinan. */
    public function bersihkan(): int
    {
        $batas = now()->subDays(max(1, config('backup.simpan_hari')));
        $dihapus = 0;
        foreach (array_filter([$this->folder(), config('backup.salin_ke')]) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $files = collect(File::files($dir))->filter(fn ($f) => preg_match(self::POLA_NAMA, $f->getFilename()))
                ->sortByDesc(fn ($f) => $f->getFilename())->values();
            foreach ($files->slice(self::MINIMAL_DISIMPAN) as $f) {
                if ($this->waktuDariNama($f->getFilename())->lt($batas)) {
                    @unlink($f->getPathname()) && $dihapus++;
                }
            }
        }

        return $dihapus;
    }

    public function daftar(): Collection
    {
        if (!is_dir($this->folder())) {
            return collect();
        }

        return collect(File::files($this->folder()))
            ->filter(fn ($f) => preg_match(self::POLA_NAMA, $f->getFilename()))
            ->map(fn ($f) => (object) ['nama' => $f->getFilename(), 'ukuran' => $f->getSize(), 'waktu' => $this->waktuDariNama($f->getFilename())])
            ->sortByDesc('nama')->values();
    }

    public function status(): array
    {
        $s = is_file($this->fileStatus()) ? (json_decode(file_get_contents($this->fileStatus()), true) ?: []) : [];
        $terakhirOk = $this->daftar()->first();
        $batas = now()->subHours(config('backup.peringatan_jam'));

        return [
            'terakhir'     => $s,                                   // percobaan terakhir (berhasil / gagal)
            'terakhir_ok'  => $terakhirOk,                          // file backup terbaru yang ada
            'perlu_cek'    => !$terakhirOk || $terakhirOk->waktu->lt($batas) || (($s['ok'] ?? true) === false),
        ];
    }

    public function path(string $nama): ?string
    {
        if (!preg_match(self::POLA_NAMA, $nama)) {
            return null;
        }
        $p = $this->folder() . DIRECTORY_SEPARATOR . $nama;

        return is_file($p) ? $p : null;
    }

    /**
     * Pulihkan database (dan foto) dari file backup ke database tujuan.
     * Dipakai oleh: php artisan backup:pulihkan — tidak tersedia dari halaman web.
     */
    public function pulihkan(string $zipPath, string $database, bool $denganFoto = true): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true || $zip->locateName('database.sql') === false) {
            throw new \RuntimeException(__('This is not a valid backup file.'));
        }
        $tmp = storage_path('app/backups/tmp-pulihkan-' . uniqid() . '.sql');
        File::ensureDirectoryExists(dirname($tmp));
        file_put_contents($tmp, $zip->getStream('database.sql'));

        try {
            $buat = new Process(array_merge([$this->alat('mysql')], $this->argKoneksi(),
                ['-e', 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci']), base_path(), $this->envMysql(), null, 120);
            $buat->mustRun();

            $impor = new Process(array_merge([$this->alat('mysql')], $this->argKoneksi(), [$database]), base_path(), $this->envMysql(), null, 1800);
            $impor->setInput(fopen($tmp, 'r'));
            $impor->run();
            if (!$impor->isSuccessful()) {
                throw new \RuntimeException('mysql: ' . trim($impor->getErrorOutput()));
            }
        } finally {
            @unlink($tmp);
        }

        $foto = 0;
        if ($denganFoto) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (str_starts_with($n, 'foto/') && !str_ends_with($n, '/') && !str_contains($n, '..')) {
                    $tujuan = storage_path('app/public/' . substr($n, 5));
                    File::ensureDirectoryExists(dirname($tujuan));
                    file_put_contents($tujuan, $zip->getStream($n));
                    $foto++;
                }
            }
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true) ?: [];
        $zip->close();

        return ['manifest' => $manifest, 'foto' => $foto];
    }

    private function hitungBaris(): array
    {
        $db = config('database.connections.mysql.database');

        return collect(DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = ?', [$db, 'BASE TABLE']))
            ->mapWithKeys(fn ($r) => [$r->t => DB::table($r->t)->count()])->all();
    }

    private function simpanStatus(array $s): void
    {
        File::ensureDirectoryExists($this->folder());
        file_put_contents($this->fileStatus(), json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function waktuDariNama(string $nama): Carbon
    {
        return Carbon::createFromFormat('Ymd-His', substr($nama, 7, 15));
    }

    /** Baris ke-n dari akhir file (tanpa membaca seluruh file besar). */
    private function baris(string $path, int $dariAkhir): string
    {
        if (!is_file($path)) {
            return '';
        }
        $f = fopen($path, 'r');
        fseek($f, max(0, filesize($path) - 512));
        $isi = stream_get_contents($f);
        fclose($f);
        $baris = array_values(array_filter(explode("\n", trim($isi))));

        return $baris[count($baris) + $dariAkhir] ?? '';
    }
}
