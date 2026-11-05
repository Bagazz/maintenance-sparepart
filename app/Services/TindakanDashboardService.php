<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Karyawan;
use App\Models\PermintaanAmbil;
use App\Models\PurchaseRequest;

/**
 * Daftar hal yang perlu ditindaklanjuti Kepala Spare Part, untuk panel peringatan di atas Dashboard.
 * Tingkat "kritis" = merah berdenyut (harus segera), "perhatian" = kuning.
 */
class TindakanDashboardService
{
    public function __construct(private AnalitikBiayaService $analitik, private BackupService $backup)
    {
    }

    /** @return array<int, array{tingkat: string, ikon: string, judul: string, isi: string, angka: int|string, url: string, tombol: string}> */
    public function daftar(): array
    {
        $t = [];

        if ($n = PermintaanAmbil::menunggu()->count()) {
            $tertua = PermintaanAmbil::menunggu()->oldest('id')->value('created_at');
            $t[] = ['tingkat' => 'kritis', 'ikon' => 'tangan', 'angka' => $n,
                'judul' => __('Take-outs waiting for your approval'),
                'isi'   => __('Technicians are waiting. Oldest request: :waktu.', ['waktu' => $tertua?->diffForHumans()]),
                'url'   => route('persetujuan.index'), 'tombol' => __('Review now')];
        }

        $restock = $this->analitik->ringkasanRestock();
        if ($restock['habis']) {
            $t[] = ['tingkat' => 'kritis', 'ikon' => 'kosong', 'angka' => $restock['habis'],
                'judul' => __('Frequently used parts are out of stock'),
                'isi'   => __('Out of stock while still used in the last 90 days. Create a PR so they arrive soon.'),
                'url'   => route('restock.index'), 'tombol' => __('Open purchase recommendations')];
        }

        if ($n = PurchaseRequest::terlambat()->count()) {
            $t[] = ['tingkat' => 'kritis', 'ikon' => 'jam', 'angka' => $n,
                'judul' => __('Purchase Requests past their expected arrival'),
                'isi'   => __('The PO was issued but the goods have not fully arrived. Follow up with Purchasing / the supplier.'),
                'url'   => route('pr.index', ['status' => 'terlambat']), 'tombol' => __('View late PRs')];
        }

        $status = $this->backup->status();
        if ($status['perlu_cek']) {
            $t[] = ['tingkat' => 'kritis', 'ikon' => 'cakram', 'angka' => '!',
                'judul' => __('Backup needs attention'),
                'isi'   => ($status['terakhir_ok'] ? __('The latest backup is from :tgl.', ['tgl' => $status['terakhir_ok']->waktu->diffForHumans()]) : __('No backup yet') . '.')
                    . (($status['terakhir']['ok'] ?? true) === false ? ' ' . __('The last attempt failed.') : ''),
                'url'   => route('backup.index'), 'tombol' => __('Open Backup')];
        }

        if ($n = PurchaseRequest::where('status', 'diajukan')->where('created_at', '<', now()->subDays(3))->count()) {
            $t[] = ['tingkat' => 'perhatian', 'ikon' => 'dokumen', 'angka' => $n,
                'judul' => __('PRs without a PO for more than 3 days'),
                'isi'   => __('Ask Purchasing for the PO number and record it, so arrival can be tracked.'),
                'url'   => route('pr.index'), 'tombol' => __('Open PRs')];
        }

        $label = Inventory::perluLabel()->count() + Inventory::laciPerluLabel()->count();
        if ($label) {
            $t[] = ['tingkat' => 'perhatian', 'ikon' => 'label', 'angka' => $label,
                'judul' => __('Barcode labels to print'),
                'isi'   => __(':b new parts, :p parts moved to another drawer, :l new drawers. Existing labels stay valid; print only these.', [
                    'b' => Inventory::perluLabel()->whereNull('label_dicetak_at')->count(),
                    'p' => Inventory::perluLabel()->whereNotNull('label_dicetak_at')->count(),
                    'l' => Inventory::laciPerluLabel()->count(),
                ]),
                'url'   => route('label.index', ['jenis' => 'part', 'rak' => 'baru']), 'tombol' => __('Print labels')];
        }

        if ($n = Inventory::baruTanpaFoto()->count()) {
            $t[] = ['tingkat' => 'perhatian', 'ikon' => 'kamera', 'angka' => $n,
                'judul' => __('New parts without a photo'),
                'isi'   => __('Added in the last :n days (ERP import / Goods Receipt). A photo helps technicians recognise the item in the catalog.', ['n' => Inventory::HARI_PART_BARU]),
                'url'   => route('inventory.kelola', ['filter' => 'baru_tanpa_foto']), 'tombol' => __('Add photos')];
        }

        if ($n = Karyawan::whereNotNull('pin_terkunci_at')->where('aktif', true)->count()) {
            $t[] = ['tingkat' => 'perhatian', 'ikon' => 'gembok', 'angka' => $n,
                'judul' => __('Technician accounts locked'),
                'isi'   => __('Locked after too many wrong passwords. They cannot take items until you unlock them.'),
                'url'   => route('karyawan.index', ['status' => 'terkunci']), 'tombol' => __('Unlock')];
        }

        return $t;
    }
}
