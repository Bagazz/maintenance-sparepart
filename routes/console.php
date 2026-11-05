<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Isi kategori awal untuk katalog foto berdasarkan kata di deskripsi part.
| Hanya mengisi part yang kategorinya masih kosong; hasilnya tetap bisa
| dikoreksi manual lewat menu Kelola Part.
|   php artisan sparepart:isi-kategori           -> simulasi (tidak menyimpan)
|   php artisan sparepart:isi-kategori --simpan  -> simpan ke database
*/
Artisan::command('sparepart:isi-kategori {--simpan : Simpan hasil ke database}', function () {
    // Urutan penting: aturan yang lebih spesifik ditaruh di atas. '^' = harus di awal deskripsi.
    $aturan = [
        'Relay'               => ['RELAY'],
        'Seal & O-Ring'       => ['OIL SEAL', 'SEAL', 'O RING', 'O-RING', 'ORING'],
        'Housing'             => ['^HOUSING'],
        'Bearing'             => ['BEARING'],
        'Gear & Sprocket'     => ['GEAR', 'SPROCKET'],
        'Belt'                => ['VAN BELT', 'V BELT', 'V-BELT', 'TIMING BELT', 'BELT'],
        'Snap Ring'           => ['SNAPRING', 'SNAP RING', 'CIRCLIP'],
        'Baut, Mur & Ring'    => ['BOLT', 'SCREW', 'NUT', 'WASHER'],
        'Fitting & Selang'    => ['FITTING', 'TUBING', 'HOSE', 'NIPPLE', 'CONNECTOR'],
        'Pneumatik & Valve'   => ['SOLENOID', 'VALVE', 'REGULATOR', 'AIR CYLINDER'],
        'Sensor'              => ['SENSOR', 'PROXIMITY', 'PHOTOELECTRIC', 'LIMIT SWITCH', 'ENCODER'],
        'Elektrikal'          => ['POWER SUPPLY', 'INVERTER', 'AC DRIVE', 'CONTACTOR', 'MCB', 'FUSE', 'MOTOR', 'SWITCH', 'TIMER', 'CABLE'],
        'Shaft'               => ['^SHAFT'],
        'Spring'              => ['^SPRING'],
        'Bushing'             => ['BUSHING'],
        'Roller'              => ['^ROLLER'],
    ];

    $hitung = [];
    $perubahan = [];

    App\Models\Inventory::whereNull('kategori')->orderBy('id')
        ->chunk(500, function ($items) use ($aturan, &$hitung, &$perubahan) {
            foreach ($items as $item) {
                $desk = ' ' . mb_strtoupper($item->deskripsi) . ' ';

                foreach ($aturan as $kategori => $kataKunci) {
                    foreach ($kataKunci as $kata) {
                        $cocok = str_starts_with($kata, '^')
                            ? str_starts_with(ltrim($desk), substr($kata, 1) . ' ')
                            : preg_match('/[^A-Z0-9]' . preg_quote($kata, '/') . '[^A-Z0-9]/', $desk);

                        if ($cocok) {
                            $hitung[$kategori] = ($hitung[$kategori] ?? 0) + 1;
                            $perubahan[$kategori][] = $item->id;
                            continue 3;
                        }
                    }
                }

                $hitung['(tidak terdeteksi)'] = ($hitung['(tidak terdeteksi)'] ?? 0) + 1;
            }
        });

    arsort($hitung);
    $this->table(['Kategori', 'Jumlah part'], collect($hitung)->map(fn ($n, $k) => [$k, $n])->values());

    if (!$this->option('simpan')) {
        $this->warn('Simulasi saja. Jalankan dengan --simpan untuk menyimpan.');
        return;
    }

    foreach ($perubahan as $kategori => $ids) {
        foreach (array_chunk($ids, 500) as $potongan) {
            App\Models\Inventory::whereIn('id', $potongan)->whereNull('kategori')->update(['kategori' => $kategori]);
        }
    }

    $this->info('Kategori tersimpan.');
})->purpose('Isi kategori spare part otomatis dari deskripsi (untuk katalog foto)');

/*
| Hitung ulang harga satuan dari sheet master ERP (M0331agt) memakai kolom IDR
| manager (lihat App\Support\HargaExcel), lalu perbarui nilai Rp transaksi & snapshot.
|   php artisan sparepart:perbarui-harga "path\Data untuk Dasboard.xlsx"           -> simulasi
|   php artisan sparepart:perbarui-harga "path\Data untuk Dasboard.xlsx" --simpan  -> simpan
*/
Artisan::command('sparepart:perbarui-harga {file} {--simpan}', function (string $file) {
    if (!is_file($file)) {
        $this->error("File tidak ditemukan: $file");
        return 1;
    }

    $reader = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $sheet = $reader->load($file)->getSheetByName('M0331agt');
    if (!$sheet) {
        $this->error('Sheet M0331agt tidak ada di file ini.');
        return 1;
    }

    // Aturan sama dengan import: baris pertama, ditimpa baris berikutnya bila harganya > 0.
    $harga = [];
    foreach ($sheet->toArray(null, true, false, true) as $i => $row) {
        if ($i === 1 || empty($row['A'])) {
            continue;
        }
        $partNo = preg_replace('/\.0$/', '', trim((string) $row['A']));
        $h = App\Support\HargaExcel::dariBarisMaster($row);
        if (!isset($harga[$partNo]) || $h > 0) {
            $harga[$partNo] = $h;
        }
    }

    $ringkas = fn () => [
        'aset'      => (float) DB::table('inventories')->sum(DB::raw('stock * harga_beli_terakhir')),
        'biaya_out' => (float) DB::table('inventory_transactions')->where('type', 'OUT')->sum('total_harga'),
    ];
    $sebelum = $ringkas();

    $berubah = [];
    foreach (App\Models\Inventory::get(['id', 'part_no', 'deskripsi', 'harga_beli_terakhir']) as $inv) {
        if (!array_key_exists($inv->part_no, $harga)) {
            continue;
        }
        $baru = $harga[$inv->part_no];
        if (abs($baru - (float) $inv->harga_beli_terakhir) >= 0.01) {
            $berubah[$inv->id] = [$inv->part_no, $inv->deskripsi, (float) $inv->harga_beli_terakhir, $baru];
        }
    }

    $this->info(count($berubah) . ' part berubah harga. Contoh selisih terbesar:');
    $contoh = collect($berubah)->sortByDesc(fn ($b) => abs($b[3] - $b[2]))->take(10)
        ->map(fn ($b) => [$b[0], mb_strimwidth($b[1], 0, 45, '...'), number_format($b[2], 0, ',', '.'), number_format($b[3], 0, ',', '.')]);
    $this->table(['Part', 'Deskripsi', 'Harga lama (Rp)', 'Harga baru (Rp)'], $contoh->values());

    if (!$this->option('simpan')) {
        $this->warn('Simulasi saja. Jalankan dengan --simpan untuk menyimpan.');
        return 0;
    }

    DB::transaction(function () use ($berubah) {
        foreach ($berubah as $id => [, , , $baru]) {
            DB::table('inventories')->where('id', $id)->update(['harga_beli_terakhir' => $baru, 'updated_at' => now()]);

            DB::table('inventory_transactions')->where('inventory_id', $id)->update([
                'harga_satuan' => $baru,
                'total_harga'  => DB::raw('qty * ' . $baru),
            ]);

            DB::table('inventory_stock_snapshots')->where('inventory_id', $id)->update([
                'harga_satuan_saat_itu' => $baru,
                'nilai_total_saat_itu'  => DB::raw('stock_fisik * ' . $baru),
            ]);
        }
    });

    $sesudah = $ringkas();
    $this->table(['Ringkasan', 'Sebelum (Rp)', 'Sesudah (Rp)'], [
        ['Total nilai aset', number_format($sebelum['aset'], 0, ',', '.'), number_format($sesudah['aset'], 0, ',', '.')],
        ['Total biaya OUT', number_format($sebelum['biaya_out'], 0, ',', '.'), number_format($sesudah['biaya_out'], 0, ',', '.')],
    ]);
    $this->info('Harga tersimpan.');
    return 0;
})->purpose('Perbarui harga part & nilai transaksi dari kolom IDR sheet master ERP');

/*
| Isi area rak (deskripsi bin ERP, mis. "Bearings", "Tooling Room") dari file
| "Part Quantity Results". Dicocokkan per bin; bin yang tidak ada di file memakai
| area terbanyak di rak yang sama.
|   php artisan sparepart:sinkron-area "path\Part Quantity Results 20260721.xlsx" [--simpan]
*/
Artisan::command('sparepart:sinkron-area {file} {--simpan}', function (string $file) {
    if (!is_file($file)) {
        $this->error("File tidak ditemukan: $file");
        return 1;
    }

    $reader = PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $rows = $reader->load($file)->getActiveSheet()->toArray(null, true, false, true);

    $areaBin = [];
    $areaRak = [];
    foreach ($rows as $i => $r) {
        $bin = trim((string) ($r['I'] ?? ''));
        if ($i === 1 || $bin === '') {
            continue;
        }
        $area = trim(substr((string) $r['J'], strlen($bin)));
        if ($area === '') {
            continue;
        }
        $areaBin[$bin] = $area;
        $rak = App\Models\Inventory::uraiLokasi($bin)['kode_rak'];
        $areaRak[$rak][$area] = ($areaRak[$rak][$area] ?? 0) + 1;
    }
    foreach ($areaRak as $rak => $hitung) {
        arsort($hitung);
        $areaRak[$rak] = array_key_first($hitung);
    }

    $hasil = ['bin persis' => 0, 'dari rak' => 0, 'tidak ketemu' => 0];
    $update = [];
    foreach (App\Models\Inventory::get(['id', 'lokasi_rak']) as $inv) {
        $bin = trim((string) $inv->lokasi_rak);
        if (isset($areaBin[$bin])) {
            $update[$areaBin[$bin]][] = $inv->id;
            $hasil['bin persis']++;
        } elseif (($rak = App\Models\Inventory::uraiLokasi($bin)['kode_rak']) && isset($areaRak[$rak])) {
            $update[$areaRak[$rak]][] = $inv->id;
            $hasil['dari rak']++;
        } else {
            $hasil['tidak ketemu']++;
        }
    }

    $this->table(['Cara cocok', 'Jumlah part'], collect($hasil)->map(fn ($n, $k) => [$k, $n])->values());

    if (!$this->option('simpan')) {
        $this->warn('Simulasi saja. Jalankan dengan --simpan untuk menyimpan.');
        return 0;
    }

    foreach ($update as $area => $ids) {
        foreach (array_chunk($ids, 500) as $potongan) {
            App\Models\Inventory::whereIn('id', $potongan)->update(['area_rak' => $area]);
        }
    }
    $this->info('Area rak tersimpan.');
    return 0;
})->purpose('Isi area rak dari deskripsi bin file Part Quantity Results ERP');
