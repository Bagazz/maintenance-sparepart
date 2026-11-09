<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Support\Ekspor;
use Illuminate\Support\Carbon;

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

    /** Pilihan periode ekspor retur: kunci => [label, awal]. */
    public static function pilihanPeriode(): array
    {
        return [
            'bulan-ini'  => [__('This month'), now()->startOfMonth()],
            'bulan-lalu' => [__('Last month'), now()->subMonthNoOverflow()->startOfMonth()],
            '90-hari'    => [__('Last 90 days'), now()->subDays(90)->startOfDay()],
            'semua'      => [__('All time'), null],
        ];
    }

    /**
     * Ekspor riwayat retur (setiap kejadian retur = satu baris) dalam Excel / CSV / PDF.
     * Sumber: transaksi app_return (stok kembali & koreksi biaya mesin) + data pengambilan aslinya.
     */
    public function ekspor(Request $request)
    {
        $data = $request->validate([
            'format'  => ['required', 'in:xlsx,csv,pdf'],
            'periode' => ['nullable', 'in:' . implode(',', array_keys(self::pilihanPeriode()))],
            'q'       => ['nullable', 'string', 'max:100'],
        ]);
        $periode = $data['periode'] ?? 'bulan-ini';
        [$labelPeriode, $awal] = self::pilihanPeriode()[$periode];
        $akhir = $periode === 'bulan-lalu' ? now()->startOfMonth()->subSecond() : now();
        $q = trim((string) ($data['q'] ?? ''));

        $retur = InventoryTransaction::query()
            ->where('inventory_transactions.source_type', 'app_return')
            ->join('history_logs as h', 'h.id', '=', 'inventory_transactions.history_log_id')
            ->leftJoin('inventories as i', 'i.id', '=', 'inventory_transactions.inventory_id')
            ->when($awal, fn ($w) => $w->whereBetween('inventory_transactions.created_at', [$awal, $akhir]))
            ->when($q !== '', fn ($w) => $w->where(function ($sub) use ($q) {
                $sub->where('h.part_no', 'like', "%$q%")->orWhere('h.item_name', 'like', "%$q%")->orWhere('h.machine', 'like', "%$q%")
                    ->orWhere('h.peminjam', 'like', "%$q%")->orWhere('h.no_pm', 'like', "%$q%");
            }))
            ->orderByDesc('inventory_transactions.created_at')
            ->get([
                'inventory_transactions.created_at as waktu', 'inventory_transactions.qty', 'inventory_transactions.harga_satuan', 'inventory_transactions.total_harga',
                'h.part_no', 'h.item_name', 'h.peminjam', 'h.machine', 'h.no_pm', 'h.created_at as diambil', 'h.jumlah', 'h.qty_returned', 'i.deskripsi',
            ]);

        $kolom = [
            [__('Return date'), 'tanggal'], [__('Time'), 'teks'], [__('Part No'), 'teks'], [__('Description'), 'teks'], [__('Qty returned'), 'angka'],
            [__('Unit price (Rp)'), 'rp'], [__('Value returned (Rp)'), 'rp'], [__('Taken by'), 'teks'], [__('Machine / Job'), 'teks'], [__('PM No'), 'teks'],
            [__('Taken on'), 'tanggal'], [__('Qty taken'), 'angka'], [__('Net used'), 'angka'],
        ];
        $baris = $retur->map(fn ($r) => [
            Carbon::parse($r->waktu)->format('Y-m-d'), Carbon::parse($r->waktu)->format('H:i'), (string) $r->part_no, $r->deskripsi ?? $r->item_name ?? '-',
            (int) abs($r->qty), (float) $r->harga_satuan, (float) abs($r->total_harga), $r->peminjam ?? '-', $r->machine ?? '-', $r->no_pm ?? '-',
            Carbon::parse($r->diambil)->format('Y-m-d'), (int) $r->jumlah, (int) ($r->jumlah - $r->qty_returned),
        ]);

        $qtyRetur = $retur->sum(fn ($r) => abs($r->qty));
        $nilaiRetur = $retur->sum(fn ($r) => abs($r->total_harga));
        $keterangan = [
            __('Period') . ': ' . $labelPeriode . ($awal ? ' (' . $awal->translatedFormat('d M Y') . ' – ' . $akhir->translatedFormat('d M Y') . ')' : ''),
            $q !== '' ? __('Search') . ': "' . $q . '"' : __('All records (no filter)'),
            __('Generated :tgl', ['tgl' => now()->translatedFormat('d M Y H:i')]) . ' · ' . __('Returned items go back to stock and are deducted from the machine cost.'),
        ];

        return Ekspor::unduhTabel($data['format'], __('Return History'), 'Return-History-' . now()->format('Ymd-Hi'), $kolom, $baris,
            [0 => __('Total'), 4 => $qtyRetur, 6 => $nilaiRetur],
            [__('Returns') => $retur->count(), __('Qty returned') => $qtyRetur, __('Value returned (Rp)') => $nilaiRetur],
            $keterangan, 'TabelRetur');
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
