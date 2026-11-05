<?php

namespace App\Http\Controllers;

use App\Models\HistoryLog;
use App\Services\RekonsiliasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Rekonsiliasi scan kiosk vs pemakaian ERP (khusus admin / kepala sparepart). */
class RekonsiliasiController extends Controller
{
    public function __construct(private RekonsiliasiService $rekon)
    {
    }

    public function index(Request $request)
    {
        // Default: fiscal year berjalan (1 Juli – hari ini)
        $awalFY = now()->month >= 7 ? now()->startOfYear()->setMonth(7) : now()->subYear()->startOfYear()->setMonth(7);
        $awal = Carbon::parse($request->get('dari', $awalFY))->startOfDay();
        $akhir = Carbon::parse($request->get('sampai', today()))->endOfDay();
        if ($awal->gt($akhir)) {
            [$awal, $akhir] = [$akhir->copy()->startOfDay(), $awal->copy()->endOfDay()];
        }

        $tab = $request->get('tab', 'erp');
        $laporan = $this->rekon->laporan($awal, $akhir);

        // Kandidat pasangan manual hanya untuk scan yang belum cocok (dibatasi supaya ringan)
        $kandidat = $tab === 'scan'
            ? $laporan['scan_belum']->take(50)->mapWithKeys(fn ($s) => [$s->id => $this->rekon->kandidatManual($s)])
            : collect();

        return view('rekonsiliasi.index', compact('laporan', 'awal', 'akhir', 'tab', 'kandidat'));
    }

    public function jalankan()
    {
        $n = $this->rekon->jalankan();

        return back()->with('success', $n ? __(':n new scan ↔ ERP pairs found.', ['n' => $n]) : __('No new pairs. The remaining data needs a manual check.'));
    }

    public function pasangkan(Request $request)
    {
        $data = $request->validate([
            'scan_id' => 'required|exists:history_logs,id',
            'erp_id'  => 'required|exists:history_logs,id',
        ]);

        $scan = HistoryLog::findOrFail($data['scan_id']);
        $erp = HistoryLog::findOrFail($data['erp_id']);

        if ($scan->source_ref || !$erp->source_ref || $scan->part_no !== $erp->part_no) {
            return back()->with('error', __('Invalid pair: it must be an app scan with an ERP line for the same part.'));
        }
        if ($scan->rekon_pasangan_id || $erp->rekon_pasangan_id) {
            return back()->with('error', __('One of the rows is already paired. Unpair it first.'));
        }

        $this->rekon->pasangkan($scan, $erp, 'manual');

        return back()->with('success', __('Scan :item paired with ERP :job.', ['item' => $scan->item_name, 'job' => $erp->no_pm]));
    }

    public function lepas(HistoryLog $history)
    {
        $this->rekon->lepas($history);

        return back()->with('success', __('Pair removed.'));
    }

    /** Tandai baris sudah dijelaskan (mis. diambil sebelum kiosk dipasang) agar tidak muncul lagi di laporan. */
    public function abaikan(Request $request, HistoryLog $history)
    {
        $data = $request->validate(['catatan' => 'required|string|max:255']);

        $history->forceFill([
            'rekon_abaikan' => !$history->rekon_abaikan,
            'rekon_catatan' => $history->rekon_abaikan ? null : $data['catatan'] . ' — ' . auth()->user()->name,
        ])->save();

        return back()->with('success', $history->rekon_abaikan ? __('Marked as explained.') : __('Mark removed.'));
    }
}
