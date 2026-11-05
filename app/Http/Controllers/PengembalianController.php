<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\InventoryTransaction;

class PengembalianController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        // Hanya pengambilan dari aplikasi. Baris hasil import ERP (source_ref terisi)
        // adalah pemakaian yang sudah final di ERP, jadi tidak bisa "dikembalikan".
        $dasar = HistoryLog::whereNull('source_ref')->with('karyawan');

        // Default: pengambilan 7 hari terakhir (paling sering diretur karena tidak jadi dipakai).
        // Retur tidak punya batas waktu — transaksi lebih lama tetap bisa dicari.
        $terbaru = (clone $dasar)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereColumn('qty_returned', '<', 'jumlah')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $returBulanIni = (clone $dasar)->where('qty_returned', '>', 0)
            ->where('updated_at', '>=', now()->startOfMonth())->sum('qty_returned');

        $transaksi = collect();
        if ($q !== '') {
            $transaksi = (clone $dasar)
                ->where(function ($sub) use ($q) {
                    $sub->where('part_no', 'like', "%$q%")
                        ->orWhere('item_name', 'like', "%$q%")
                        ->orWhere('machine', 'like', "%$q%")
                        ->orWhere('peminjam', 'like', "%$q%")
                        ->orWhere('no_pm', 'like', "%$q%");
                })
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        return view('pengembalian.index', compact('transaksi', 'q', 'terbaru', 'returBulanIni'));
    }

    public function kembalikan(Request $request, HistoryLog $history)
    {
        $request->validate(['jumlah' => 'required|integer|min:1']);

        if ($history->source_ref) {
            return back()->with('error', __('Transactions imported from ERP cannot be returned from the app.'));
        }

        $hasil = DB::transaction(function () use ($request, $history) {
            $history = HistoryLog::whereKey($history->id)->lockForUpdate()->first();
            $sisa = $history->jumlah - $history->qty_returned;

            if ($sisa <= 0) {
                return ['ok' => false, 'pesan' => __('This item has already been fully returned.')];
            }

            $jumlahDikembalikan = min((int) $request->jumlah, $sisa);

            $history->qty_returned += $jumlahDikembalikan;
            $history->updated_at = now();
            $history->save();

            // Stock kembali bertambah sesuai jumlah yang dikembalikan
            $item = Inventory::where('part_no', $history->part_no)->lockForUpdate()->first();
            $item?->increment('stock', $jumlahDikembalikan);

            // Koreksi biaya: OUT negatif yang mengurangi biaya mesin terkait.
            // Hanya untuk pengambilan yang memang tercatat OUT (data sebelum perbaikan ini tidak punya).
            $out = InventoryTransaction::where('history_log_id', $history->id)
                ->where('source_type', 'app')
                ->first();

            if ($out) {
                InventoryTransaction::create([
                    'inventory_id'      => $out->inventory_id,
                    'type'              => 'OUT',
                    'qty'               => -$jumlahDikembalikan,
                    'mesin'             => $out->mesin,
                    'harga_satuan'      => $out->harga_satuan,
                    'total_harga'       => -1 * $out->harga_satuan * $jumlahDikembalikan,
                    'keterangan'        => "Pengembalian {$jumlahDikembalikan} dari {$history->peminjam} (mesin {$out->mesin})",
                    'tanggal_transaksi' => today(),
                    'source_type'       => 'app_return',
                    'source_ref'        => 'history_log:' . $history->id . ':kembali:' . ($history->qty_returned),
                    'history_log_id'    => $history->id,
                ]);
            }

            $pesan = __('Item returned.');
            if ($jumlahDikembalikan < (int) $request->jumlah) {
                $pesan .= ' ' . __('Only :n were recorded as returned (:sisa still out).', ['n' => $jumlahDikembalikan, 'sisa' => $sisa]);
            }

            return ['ok' => true, 'pesan' => $pesan];
        });

        if (!$hasil['ok']) {
            return back()->with('error', $hasil['pesan']);
        }

        return redirect()->route('pengembalian.index', ['q' => $request->q])
            ->with('success', $hasil['pesan']);
    }
}
