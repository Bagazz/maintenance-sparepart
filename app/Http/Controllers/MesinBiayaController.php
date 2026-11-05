<?php

namespace App\Http\Controllers;

use App\Services\MesinAnalitikService;
use Illuminate\Http\Request;

/** Biaya spare part per mesin: peringkat semua mesin + halaman detail satu mesin. */
class MesinBiayaController extends Controller
{
    public const PERIODE = ['12-bulan', 'fy', 'semua'];

    public function __construct(private MesinAnalitikService $mesin)
    {
    }

    private function kodePeriode(Request $request): string
    {
        return in_array($request->get('periode'), self::PERIODE, true) ? $request->get('periode') : '12-bulan';
    }

    public function index(Request $request)
    {
        $kode = $this->kodePeriode($request);
        [$awal, $akhir, $label] = $this->mesin->periode($kode);
        $daftar = $this->mesin->daftar($awal, $akhir);

        return view('mesin.biaya', [
            'daftar'  => $daftar,
            'periode' => $kode,
            'label'   => $label,
            'awal'    => $awal,
            'akhir'   => $akhir,
            'total'   => (float) $daftar->sum('rp'),
            'belum'   => $daftar->where('belum_dipetakan', 1)->count(),
        ]);
    }

    public function detail(Request $request)
    {
        $nama = trim((string) $request->get('nama'));
        abort_if($nama === '' || mb_strlen($nama) > 100, 404);

        $kode = $this->kodePeriode($request);
        [$awal, $akhir, $label] = $this->mesin->periode($kode);
        $data = $this->mesin->detail($nama, $awal, $akhir);
        abort_if($data === null, 404);

        return view('mesin.detail', $data + ['periode' => $kode, 'label' => $label, 'awal' => $awal, 'akhir' => $akhir]);
    }
}
