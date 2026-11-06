<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Services\AnalitikBiayaService;
use App\Services\PurchaseRequestExcel;
use App\Services\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Ekspor;

/**
 * Pelacakan Purchase Request (khusus admin): dibuat dari Rekomendasi Pembelian, PO dicatat,
 * barang diterima lewat Penerimaan Barang (otomatis), sampai selesai.
 */
class PurchaseRequestController extends Controller
{
    public function __construct(private PurchaseRequestService $svc, private AnalitikBiayaService $analitik)
    {
    }

    public function index(Request $request)
    {
        $status = $request->get('status', 'terbuka');
        $daftar = PurchaseRequest::withCount('items')
            ->withSum('items as total_minta', 'qty_minta')
            ->withSum('items as total_diterima', 'qty_diterima')
            ->withSum(['items as nilai' => fn ($q) => $q->select(DB::raw('COALESCE(SUM(qty_minta * harga_perkiraan), 0)'))], 'qty_minta')
            ->when($status === 'terbuka', fn ($q) => $q->terbuka())
            ->when($status === 'terlambat', fn ($q) => $q->terlambat())
            ->when($status === 'selesai', fn ($q) => $q->whereIn('status', ['selesai', 'dibatalkan']))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $hitung = [
            'terbuka'   => PurchaseRequest::terbuka()->count(),
            'terlambat' => PurchaseRequest::terlambat()->count(),
            'diajukan'  => PurchaseRequest::where('status', 'diajukan')->count(),
        ];

        return view('pr.index', compact('daftar', 'status', 'hitung'));
    }

    /** Buat PR dari daftar Rekomendasi Pembelian (mengikuti pencarian & pilihan prioritas). */
    public function store(Request $request)
    {
        $q = $request->get('q');
        $barang = $this->analitik->queryPerluRestock()
            ->when($q, fn ($query) => $query->where(fn ($s) => $s->where('inventories.part_no', 'like', "%$q%")->orWhere('inventories.deskripsi', 'like', "%$q%")))
            ->when($request->get('prioritas') === 'penting', fn ($query) => $query->whereRaw('inventories.stock * 3 < p.pakai_90'))
            ->get();

        try {
            $pr = $this->svc->buat($barang, $request->user()->name, $request->input('catatan'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pr.show', $pr)->with('success', __('Purchase Request :kode created with :n parts. Download the Excel, submit it to Purchasing, then record the PO number here.', ['kode' => $pr->kode, 'n' => $pr->items()->count()]));
    }

    public function show(PurchaseRequest $pr)
    {
        $pr->load('items.inventory');
        $penerimaan = \App\Models\PenerimaanBarang::where('purchase_request_id', $pr->id)->latest('id')->get();

        return view('pr.show', compact('pr', 'penerimaan'));
    }

    public function po(Request $request, PurchaseRequest $pr)
    {
        $data = $request->validate([
            'no_po'            => ['required', 'string', 'max:100'],
            'pemasok'          => ['nullable', 'string', 'max:150'],
            'perkiraan_datang' => ['nullable', 'date'],
        ]);

        try {
            $this->svc->catatPo($pr, $data);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('PO :po recorded for :kode.', ['po' => $data['no_po'], 'kode' => $pr->kode]));
    }

    public function batal(PurchaseRequest $pr)
    {
        try {
            $this->svc->batal($pr);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':kode cancelled. Its quantities are no longer counted as on order.', ['kode' => $pr->kode]));
    }

    /** Formulir Excel PR (format sama dengan unduhan dari Rekomendasi Pembelian), memakai nomor PR ini. */
    public function excel(Request $request, PurchaseRequest $pr, PurchaseRequestExcel $excel)
    {
        $pakai = DB::table('inventory_transactions')->where('type', 'OUT')
            ->where('tanggal_transaksi', '>=', now()->subDays(AnalitikBiayaService::HARI_KEBUTUHAN)->toDateString())
            ->whereIn('inventory_id', $pr->items()->pluck('inventory_id'))
            ->groupBy('inventory_id')->selectRaw('inventory_id, SUM(qty) as n')->pluck('n', 'inventory_id');

        $barang = $pr->items()->with('inventory')->get()->map(fn ($i) => (object) [
            'id'                  => $i->inventory_id,
            'part_no'             => $i->part_no,
            'deskripsi'           => $i->deskripsi,
            'lokasi_rak'          => $i->inventory?->lokasi_rak,
            'stock'               => (int) ($i->inventory?->stock ?? 0),
            'pakai_90'            => (int) ($pakai[$i->inventory_id] ?? 0),
            'saran_beli'          => $i->qty_minta,
            'harga_beli_terakhir' => $i->harga_perkiraan,
        ]);

        $buku = $excel->buat($barang, [
            'nomor'   => $pr->kode,
            'tanggal' => $pr->created_at->translatedFormat('d F Y'),
            'pemohon' => $pr->dibuat_oleh,
            'dasar'   => __('Stock < usage in the last 90 days; suggested qty refills to :n months of demand.', ['n' => AnalitikBiayaService::BULAN_TARGET_STOK]),
        ]);

        if ($request->get('format') === 'csv') {
            return Ekspor::unduhCsv($pr->kode . '.csv', $excel->header(), $excel->barisCsv($barang));
        }

        return Ekspor::unduhXlsx($buku, $pr->kode . '.xlsx');
    }

    /** Sisa barang PR (JSON) untuk mengisi formulir Penerimaan Barang. */
    public function sisa(PurchaseRequest $pr)
    {
        return response()->json([
            'kode'    => $pr->kode,
            'no_po'   => $pr->no_po,
            'pemasok' => $pr->pemasok,
            'baris'   => $this->svc->barisPenerimaan($pr),
        ]);
    }
}
