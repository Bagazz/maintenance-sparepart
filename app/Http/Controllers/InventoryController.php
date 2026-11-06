<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\HistoryLog;
use App\Models\Karyawan;
use App\Models\Mesin;
use App\Models\PermintaanAmbil;
use App\Services\PengambilanService;
use App\Services\PersetujuanService;

class InventoryController extends Controller
{
    /** Sesi teknisi di kiosk berakhir otomatis bila tidak ada aktivitas selama ini. */
    private const BATAS_SESI_MENIT = 10;

    private const SESI = ['karyawan_id', 'peminjam_nama', 'karyawan_sejak'];


    /**
     * Teknisi yang sedang memakai kiosk (hasil scan kartu), atau null.
     * Sesi yang sudah lewat batas waktu dibersihkan, supaya orang berikutnya
     * tidak tercatat atas nama teknisi sebelumnya.
     */
    private function karyawanSesi(): ?Karyawan
    {
        $id = session('karyawan_id');
        $sejak = session('karyawan_sejak');

        if (!$id || !$sejak || now()->diffInMinutes(\Illuminate\Support\Carbon::parse($sejak)) >= self::BATAS_SESI_MENIT) {
            session()->forget([...self::SESI, 'keranjang']);
            return null;
        }

        $karyawan = Karyawan::where('id', $id)->where('aktif', true)->first();
        if (!$karyawan) {
            session()->forget([...self::SESI, 'keranjang']);
            return null;
        }

        session(['karyawan_sejak' => now()->toIso8601String()]);

        return $karyawan;
    }

    private function isAdmin(): bool
    {
        return auth()->user()->role === 'admin';
    }

    /**
     * Teknisi masuk lewat halaman login (nama karyawan + password), bukan lewat kartu.
     * Rute ini dipakai bila sesi teknisi berakhir (selesai, ganti orang, atau 10 menit tanpa aktivitas):
     * akun kiosk ikut keluar supaya orang berikutnya wajib login dengan namanya sendiri.
     */
    public function pilihPeminjam(Request $request)
    {
        if ($this->isAdmin() || $this->karyawanSesi()) {
            return redirect()->route('inventory.index');
        }

        return $this->keluarKiosk($request, session('error') ?? __('Session ended. Sign in with your name and password.'), session()->has('error') ? 'error' : 'status');
    }

    /** Teknisi memastikan / mengganti shift-nya minggu ini (Kepala Spare Part tetap bisa mengatur di menu Karyawan). */
    public function aturShift(Request $request)
    {
        $karyawan = $this->karyawanSesi();
        if (!$karyawan) {
            return redirect()->route('inventory.pilih-peminjam');
        }

        $data = $request->validate(['shift' => ['required', \Illuminate\Validation\Rule::in(Karyawan::SHIFT)]]);
        $karyawan->aturShift($data['shift']);

        return back()->with('success', __('Your shift this week: :shift.', ['shift' => $data['shift']]));
    }

    public function gantiPeminjam(Request $request)
    {
        return $this->keluarKiosk($request, __('Signed out. The next technician can sign in with their own name and password.'));
    }

    /** Keluarkan akun kiosk sepenuhnya lalu kembali ke halaman login dengan pesan. */
    private function keluarKiosk(Request $request, string $pesan, string $jenis = 'status')
    {
        if ($this->isAdmin()) {
            session()->forget([...self::SESI, 'keranjang']);
            return redirect()->route('inventory.index');
        }

        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with($jenis, $pesan);
    }

    public function index(Request $request)
    {
        $isAdmin = $this->isAdmin();
        $karyawan = $isAdmin ? null : $this->karyawanSesi();

        // Kiosk wajib scan kartu dulu; viewer (Presdir / manager) boleh melihat tanpa form pengambilan.
        if (auth()->user()->role === 'user' && !$karyawan) {
            return redirect()->route('inventory.pilih-peminjam');
        }

        $item = null;
        $hasil = collect();

        if ($request->filled('q')) {
            $q = trim($request->q);

            // Part no persis (hasil scan / klik saran) langsung ke detail.
            $item = Inventory::where('part_no', $q)->first();

            // Scan label laci (kode bin persis, mis. M03-B5D1): hanya isi laci itu,
            // bukan M03-B5D10..D19 yang ikut cocok bila dicari dengan LIKE.
            if (!$item && preg_match(LabelController::POLA_BIN, strtoupper($q))) {
                $hasil = Inventory::where('lokasi_rak', strtoupper($q))->orderBy('deskripsi')->limit(48)->get();
                if ($hasil->count() === 1) {
                    $item = $hasil->first();
                    $hasil = collect();
                }
            }

            if (!$item && $hasil->isEmpty()) {
                $hasil = Inventory::cari($q)->urutRelevan($q)->limit(48)->get();

                if ($hasil->count() === 1) {
                    $item = $hasil->first();
                    $hasil = collect();
                }
            }
        }

        // Hasil scan (atau ketik part no persis + Enter) langsung masuk keranjang, seperti toko:
        // tidak perlu membuka detail barang lalu menekan "Tambah ke keranjang".
        if ($item && $request->boolean('scan') && Gate::allows('ambil')) {
            $galat = $this->masukkanKeranjang($item, 1);

            return $galat
                ? redirect()->route('inventory.index', ['q' => $item->part_no])->with('error', $galat)
                : redirect()->route('inventory.index')
                    ->with('success', __('":item" added to the cart. Scan the next item, or confirm the cart.', ['item' => $item->deskripsi]))
                    ->with('baru_masuk', $item->part_no);
        }

        // Daftar mesin untuk dropdown di form pengambilan
        $daftarMesin = Mesin::orderBy('nama')->pluck('nama');

        // Isi keranjang yang sedang berjalan
        $keranjang = session('keranjang', []);

        $permintaanSaya = $karyawan
            ? PermintaanAmbil::with('items')->where('karyawan_id', $karyawan->id)->where('created_at', '>=', now()->subDays(7))->latest('id')->limit(5)->get()
            : collect();

        return view('inventory.index', compact('item', 'hasil', 'daftarMesin', 'keranjang', 'karyawan', 'permintaanSaya'));
    }

    public function suggest(Request $request)
    {
        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $results = Inventory::cari($q)
            ->urutRelevan($q)
            ->limit(15)
            ->get(['part_no', 'deskripsi', 'kategori', 'lokasi_rak', 'stock', 'foto_barang'])
            ->map(fn ($i) => [
                'part_no'    => $i->part_no,
                'deskripsi'  => $i->deskripsi,
                'kategori'   => $i->kategori,
                'lokasi_rak' => $i->lokasi_rak,
                'stock'      => $i->stock,
                'foto'       => $i->foto_barang_url,
            ]);

        return response()->json($results);
    }

    /**
     * Katalog foto: untuk teknisi yang lupa nama barang tapi ingat bentuknya.
     * Tanpa filter -> daftar kategori. Dengan kategori/q -> grid barang.
     */
    public function katalog(Request $request)
    {
        $kategori = $request->get('kategori');
        $q = trim((string) $request->get('q', ''));
        $hanyaFoto = $request->boolean('foto');

        $daftarKategori = Inventory::whereNotNull('kategori')
            ->select('kategori', DB::raw('COUNT(*) as jumlah'), DB::raw('MAX(foto_barang) as cover'))
            ->groupBy('kategori')
            ->orderBy('kategori')
            ->get();

        $tanpaKategori = Inventory::whereNull('kategori')->count();

        $barang = null;
        if ($kategori || $q !== '' || $hanyaFoto) {
            $barang = Inventory::query()
                ->when($kategori === '__kosong', fn ($qr) => $qr->whereNull('kategori'))
                ->when($kategori && $kategori !== '__kosong', fn ($qr) => $qr->where('kategori', $kategori))
                ->when($q !== '', fn ($qr) => $qr->cari($q))
                ->when($hanyaFoto, fn ($qr) => $qr->whereNotNull('foto_barang'))
                ->orderByRaw('foto_barang IS NULL')
                ->orderBy('deskripsi')
                ->paginate(24)
                ->withQueryString();
        }

        return view('inventory.katalog', compact('daftarKategori', 'tanpaKategori', 'barang', 'kategori', 'q', 'hanyaFoto'));
    }

    // ============================================================
    // KELOLA MASTER PART (ADMIN) — kategori, kata kunci, foto
    // ============================================================

    /**
     * Daftar part untuk dilengkapi. Diurutkan dari yang paling sering keluar
     * 12 bulan terakhir, supaya foto diprioritaskan untuk part yang paling sering dicari.
     */
    public function kelola(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $filter = $request->get('filter', 'tanpa_foto');

        $pemakaian = DB::table('inventory_transactions')
            ->where('type', 'OUT')
            ->whereRaw('COALESCE(tanggal_transaksi, DATE(created_at)) >= ?', [now()->subYear()->toDateString()])
            ->select('inventory_id', DB::raw('SUM(qty) as qty_keluar'), DB::raw('COUNT(*) as frekuensi'))
            ->groupBy('inventory_id');

        $barang = Inventory::query()
            ->leftJoinSub($pemakaian, 'pakai', 'pakai.inventory_id', '=', 'inventories.id')
            ->select('inventories.*', DB::raw('COALESCE(pakai.qty_keluar, 0) as qty_keluar'), DB::raw('COALESCE(pakai.frekuensi, 0) as frekuensi'))
            ->when($q !== '', fn ($qr) => $qr->cari($q))
            ->when($filter === 'tanpa_foto', fn ($qr) => $qr->whereNull('foto_barang'))
            ->when($filter === 'baru_tanpa_foto', fn ($qr) => $qr->baruTanpaFoto()->orderByDesc('inventories.created_at'))
            ->when($filter === 'tanpa_kategori', fn ($qr) => $qr->whereNull('kategori'))
            ->orderByDesc('frekuensi')
            ->orderByDesc('qty_keluar')
            ->orderBy('deskripsi')
            ->paginate(30)
            ->withQueryString();

        $statistik = [
            'total'          => Inventory::count(),
            'ada_foto'       => Inventory::whereNotNull('foto_barang')->count(),
            'ada_kategori'   => Inventory::whereNotNull('kategori')->count(),
            'ada_kata_kunci' => Inventory::whereNotNull('kata_kunci')->count(),
        ];

        return view('inventory.kelola', compact('barang', 'q', 'filter', 'statistik'));
    }

    public function edit(Inventory $inventory)
    {
        $daftarKategori = Inventory::whereNotNull('kategori')->distinct()->orderBy('kategori')->pluck('kategori');

        return view('inventory.edit', ['item' => $inventory, 'daftarKategori' => $daftarKategori]);
    }

    public function update(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
            'kategori'          => 'nullable|string|max:100',
            'kata_kunci'        => 'nullable|string|max:500',
            'foto_barang'       => 'nullable|image|max:8192',
            'foto_lokasi'       => 'nullable|image|max:8192',
            'hapus_foto_barang' => 'nullable|boolean',
            'hapus_foto_lokasi' => 'nullable|boolean',
        ]);

        $inventory->kategori = isset($data['kategori']) ? mb_convert_case(trim($data['kategori']), MB_CASE_TITLE) : null;
        $inventory->kata_kunci = isset($data['kata_kunci']) ? trim($data['kata_kunci']) : null;

        foreach (['foto_barang' => 'sparepart/barang', 'foto_lokasi' => 'sparepart/lokasi'] as $kolom => $folder) {
            $hapus = $request->boolean('hapus_' . $kolom);

            if (($hapus || $request->hasFile($kolom)) && $inventory->$kolom) {
                Storage::disk('public')->delete($inventory->$kolom);
                $inventory->$kolom = null;
            }

            if ($request->hasFile($kolom)) {
                $inventory->$kolom = $request->file($kolom)->store($folder, 'public');
            }
        }

        $inventory->save();

        $kembali = $request->input('kembali');

        return redirect()->to($kembali && str_starts_with($kembali, url('/')) ? $kembali : route('inventory.kelola'))
            ->with('success', __('Data for ":item" saved.', ['item' => $inventory->deskripsi]));
    }

    // ============================================================
    // PENGAMBILAN BARANG
    // ============================================================

    /**
     * Satu-satunya jalur stok keluar dari aplikasi. Dalam satu DB transaction:
     * kunci baris stok (cegah 2 orang ambil bersamaan), kurangi stok, catat ke
     * history_logs (siapa/kapan) DAN ke inventory_transactions sebagai OUT
     * (supaya masuk perhitungan biaya per mesin di dashboard).
     *
     * @throws \RuntimeException kalau stok tidak cukup
     */
    private function catatPengambilan(string $partNo, int $jumlah, string $mesin, ?Karyawan $karyawan, ?string $noPm = null): Inventory
    {
        return app(PengambilanService::class)->catat($partNo, $jumlah, $mesin, $karyawan, $noPm);
    }

    public function pinjam(Request $request)
    {
        $isAdmin = $this->isAdmin();
        $karyawan = $isAdmin ? null : $this->karyawanSesi();

        if (!$isAdmin && !$karyawan) {
            return redirect()->route('inventory.pilih-peminjam')->with('error', __('Session ended. Sign in with your name and password.'));
        }

        // Sama seperti konfirmasi keranjang: shift minggu ini wajib dipastikan dulu.
        if ($karyawan && !$karyawan->shift_minggu_ini) {
            return back()->with('error', __('Confirm your shift for this week first (at the top of the page).'))->withInput();
        }

        $request->validate([
            'part_no' => 'required|exists:inventories,part_no',
            'jumlah'  => 'required|integer|min:1',
            'mesin'   => 'required|string|max:255',
            'no_pm'   => 'nullable|string|max:50',
        ]);

        try {
            $item = $this->catatPengambilan($request->part_no, (int) $request->jumlah, $request->mesin, $karyawan, $request->no_pm);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage() . '.')->withInput();
        }

        $nama = $karyawan?->nama ?? auth()->user()->name;
        $pesan = __('Take-out of :n ":item" by :nama for machine :mesin recorded. Remaining stock: :sisa.', ['n' => $request->jumlah, 'item' => $item->deskripsi, 'nama' => $nama, 'mesin' => $request->mesin, 'sisa' => $item->stock]);

        if (!$isAdmin) {
            session()->forget([...self::SESI, 'keranjang']);

            return redirect()->route('inventory.pilih-peminjam')
                ->with('success', $pesan);
        }

        return redirect()->route('inventory.index', ['q' => $item->part_no])
            ->with('success', $pesan);
    }

    // ============================================================
    // FITUR KERANJANG — ambil beberapa barang sekaligus
    // ============================================================

    public function tambahKeranjang(Request $request)
    {
        $request->validate([
            'part_no'   => 'required|exists:inventories,part_no',
            'jumlah'    => 'required|integer|min:1',
            'mesin'     => 'nullable|string|max:255',
            'no_pm'     => 'nullable|string|max:50',
        ]);

        if (!$this->isAdmin() && !$this->karyawanSesi()) {
            return redirect()->route('inventory.pilih-peminjam')->with('error', __('Session ended. Sign in with your name and password.'));
        }

        $item = Inventory::where('part_no', $request->part_no)->firstOrFail();

        if ($galat = $this->masukkanKeranjang($item, (int) $request->jumlah, $request->mesin, $request->no_pm)) {
            return back()->with('error', $galat)->withInput();
        }

        return redirect()->route('inventory.index')
            ->with('success', __('":item" x:n added to the cart. Find another item or press Confirm.', ['item' => $item->deskripsi, 'n' => $request->jumlah]))
            ->with('baru_masuk', $item->part_no);
    }

    /**
     * Masukkan barang ke keranjang sesi. Barang yang sama (mesin & PM sama) digabung jadi satu baris.
     * Mengembalikan pesan galat bila stok tidak cukup, atau null bila berhasil.
     */
    private function masukkanKeranjang(Inventory $item, int $jumlah, ?string $mesin = null, ?string $noPm = null): ?string
    {
        $keranjang = session('keranjang', []);
        $mesin = $mesin !== null && trim($mesin) !== '' ? trim($mesin) : null;
        $noPm = $noPm !== null && trim($noPm) !== '' ? strtoupper(trim($noPm)) : null;

        if ($item->stock <= 0) {
            return __('":item" is out of stock — report to the spare part head for restock.', ['item' => $item->deskripsi]);
        }

        $tersedia = PengambilanService::tersedia($item);
        $sudahDiKeranjang = collect($keranjang)->where('part_no', $item->part_no)->sum('jumlah');
        if (($sudahDiKeranjang + $jumlah) > $tersedia) {
            return __('Total cart qty for ":item" exceeds the available stock (:sisa left).', ['item' => $item->deskripsi, 'sisa' => $tersedia]);
        }

        foreach ($keranjang as $i => $baris) {
            if ($baris['part_no'] === $item->part_no && ($baris['mesin'] ?? null) === $mesin && ($baris['no_pm'] ?? null) === $noPm) {
                $keranjang[$i]['jumlah'] += $jumlah;
                session(['keranjang' => $keranjang]);
                return null;
            }
        }

        $keranjang[] = [
            'part_no'   => $item->part_no,
            'deskripsi' => $item->deskripsi,
            'lokasi'    => $item->lokasi_rak,
            'harga'     => (float) $item->harga_beli_terakhir,
            'mesin'     => $mesin,
            'no_pm'     => $noPm,
            'jumlah'    => $jumlah,
        ];
        session(['keranjang' => $keranjang]);

        return null;
    }

    /**
     * Scan di halaman Parts Issuance (tanpa memuat ulang halaman). Hasil:
     *  - masuk : kode cocok persis -> masuk keranjang (barang sama digabung, qty +1)
     *  - galat : kode cocok tapi stok tidak cukup
     *  - kosong: kode tidak dikenal sama sekali (salah scan / label belum terdaftar)
     *  - cari  : kata bebas yang punya hasil -> halaman memuat daftar hasil pencarian
     */
    public function scanKeranjang(Request $request)
    {
        $request->validate(['kode' => 'required|string|max:100']);

        if (!$this->isAdmin() && !$this->karyawanSesi()) {
            return response()->json(['status' => 'sesi', 'url' => route('inventory.pilih-peminjam')], 401);
        }

        $kode = trim($request->kode);
        $item = $this->cariPersis($kode);

        if (!$item) {
            $adaHasil = Inventory::cari($kode)->exists()
                || (preg_match(LabelController::POLA_BIN, strtoupper($kode)) && Inventory::where('lokasi_rak', strtoupper($kode))->exists());

            return $adaHasil
                ? response()->json(['status' => 'cari', 'url' => route('inventory.index', ['q' => $kode])])
                : $this->responsKeranjang(__('Barcode ":kode" is not registered in the parts master.', ['kode' => $kode]), 'galat', null, 'kosong');
        }

        if ($galat = $this->masukkanKeranjang($item, 1)) {
            return $this->responsKeranjang($galat, 'galat', $item->part_no, 'galat');
        }

        $qty = collect(session('keranjang', []))->where('part_no', $item->part_no)->sum('jumlah');
        $pesan = $qty > 1
            ? __('":item" scanned again — quantity is now :n.', ['item' => $item->deskripsi, 'n' => $qty])
            : __('":item" added to the cart.', ['item' => $item->deskripsi]);

        return $this->responsKeranjang($pesan, 'sukses', $item->part_no, 'masuk');
    }

    /** Part no persis, atau kode laci yang isinya tepat satu barang. */
    private function cariPersis(string $kode): ?Inventory
    {
        if ($item = Inventory::where('part_no', $kode)->first()) {
            return $item;
        }

        if (preg_match(LabelController::POLA_BIN, strtoupper($kode))) {
            $isi = Inventory::where('lokasi_rak', strtoupper($kode))->limit(2)->get();
            return $isi->count() === 1 ? $isi->first() : null;
        }

        return null;
    }

    /**
     * Balasan aksi keranjang: JSON berisi panel keranjang terbaru (dipakai halaman tanpa reload),
     * atau redirect biasa bila dipanggil dari form tanpa JavaScript.
     */
    private function responsKeranjang(?string $pesan, string $jenis = 'sukses', ?string $baru = null, ?string $status = null, bool $bisaUrung = false)
    {
        if (!request()->expectsJson()) {
            return $pesan ? back()->with($jenis === 'galat' ? 'error' : 'success', $pesan) : back();
        }

        $keranjang = session('keranjang', []);

        return response()->json([
            'status' => $status ?? 'ok',
            'jenis'  => $jenis,
            'pesan'  => $pesan,
            'urung'  => $bisaUrung,
            'baris'  => count($keranjang),
            'pcs'    => collect($keranjang)->sum('jumlah'),
            'html'   => view('inventory._keranjang', [
                'keranjang'   => $keranjang,
                'daftarMesin' => Mesin::orderBy('nama')->pluck('nama'),
                'karyawan'    => $this->isAdmin() ? null : $this->karyawanSesi(),
                'baruMasuk'   => $baru,
            ])->render(),
        ]);
    }

    /** Simpan isi keranjang sebelum dihapus, supaya bisa dibatalkan ("Urungkan"). */
    private function simpanUntukUrung(array $keranjang): void
    {
        session(['keranjang_sebelum' => $keranjang]);
    }

    /** Tombol "+" di keranjang. */
    public function tambahSatuKeranjang($index)
    {
        $keranjang = session('keranjang', []);

        if (isset($keranjang[$index])) {
            $item = Inventory::where('part_no', $keranjang[$index]['part_no'])->first();
            $total = collect($keranjang)->where('part_no', $keranjang[$index]['part_no'])->sum('jumlah');

            if (!$item || $total + 1 > PengambilanService::tersedia($item)) {
                return $this->responsKeranjang(__('Total cart qty for ":item" exceeds the available stock (:sisa left).', ['item' => $keranjang[$index]['deskripsi'], 'sisa' => $item ? PengambilanService::tersedia($item) : 0]), 'galat');
            }

            $keranjang[$index]['jumlah']++;
            session(['keranjang' => $keranjang]);
        }

        return $this->responsKeranjang(null);
    }

    public function kurangiKeranjang($index)
    {
        $keranjang = session('keranjang', []);

        if (isset($keranjang[$index])) {
            if ($keranjang[$index]['jumlah'] <= 1) {
                return $this->hapusKeranjang($index);
            }

            $keranjang[$index]['jumlah']--;
            session(['keranjang' => $keranjang]);
        }

        return $this->responsKeranjang(null);
    }

    public function hapusKeranjang($index)
    {
        $keranjang = session('keranjang', []);

        if (!isset($keranjang[$index])) {
            return $this->responsKeranjang(null);
        }

        $nama = $keranjang[$index]['deskripsi'];
        $this->simpanUntukUrung($keranjang);
        unset($keranjang[$index]);
        session(['keranjang' => array_values($keranjang)]);

        return $this->responsKeranjang(__('":item" removed from the cart.', ['item' => $nama]), 'info', null, null, true);
    }

    /** Hapus semua isi keranjang. */
    public function kosongkanKeranjang()
    {
        $keranjang = session('keranjang', []);

        if ($keranjang) {
            $this->simpanUntukUrung($keranjang);
        }
        session()->forget('keranjang');

        if (!request()->expectsJson()) {
            return redirect()->route('inventory.index')->with('success', __('The cart has been emptied.'));
        }

        return $this->responsKeranjang(__('The cart has been emptied.'), 'info', null, null, (bool) $keranjang);
    }

    /** Batalkan hapus / kosongkan terakhir. */
    public function urungKeranjang()
    {
        if (session()->has('keranjang_sebelum')) {
            session(['keranjang' => session()->pull('keranjang_sebelum')]);
            return $this->responsKeranjang(__('The cart has been restored.'));
        }

        return $this->responsKeranjang(null);
    }

    public function ambilSemua(Request $request)
    {
        $isAdmin = $this->isAdmin();
        $karyawan = $isAdmin ? null : $this->karyawanSesi();

        if (!$isAdmin && !$karyawan) {
            return redirect()->route('inventory.pilih-peminjam')->with('error', __('Session ended before confirmation. Sign in again and repeat.'));
        }

        $keranjang = session('keranjang', []);

        if (empty($keranjang)) {
            return back()->with('error', __('The cart is still empty.'));
        }

        // Shift bergilir tiap minggu: teknisi wajib memastikan shift-nya dulu.
        if ($karyawan && !$karyawan->shift_minggu_ini) {
            return back()->with('error', __('Confirm your shift for this week first (at the top of the page).'));
        }

        $request->validate([
            'mesin' => 'nullable|string|max:255',
            'no_pm' => 'nullable|string|max:50',
        ]);

        // Baris hasil scan belum punya mesin: pakai mesin & PM yang dipilih saat konfirmasi.
        if (collect($keranjang)->contains(fn ($b) => empty($b['mesin'])) && !$request->filled('mesin')) {
            return back()->with('error', __('Choose the target machine before confirming.'));
        }
        foreach ($keranjang as $i => $baris) {
            if (empty($baris['mesin'])) {
                $keranjang[$i]['mesin'] = trim($request->mesin);
                $keranjang[$i]['no_pm'] = $baris['no_pm'] ?? ($request->filled('no_pm') ? strtoupper(trim($request->no_pm)) : null);
            }
        }

        $nama = $karyawan?->nama ?? auth()->user()->name;
        $gagal = [];
        $berhasil = [];

        // Teknisi: baris bernilai besar (aturan di menu Persetujuan) menunggu persetujuan Kepala Spare Part.
        // Admin mengambil sendiri tidak perlu persetujuan.
        $permintaan = null;
        if (!$isAdmin) {
            $aturan = PersetujuanService::aturan();
            $harga = Inventory::whereIn('part_no', collect($keranjang)->pluck('part_no'))->pluck('harga_beli_terakhir', 'part_no');
            $tanda = PersetujuanService::tandaiKeranjang($keranjang, $harga, $aturan);
            [$perlu, $keranjang] = collect($keranjang)->partition(fn ($b, $i) => $tanda[$i])->map(fn ($c) => $c->values()->all())->all();
            if ($perlu) {
                try {
                    $permintaan = app(PersetujuanService::class)->ajukan($perlu, $karyawan);
                } catch (\RuntimeException $e) {
                    $gagal[] = $e->getMessage();
                }
            }
        }

        foreach ($keranjang as $baris) {
            try {
                $this->catatPengambilan($baris['part_no'], (int) $baris['jumlah'], $baris['mesin'], $karyawan, $baris['no_pm'] ?? null);
                $berhasil[] = $baris['deskripsi'] . ' x' . $baris['jumlah'];
            } catch (\RuntimeException $e) {
                $gagal[] = $baris['deskripsi'] . ' (' . $e->getMessage() . ')';
            }
        }

        session()->forget('keranjang');

        $pesan = empty($berhasil)
            ? __('No items were taken.')
            : __(':n items recorded under :nama:', ['n' => count($berhasil), 'nama' => $nama]) . ' ' . implode(', ', $berhasil) . '.';
        if ($permintaan) {
            $pesan = (empty($berhasil) ? '' : $pesan . ' ')
                . __(':n items need approval from the spare part head (request :kode). Wait for approval before taking them; the result appears when you sign in again.', ['n' => $permintaan->items()->count(), 'kode' => $permintaan->kode]);
        }
        if (!empty($gagal)) {
            $pesan .= ' ' . __('Failed:') . ' ' . implode(', ', $gagal) . '.';
        }

        if (!$isAdmin) {
            return $this->keluarKiosk(request(), $pesan, empty($berhasil) && !$permintaan ? 'error' : 'status');
        }

        return redirect()->route('inventory.index')->with('success', $pesan);
    }
}
