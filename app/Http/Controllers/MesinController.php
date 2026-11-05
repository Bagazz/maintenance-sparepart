<?php

namespace App\Http\Controllers;

use App\Models\HistoryLog;
use App\Models\JobMesin;
use App\Models\Mesin;
use App\Services\AnalitikBiayaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Master mesin & padanan nomor Job PM (ERP) -> nama mesin (khusus admin).
 * Dengan padanan ini laporan biaya bisa dibaca per mesin, bukan per nomor Job.
 */
class MesinController extends Controller
{
    public function index(Request $request, AnalitikBiayaService $analitik)
    {
        $cari = trim((string) $request->get('q', ''));
        $filter = in_array($request->get('filter'), ['belum', 'sudah', 'semua'], true) ? $request->get('filter') : 'semua';

        $jobs = $analitik->daftarJob($cari ?: null, $filter)->paginate(25)->withQueryString();

        $semuaJob = DB::query()->fromSub($analitik->daftarJob(), 'j');
        $statistik = [
            'job'         => (clone $semuaJob)->count(),
            'dipetakan'   => (clone $semuaJob)->whereNotNull('mesin')->count(),
            'rp_total'    => (float) (clone $semuaJob)->sum('rp'),
            'rp_belum'    => (float) (clone $semuaJob)->whereNull('mesin')->sum('rp'),
        ];

        $mesin = Mesin::query()
            ->leftJoinSub(JobMesin::select('mesin', DB::raw('COUNT(*) as jumlah_job'))->groupBy('mesin'), 'jm', 'jm.mesin', '=', 'mesins.nama')
            ->select('mesins.*', DB::raw('COALESCE(jm.jumlah_job, 0) as jumlah_job'))
            ->orderBy('nama')
            ->get();

        return view('mesin.index', compact('jobs', 'cari', 'filter', 'statistik', 'mesin'));
    }

    public function petakan(Request $request)
    {
        $data = $request->validate([
            'no_job'     => 'required|string|max:50',
            'mesin'      => 'required|string|max:100',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $p = JobMesin::petakan($data['no_job'], $data['mesin'], $data['keterangan'] ?? null);

        return back()->with('success', __('Job :job mapped to machine :mesin.', ['job' => $p->no_job, 'mesin' => $p->mesin]));
    }

    public function hapusPadanan(Request $request)
    {
        $job = JobMesin::normalJob($request->validate(['no_job' => 'required|string|max:50'])['no_job']);
        JobMesin::where('no_job', $job)->delete();

        return back()->with('success', __('Mapping for Job :job removed — it shows as a Job number again.', ['job' => $job]));
    }

    /**
     * Import massal: baris pertama header, kolom dikenali dari nama:
     * "Job" / "No PM" / "No Job" dan "Mesin" (opsional "Keterangan").
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);

        $baris = IOFactory::load($request->file('file')->getPathname())->getActiveSheet()->toArray(null, true, true, false);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($baris) ?? []);

        $cari = function (array $kandidat) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($kandidat as $k) {
                    if ($h === $k || str_contains($h, $k)) {
                        return $i;
                    }
                }
            }
            return null;
        };
        $kJob = $cari(['no job', 'job', 'no pm', 'pm']);
        $kMesin = $cari(['mesin', 'machine']);
        $kKet = $cari(['keterangan', 'catatan']);

        if ($kJob === null || $kMesin === null) {
            return back()->with('error', __('Columns "Job" and "Mesin" were not found in the first row. Example header: No Job | Mesin | Keterangan'));
        }

        $hasil = ['tersimpan' => 0, 'dilewati' => 0];
        DB::transaction(function () use ($baris, $kJob, $kMesin, $kKet, &$hasil) {
            foreach ($baris as $r) {
                $job = JobMesin::normalJob($r[$kJob] ?? '');
                $mesin = JobMesin::normalMesin($r[$kMesin] ?? '');
                if ($job === '' || $mesin === '') {
                    $hasil['dilewati']++;
                    continue;
                }
                JobMesin::petakan($job, $mesin, $kKet !== null ? trim((string) ($r[$kKet] ?? '')) : null);
                $hasil['tersimpan']++;
            }
        });

        return back()->with('success', __('Mapping import finished: :n Jobs mapped, :lewat rows skipped.', ['n' => $hasil['tersimpan'], 'lewat' => $hasil['dilewati']]));
    }

    public function tambahMesin(Request $request)
    {
        $nama = JobMesin::normalMesin($request->validate(['nama' => 'required|string|max:100'])['nama']);

        $m = Mesin::firstOrCreate(['nama' => $nama]);

        return back()->with('success', $m->wasRecentlyCreated ? __('Machine :nama added to the kiosk list.', ['nama' => $nama]) : __('Machine :nama already exists.', ['nama' => $nama]));
    }

    /** Hapus mesin dari daftar kiosk — hanya bila belum dipakai di padanan maupun riwayat pengambilan. */
    public function hapusMesin(Mesin $mesin)
    {
        $dipakai = JobMesin::where('mesin', $mesin->nama)->exists()
            || HistoryLog::where('machine', $mesin->nama)->exists();

        if ($dipakai) {
            return back()->with('error', __('Machine :nama is used in Job mappings / take-out history and cannot be removed.', ['nama' => $mesin->nama]));
        }

        $mesin->delete();

        return back()->with('success', __('Machine :nama removed from the list.', ['nama' => $mesin->nama]));
    }
}
