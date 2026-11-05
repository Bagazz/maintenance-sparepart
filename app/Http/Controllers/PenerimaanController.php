<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\PenerimaanBarang;
use App\Services\PenerimaanService;
use Illuminate\Http\Request;

/** Penerimaan Barang (khusus admin / kepala spare part): barang datang -> stok langsung bertambah. */
class PenerimaanController extends Controller
{
    public function __construct(private PenerimaanService $penerimaan)
    {
    }

    public function index(Request $request)
    {
        $riwayat = PenerimaanBarang::with('user:id,name')
            ->withCount('baris')
            ->withSum('baris as total_rp', 'total_harga')
            ->withSum('baris as total_qty', 'qty')
            ->latest('id')
            ->paginate(15);

        return view('penerimaan.index', [
            'riwayat'  => $riwayat,
            'pemasok'  => $this->penerimaan->daftarPemasok(),
            'bulanIni' => PenerimaanBarang::where('status', 'aktif')->whereBetween('tanggal', [now()->startOfMonth(), now()])->count(),
            // PR yang barangnya masih ditunggu: bisa dipilih untuk mengisi baris penerimaan.
            'prTerbuka' => \App\Models\PurchaseRequest::terbuka()->withCount('items')->latest('id')->get(),
            'prDipilih' => $request->integer('pr') ?: null,
        ]);
    }

    /** Pencarian barang untuk formulir penerimaan (termasuk harga terakhir — hanya untuk admin). */
    public function cari(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $persis = Inventory::where('part_no', $q)->get();
        $hasil = $persis->isNotEmpty() ? $persis : Inventory::cari($q)->urutRelevan($q)->limit(12)->get();

        return response()->json($hasil->map(fn ($i) => [
            'part_no'   => $i->part_no,
            'deskripsi' => $i->deskripsi,
            'lokasi'    => $i->lokasi_rak,
            'stok'      => (int) $i->stock,
            'harga'     => (float) $i->harga_beli_terakhir,
            'foto'      => $i->foto_barang_url,
            'persis'    => $i->part_no === $q,
        ])->values());
    }

    public function simpan(Request $request)
    {
        $data = $request->validate([
            'tanggal'             => ['required', 'date', 'before_or_equal:today'],
            'pemasok'             => ['nullable', 'string', 'max:150'],
            'no_dokumen'          => ['nullable', 'string', 'max:100'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'catatan'             => ['nullable', 'string', 'max:500'],
            'baris'               => ['required', 'array', 'min:1', 'max:200'],
            'baris.*.part_no'     => ['required', 'string', 'max:100'],
            'baris.*.qty'         => ['required', 'integer', 'min:1', 'max:1000000'],
            'baris.*.harga'       => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'baris.*.baru'        => ['nullable', 'boolean'],
            'baris.*.deskripsi'   => ['required_if:baris.*.baru,1', 'nullable', 'string', 'max:255'],
            'baris.*.lokasi_rak'  => ['nullable', 'string', 'max:50'],
        ], [], [
            'baris'              => __('item lines'),
            'baris.*.part_no'    => __('part number'),
            'baris.*.qty'        => __('quantity'),
            'baris.*.harga'      => __('unit price'),
            'baris.*.deskripsi'  => __('description'),
        ]);

        // Barang "baru" yang ternyata sudah terdaftar: tolak supaya tidak tercipta data ganda.
        foreach ($data['baris'] as $b) {
            if (!empty($b['baru']) && Inventory::where('part_no', trim($b['part_no']))->exists()) {
                return back()->withInput()->with('error', __('Part number :part is already registered. Search for it instead of adding it as a new item.', ['part' => $b['part_no']]));
            }
        }

        try {
            $dok = $this->penerimaan->simpan($data, $data['baris'], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('penerimaan.show', $dok)
            ->with('success', __('Goods receipt :kode saved: :n items added to stock. Remember to post the same receipt in ERP.', ['kode' => $dok->kode, 'n' => count($data['baris'])])
                . (collect($data['baris'])->contains(fn ($b) => !empty($b['baru'])) ? ' ' . __('New parts were created — print their barcode labels in Print Labels.') : ''));
    }

    public function show(PenerimaanBarang $penerimaan)
    {
        $penerimaan->load(['baris.inventory:id,part_no,deskripsi,lokasi_rak,stock', 'user:id,name']);

        return view('penerimaan.show', ['dok' => $penerimaan]);
    }

    public function batal(Request $request, PenerimaanBarang $penerimaan)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:300']]);

        try {
            $this->penerimaan->batal($penerimaan, $data['alasan']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Goods receipt :kode cancelled; stock has been reduced again.', ['kode' => $penerimaan->kode]));
    }
}
