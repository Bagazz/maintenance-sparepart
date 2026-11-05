<?php

namespace App\Http\Controllers;

use App\Models\HistoryLog;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $exact = $request->boolean('exact'); // true kalau q berasal dari klik/Enter saran pencarian
        $filterMesin = $request->get('mesin');
        $filterOperator = $request->get('operator');
        $activeTab = $request->get('tab', 'out');

        // ---------------------------------------------------------
        // 1. DATA TAB OUT (PEMINJAMAN) - LOGIKA ASLI TETAP UTUH
        // ---------------------------------------------------------
        $queryOut = HistoryLog::orderByDesc('created_at');

        if ($q) {
            $queryOut->where(function ($sub) use ($q, $exact) {
                if ($exact) {
                    // Dipilih dari daftar saran -> cocokkan persis, jangan ikut ke part number
                    // lain yang kebetulan mengandung angka yang sama (mis. 300112 vs 3001121).
                    $sub->where('part_no', $q)
                        ->orWhere('peminjam', $q)
                        ->orWhere('machine', $q);
                } else {
                    $sub->where('part_no', 'like', "%$q%")
                        ->orWhere('item_name', 'like', "%$q%")
                        ->orWhere('peminjam', 'like', "%$q%")
                        ->orWhere('machine', 'like', "%$q%")
                        ->orWhere('no_pm', 'like', "%$q%");
                }
            });
        }

        if ($filterMesin) {
            $queryOut->where('machine', $filterMesin);
        }

        if ($filterOperator) {
            $queryOut->where('operator', $filterOperator);
        }

        $histories = $queryOut->paginate(15, ['*'], 'out_page')->withQueryString();

        // ---------------------------------------------------------
        // 2. DATA TAB IN (BARANG MASUK / PURCHASING)
        // ---------------------------------------------------------
        $queryIn = InventoryTransaction::with('inventory')->where('type', 'IN')->orderByDesc('created_at');
        if ($q) {
            $queryIn->whereHas('inventory', function($sub) use ($q) {
                $sub->where('part_no', 'like', "%$q%")->orWhere('deskripsi', 'like', "%$q%");
            });
        }
        $inHistories = $queryIn->paginate(15, ['*'], 'in_page')->withQueryString();

        // ---------------------------------------------------------
        // 3. DATA TAB ADJ (PENYESUAIAN STOK)
        // ---------------------------------------------------------
        $queryAdj = InventoryTransaction::with('inventory')->where('type', 'ADJ')->orderByDesc('created_at');
        if ($q) {
            $queryAdj->whereHas('inventory', function($sub) use ($q) {
                $sub->where('part_no', 'like', "%$q%")->orWhere('deskripsi', 'like', "%$q%");
            });
        }
        $adjHistories = $queryAdj->paginate(15, ['*'], 'adj_page')->withQueryString();

        // ---------------------------------------------------------
        // 4. STATISTIK KARTU ATAS
        // ---------------------------------------------------------
        $totalTransaksi = HistoryLog::count() + InventoryTransaction::count();
        $barangKeluar   = HistoryLog::sum('jumlah');
        $barangMasuk    = InventoryTransaction::where('type', 'IN')->sum('qty');
        
        $mesin          = HistoryLog::whereNotNull('machine')->distinct()->count('machine');
        $operator       = HistoryLog::whereNotNull('operator')->distinct()->count('operator');

        $daftarMesin    = HistoryLog::whereNotNull('machine')->distinct()->orderBy('machine')->pluck('machine');
        $daftarOperator = HistoryLog::whereNotNull('operator')->distinct()->orderBy('operator')->pluck('operator');

        return view('history.index', compact(
            'totalTransaksi', 'barangKeluar', 'barangMasuk', 'mesin', 'operator',
            'daftarMesin', 'daftarOperator',
            'histories', 'inHistories', 'adjHistories',
            'q', 'filterMesin', 'filterOperator', 'activeTab'
        ));
    }

    /**
     * Halaman direktori Mesin — daftar semua mesin yang tercatat,
     * masing-masing bisa diklik untuk lihat riwayat pemakaian part di mesin itu.
     */
    public function daftarMesin()
    {
        $daftarMesin = HistoryLog::whereNotNull('machine')->distinct()->orderBy('machine')->pluck('machine');

        return view('history.mesin', compact('daftarMesin'));
    }

    /**
     * Halaman direktori Operator — daftar semua operator yang tercatat,
     * masing-masing bisa diklik untuk lihat riwayat aktivitasnya.
     */
    public function daftarOperator()
    {
        $daftarOperator = HistoryLog::whereNotNull('operator')->distinct()->orderBy('operator')->pluck('operator');

        return view('history.operator', compact('daftarOperator'));
    }

    /**
     * Endpoint autocomplete untuk kotak pencarian History (Logika Asli).
     */
    public function suggest(Request $request)
    {
        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $partNo = HistoryLog::where('part_no', 'like', "%{$q}%")
            ->distinct()->limit(5)->pluck('part_no');

        $peminjam = HistoryLog::where('peminjam', 'like', "%{$q}%")
            ->whereNotNull('peminjam')->distinct()->limit(5)->pluck('peminjam');

        $machine = HistoryLog::where('machine', 'like', "%{$q}%")
            ->whereNotNull('machine')->distinct()->limit(5)->pluck('machine');

        $hasil = collect()
            ->merge($partNo->map(fn($v) => ['label' => $v, 'type' => __('Part Number')]))
            ->merge($peminjam->map(fn($v) => ['label' => $v, 'type' => __('Taken by')]))
            ->merge($machine->map(fn($v) => ['label' => $v, 'type' => __('Machine')]))
            ->unique('label')
            ->values();

        return response()->json($hasil);
    }

    /**
     * Export PDF, mengikuti filter yang sedang aktif (Logika Asli).
     */
    public function exportPdf(Request $request)
    {
        $q = $request->get('q');
        $filterMesin = $request->get('mesin');
        $filterOperator = $request->get('operator');

        $query = HistoryLog::orderByDesc('created_at');

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('part_no', 'like', "%$q%")
                    ->orWhere('peminjam', 'like', "%$q%")
                    ->orWhere('machine', 'like', "%$q%");
            });
        }

        if ($filterMesin) {
            $query->where('machine', $filterMesin);
        }

        if ($filterOperator) {
            $query->where('operator', $filterOperator);
        }

        $histories = $query->get();

        $pdf = Pdf::loadView('history.pdf', compact('histories', 'q', 'filterMesin', 'filterOperator'))
                   ->setPaper('a4', 'landscape');

        return $pdf->download('history-peminjaman-' . now()->format('Ymd-His') . '.pdf');
    }
}