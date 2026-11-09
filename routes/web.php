<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\PengembalianController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalitikController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\RekonsiliasiController;
use App\Http\Controllers\MesinController;
use App\Http\Controllers\MesinBiayaController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\PenerimaanController;
use Illuminate\Support\Facades\Route;

// Alamat utama: sudah login -> dashboard sesuai peran, belum -> halaman login.
// (Versi lama me-logout siapa pun yang membuka "/", sehingga bookmark/alamat utama
// tanpa sengaja mengeluarkan admin yang sedang bekerja. Keluar cukup lewat tombol Keluar.)
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Ganti bahasa tampilan (English default / Bahasa Indonesia). Disimpan di session + cookie 1 tahun.
Route::get('/bahasa/{locale}', function (string $locale) {
    abort_unless(in_array($locale, \App\Http\Middleware\SetLocale::DIDUKUNG, true), 404);
    session(['locale' => $locale]);

    return back()->withCookie(cookie()->forever('locale', $locale));
})->name('bahasa');

// Redirect otomatis saat user/admin berhasil login
Route::get('/dashboard', function () {
    if (in_array(auth()->user()->role, ['admin', 'viewer'], true)) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('user.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');


// =================================================================
// 1. LIHAT BARANG (semua yang login, termasuk viewer)
// =================================================================
Route::middleware(['auth'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/suggest', [InventoryController::class, 'suggest'])->name('inventory.suggest');
    Route::get('/inventory/katalog', [InventoryController::class, 'katalog'])->name('inventory.katalog');
});


// =================================================================
// 1b. AMBIL & KEMBALIKAN BARANG (admin & kiosk teknisi; viewer tidak)
// =================================================================
Route::middleware(['auth', 'role:admin,user'])->group(function () {

    // Inventory
    // Teknisi login dengan nama + password di halaman login. Sesi berakhir -> akun kiosk keluar ke halaman login.
    Route::get('/inventory/pilih-peminjam', [InventoryController::class, 'pilihPeminjam'])->name('inventory.pilih-peminjam');
    Route::get('/inventory/ganti-peminjam', [InventoryController::class, 'gantiPeminjam'])->name('inventory.ganti-peminjam');
    Route::post('/inventory/pola', [InventoryController::class, 'aturPola'])->name('inventory.pola');

    // PAGE 2: pencarian barang (tetap 1 menu "Inventory")
    Route::post('/inventory/pinjam', [InventoryController::class, 'pinjam'])->name('inventory.pinjam');

    // Keranjang — fitur baru, ambil banyak barang sekaligus
    Route::post('/inventory/keranjang/tambah', [InventoryController::class, 'tambahKeranjang'])->name('inventory.keranjang.tambah');
    Route::post('/inventory/keranjang/hapus/{index}', [InventoryController::class, 'hapusKeranjang'])->name('inventory.keranjang.hapus');
    Route::post('/inventory/keranjang/kurangi/{index}', [InventoryController::class, 'kurangiKeranjang'])->name('inventory.keranjang.kurangi');
    Route::post('/inventory/keranjang/tambah-satu/{index}', [InventoryController::class, 'tambahSatuKeranjang'])->name('inventory.keranjang.tambah-satu');
    Route::post('/inventory/keranjang/kosongkan', [InventoryController::class, 'kosongkanKeranjang'])->name('inventory.keranjang.kosongkan');
    Route::post('/inventory/keranjang/urung', [InventoryController::class, 'urungKeranjang'])->name('inventory.keranjang.urung');
    Route::post('/inventory/keranjang/scan', [InventoryController::class, 'scanKeranjang'])->name('inventory.keranjang.scan');
    Route::post('/inventory/ambil-semua', [InventoryController::class, 'ambilSemua'])->name('inventory.ambil-semua');

    // Pengembalian
    Route::get('/pengembalian', [PengembalianController::class, 'index'])->name('pengembalian.index');
    Route::get('/pengembalian/export', [PengembalianController::class, 'ekspor'])->name('pengembalian.export');
    Route::post('/pengembalian/{history}', [PengembalianController::class, 'kembalikan'])->name('pengembalian.kembalikan');

    // Halaman profil bawaan Breeze (ubah email/password, hapus akun) sengaja TIDAK diaktifkan:
    // akun admin & kiosk bersifat tetap — lihat routes/auth.php.
});


// =================================================================
// 2. LAPORAN & ANALITIK — admin + viewer (Presdir / manager, hanya melihat)
// =================================================================
Route::middleware(['auth', 'role:admin,viewer'])->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/analitik', [AnalitikController::class, 'index'])->name('analitik.index');
    Route::get('/analitik/laporan-bulanan', [AnalitikController::class, 'laporanBulanan'])->name('analitik.laporan');
    Route::get('/restock', [DashboardController::class, 'restock'])->name('restock.index');
    Route::get('/restock/purchase-request', [DashboardController::class, 'exportRestock'])->name('restock.export');
    Route::get('/mesin/biaya', [MesinBiayaController::class, 'index'])->name('mesin.biaya');
    Route::get('/mesin/detail', [MesinBiayaController::class, 'detail'])->name('mesin.detail');
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('/history/suggest', [HistoryController::class, 'suggest'])->name('history.suggest');
    Route::get('/history/export-pdf', [HistoryController::class, 'exportPdf'])->name('history.export-pdf');
    Route::get('/history/export-csv', [HistoryController::class, 'exportCsv'])->name('history.export-csv');
    Route::get('/history/export-xlsx', [HistoryController::class, 'exportXlsx'])->name('history.export-xlsx');
    Route::get('/history/mesin', [HistoryController::class, 'daftarMesin'])->name('history.mesin');
    Route::get('/history/operator', [HistoryController::class, 'daftarOperator'])->name('history.operator');
    Route::get('/rekonsiliasi', [RekonsiliasiController::class, 'index'])->name('rekonsiliasi.index');
    Route::get('/opname', [StockOpnameController::class, 'index'])->name('opname.index');
    Route::get('/opname/{opname}/tinjau', [StockOpnameController::class, 'tinjau'])->name('opname.tinjau');
});


// =================================================================
// 3. FITUR KHUSUS ADMIN — semua yang mengubah data
// =================================================================
Route::middleware(['auth', 'role:admin'])->group(function () {

    // Kelola master part (kategori, kata kunci, foto) — khusus admin
    Route::get('/inventory/kelola', [InventoryController::class, 'kelola'])->name('inventory.kelola');
    Route::get('/inventory/{inventory}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');

    // Registrasi karyawan (nomor kartu) — khusus admin
    Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
    Route::post('/karyawan', [KaryawanController::class, 'store'])->name('karyawan.store');
    Route::post('/karyawan/import', [KaryawanController::class, 'import'])->name('karyawan.import');
    Route::put('/karyawan/{karyawan}', [KaryawanController::class, 'update'])->name('karyawan.update');
    Route::post('/karyawan/{karyawan}/toggle', [KaryawanController::class, 'toggle'])->name('karyawan.toggle');
    Route::post('/karyawan/pin-massal', [KaryawanController::class, 'pinMassal'])->name('karyawan.pin-massal');
    Route::post('/karyawan/{karyawan}/pin', [KaryawanController::class, 'aturPin'])->name('karyawan.pin');
    Route::post('/karyawan/{karyawan}/buka-kunci', [KaryawanController::class, 'bukaKunci'])->name('karyawan.buka-kunci');

    // Master mesin & padanan Job PM -> mesin — khusus admin
    Route::get('/mesin', [MesinController::class, 'index'])->name('mesin.index');
    Route::post('/mesin/petakan', [MesinController::class, 'petakan'])->name('mesin.petakan');
    Route::post('/mesin/hapus-padanan', [MesinController::class, 'hapusPadanan'])->name('mesin.hapus-padanan');
    Route::post('/mesin/import', [MesinController::class, 'import'])->name('mesin.import');
    Route::post('/mesin', [MesinController::class, 'tambahMesin'])->name('mesin.tambah');
    Route::delete('/mesin/{mesin}', [MesinController::class, 'hapusMesin'])->name('mesin.hapus');

    // History — khusus admin


    // Stock opname per rak (hitung fisik lewat HP) — khusus admin
    Route::post('/opname', [StockOpnameController::class, 'mulai'])->name('opname.mulai');
    Route::get('/opname/{opname}', [StockOpnameController::class, 'hitung'])->name('opname.hitung');
    Route::post('/opname/{opname}/item/{item}', [StockOpnameController::class, 'simpan'])->name('opname.simpan');
    Route::post('/opname/{opname}/tambah', [StockOpnameController::class, 'tambah'])->name('opname.tambah');
    Route::post('/opname/{opname}/posting', [StockOpnameController::class, 'posting'])->name('opname.posting');
    Route::post('/opname/{opname}/batal', [StockOpnameController::class, 'batal'])->name('opname.batal');

    // Purchase Request dilacak sampai barang diterima — khusus admin
    Route::get('/pr', [PurchaseRequestController::class, 'index'])->name('pr.index');
    Route::post('/pr', [PurchaseRequestController::class, 'store'])->name('pr.store');
    Route::get('/pr/{pr}', [PurchaseRequestController::class, 'show'])->name('pr.show');
    Route::post('/pr/{pr}/po', [PurchaseRequestController::class, 'po'])->name('pr.po');
    Route::post('/pr/{pr}/batal', [PurchaseRequestController::class, 'batal'])->name('pr.batal');
    Route::get('/pr/{pr}/excel', [PurchaseRequestController::class, 'excel'])->name('pr.excel');
    Route::get('/pr/{pr}/sisa', [PurchaseRequestController::class, 'sisa'])->name('pr.sisa');

    // Persetujuan pengambilan bernilai besar — hanya Kepala Spare Part (admin)
    Route::get('/persetujuan', [PersetujuanController::class, 'index'])->name('persetujuan.index');
    Route::post('/persetujuan/pengaturan', [PersetujuanController::class, 'pengaturan'])->name('persetujuan.pengaturan');
    Route::post('/persetujuan/{permintaan}/setujui', [PersetujuanController::class, 'setujui'])->name('persetujuan.setujui');
    Route::post('/persetujuan/{permintaan}/tolak', [PersetujuanController::class, 'tolak'])->name('persetujuan.tolak');

    // Penerimaan barang (barang datang -> stok langsung bertambah) — khusus admin
    Route::get('/penerimaan', [PenerimaanController::class, 'index'])->name('penerimaan.index');
    Route::get('/penerimaan/cari', [PenerimaanController::class, 'cari'])->name('penerimaan.cari');
    Route::post('/penerimaan', [PenerimaanController::class, 'simpan'])->name('penerimaan.simpan');
    Route::get('/penerimaan/{penerimaan}', [PenerimaanController::class, 'show'])->name('penerimaan.show');
    Route::post('/penerimaan/{penerimaan}/batal', [PenerimaanController::class, 'batal'])->name('penerimaan.batal');

    // Backup database + foto — khusus admin
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup', [BackupController::class, 'jalankan'])->name('backup.jalankan');
    Route::get('/backup/unduh/{nama}', [BackupController::class, 'unduh'])->name('backup.unduh');

    // Cetak label barcode laci / part untuk ditempel di rak — khusus admin
    Route::get('/label', [LabelController::class, 'index'])->name('label.index');
    Route::get('/label/cetak', [LabelController::class, 'cetak'])->name('label.cetak');
    Route::post('/label/tandai', [LabelController::class, 'tandai'])->name('label.tandai');

    // Rekonsiliasi scan kiosk vs ERP — khusus admin
    Route::post('/rekonsiliasi/jalankan', [RekonsiliasiController::class, 'jalankan'])->name('rekonsiliasi.jalankan');
    Route::post('/rekonsiliasi/pasangkan', [RekonsiliasiController::class, 'pasangkan'])->name('rekonsiliasi.pasangkan');
    Route::post('/rekonsiliasi/{history}/lepas', [RekonsiliasiController::class, 'lepas'])->name('rekonsiliasi.lepas');
    Route::post('/rekonsiliasi/{history}/abaikan', [RekonsiliasiController::class, 'abaikan'])->name('rekonsiliasi.abaikan');
});


// =================================================================
// 3. DASHBOARD USER BIASA
// =================================================================
Route::middleware(['auth', 'role:user'])->group(function () {
    // Akun "user" = kiosk: teknisi yang login dengan namanya langsung ke Parts Issuance.
    Route::get('/user/dashboard', function () {
        return redirect()->route('inventory.pilih-peminjam');
    })->name('user.dashboard');
});

// Import Excel ERP — admin, dan manager yang diberi hak upload (lihat Gate "impor")
Route::middleware(['auth', 'can:impor'])->group(function () {
    Route::get('/import', [ImportController::class, 'index'])->name('import.index');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    Route::post('/import-stok', [ImportController::class, 'storeStockSnapshot'])->name('import.stok');
});

require __DIR__.'/auth.php';
// Alamat tak dikenal tetap lewat grup "web" supaya halaman 404 memakai bahasa pilihan pengguna.
Route::fallback(fn () => abort(404));
