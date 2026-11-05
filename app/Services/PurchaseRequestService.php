<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\PenerimaanBarang;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siklus Purchase Request: dibuat dari Rekomendasi Pembelian -> PO dicatat -> barang diterima
 * (lewat Penerimaan Barang, otomatis) -> selesai.
 */
class PurchaseRequestService
{
    /** Subquery: qty yang masih dipesan (belum diterima) per part, dari PR yang masih terbuka. */
    public static function subqueryDipesan()
    {
        return DB::table('purchase_request_items as pri')
            ->join('purchase_requests as pr', 'pr.id', '=', 'pri.purchase_request_id')
            ->whereIn('pr.status', PurchaseRequest::TERBUKA)
            ->groupBy('pri.inventory_id')
            ->select('pri.inventory_id', DB::raw('SUM(GREATEST(pri.qty_minta - pri.qty_diterima, 0)) as dipesan'));
    }

    /**
     * @param Collection $barang baris dari Rekomendasi Pembelian (punya id, part_no, deskripsi, saran_beli, harga_beli_terakhir)
     */
    public function buat(Collection $barang, string $oleh, ?string $catatan = null): PurchaseRequest
    {
        $barang = $barang->filter(fn ($b) => (int) $b->saran_beli > 0)->values();
        if ($barang->isEmpty()) {
            throw new \RuntimeException(__('Nothing to order: every part in this list is already covered by an open PR.'));
        }

        return DB::transaction(function () use ($barang, $oleh, $catatan) {
            $pr = PurchaseRequest::create(['kode' => PurchaseRequest::kodeBaru(), 'status' => 'diajukan', 'dibuat_oleh' => $oleh, 'catatan' => $catatan]);
            foreach ($barang as $b) {
                $pr->items()->create([
                    'inventory_id'    => $b->id,
                    'part_no'         => $b->part_no,
                    'deskripsi'       => $b->deskripsi,
                    'qty_minta'       => (int) $b->saran_beli,
                    'harga_perkiraan' => (float) $b->harga_beli_terakhir,
                ]);
            }

            return $pr;
        });
    }

    public function catatPo(PurchaseRequest $pr, array $data): void
    {
        if (!in_array($pr->status, ['diajukan', 'po'], true)) {
            throw new \RuntimeException(__('The PO of :kode can no longer be changed.', ['kode' => $pr->kode]));
        }
        $pr->update([
            'status'           => 'po',
            'no_po'            => trim($data['no_po']),
            'pemasok'          => isset($data['pemasok']) ? trim($data['pemasok']) ?: null : null,
            'perkiraan_datang' => $data['perkiraan_datang'] ?? null,
            'po_at'            => $pr->po_at ?? now(),
        ]);
    }

    public function batal(PurchaseRequest $pr): void
    {
        if (!in_array($pr->status, PurchaseRequest::TERBUKA, true)) {
            throw new \RuntimeException(__(':kode is already closed.', ['kode' => $pr->kode]));
        }
        $pr->update(['status' => 'dibatalkan', 'selesai_at' => now()]);
    }

    /**
     * Penerimaan barang mengisi qty diterima PR. Bila penerimaan memilih PR tertentu, PR itu didahulukan;
     * sisanya dicocokkan ke PR terbuka lain untuk part yang sama, dari yang paling lama.
     */
    public function terimaDariPenerimaan(PenerimaanBarang $dok): void
    {
        $sisaPerPart = $dok->baris()->get()->groupBy('inventory_id')->map(fn ($g) => (int) $g->sum('qty'));
        if ($sisaPerPart->isEmpty()) {
            return;
        }

        $items = PurchaseRequestItem::query()
            ->join('purchase_requests as pr', 'pr.id', '=', 'purchase_request_items.purchase_request_id')
            ->whereIn('pr.status', PurchaseRequest::TERBUKA)
            ->whereIn('purchase_request_items.inventory_id', $sisaPerPart->keys())
            ->whereColumn('purchase_request_items.qty_diterima', '<', 'purchase_request_items.qty_minta')
            ->orderByRaw('pr.id = ? DESC', [(int) $dok->purchase_request_id])
            ->orderBy('pr.id')
            ->select('purchase_request_items.*')
            ->lockForUpdate()
            ->get();

        $tersentuh = [];
        foreach ($items as $it) {
            $sisa = $sisaPerPart[$it->inventory_id] ?? 0;
            if ($sisa <= 0) {
                continue;
            }
            $ambil = min($sisa, $it->sisa);
            $it->increment('qty_diterima', $ambil);
            $sisaPerPart[$it->inventory_id] = $sisa - $ambil;
            $tersentuh[$it->purchase_request_id] = true;
        }

        foreach (array_keys($tersentuh) as $id) {
            $this->perbaruiStatus(PurchaseRequest::find($id));
        }
    }

    public function perbaruiStatus(PurchaseRequest $pr): void
    {
        $items = $pr->items()->get();
        $lengkap = $items->every(fn ($i) => $i->qty_diterima >= $i->qty_minta);
        $ada = $items->contains(fn ($i) => $i->qty_diterima > 0);

        if ($lengkap) {
            $pr->update(['status' => 'selesai', 'selesai_at' => now()]);
        } elseif ($ada) {
            $pr->update(['status' => 'sebagian']);
        }
    }

    /** Baris sisa PR untuk mengisi formulir Penerimaan Barang. */
    public function barisPenerimaan(PurchaseRequest $pr): array
    {
        return $pr->items()->with('inventory')->get()->filter(fn ($i) => $i->sisa > 0)->map(fn ($i) => [
            'part_no'   => $i->part_no,
            'deskripsi' => $i->deskripsi,
            'lokasi'    => $i->inventory?->lokasi_rak,
            'stok'      => (int) ($i->inventory?->stock ?? 0),
            'qty'       => $i->sisa,
            'harga'     => (float) ($i->inventory?->harga_beli_terakhir ?: $i->harga_perkiraan),
        ])->values()->all();
    }
}
