<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Models\PermintaanAmbil;
use App\Services\PersetujuanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Persetujuan pengambilan bernilai besar — hanya Kepala Spare Part (admin).
 * Teknisi mengajukan lewat keranjang kiosk; di sini disetujui (stok berkurang, tercatat atas nama teknisi) atau ditolak.
 */
class PersetujuanController extends Controller
{
    public function index()
    {
        $menunggu = PermintaanAmbil::with('items')->menunggu()->oldest('id')->get();
        $riwayat = PermintaanAmbil::with('items')->where('status', '!=', 'menunggu')
            ->where('diputus_at', '>=', now()->subDays(30))->latest('diputus_at')->limit(30)->get();
        $aturan = PersetujuanService::aturan();

        return view('persetujuan.index', compact('menunggu', 'riwayat', 'aturan'));
    }

    public function setujui(PermintaanAmbil $permintaan, PersetujuanService $svc)
    {
        try {
            $svc->setujui($permintaan);
        } catch (\RuntimeException $e) {
            return back()->with('error', __('Request :kode could not be approved: :alasan', ['kode' => $permintaan->kode, 'alasan' => $e->getMessage()]));
        }

        return back()->with('success', __('Request :kode approved: stock reduced and recorded under :nama.', ['kode' => $permintaan->kode, 'nama' => $permintaan->peminjam]));
    }

    public function tolak(Request $request, PermintaanAmbil $permintaan, PersetujuanService $svc)
    {
        $data = $request->validate(['alasan' => ['nullable', 'string', 'max:255']]);

        try {
            $svc->tolak($permintaan, $data['alasan'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Request :kode rejected. The reserved stock is free again.', ['kode' => $permintaan->kode]));
    }

    public function pengaturan(Request $request)
    {
        $data = $request->validate([
            'mode'  => ['required', Rule::in(PersetujuanService::MODE)],
            'nilai' => ['required_if:mode,batas', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'qty'   => ['nullable', 'integer', 'min:0', 'max:100000'],
            'total' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        Pengaturan::simpan('persetujuan_mode', $data['mode']);
        Pengaturan::simpan('persetujuan_nilai', (float) ($data['nilai'] ?? 0));
        Pengaturan::simpan('persetujuan_qty', (int) ($data['qty'] ?? 0));
        Pengaturan::simpan('persetujuan_total', (float) ($data['total'] ?? 0));

        return back()->with('success', __('Approval rules saved.'));
    }
}
