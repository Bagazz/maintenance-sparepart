<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\AnalitikBiayaService;
use App\Services\StockOpnameService;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function __construct(private StockOpnameService $opname)
    {
    }

    /** Daftar rak (kapan terakhir dihitung) + sesi berjalan + riwayat sesi. */
    public function index()
    {
        $rak = $this->opname->daftarRak();
        $batas = now()->subDays(StockOpnameService::HARI_SIKLUS);

        $berjalan = StockOpname::where('status', 'berjalan')->latest()->get();
        $riwayat = StockOpname::whereIn('status', ['diposting', 'batal'])->latest('updated_at')->paginate(10);

        return view('opname.index', [
            'rak'        => $rak,
            'berjalan'   => $berjalan->map(fn ($o) => [$o, $this->opname->ringkasan($o)]),
            'riwayat'    => $riwayat,
            'ringkasRiwayat' => $riwayat->getCollection()->mapWithKeys(fn ($o) => [$o->id => $this->opname->ringkasan($o)]),
            'statistik'  => [
                'rak'         => $rak->count(),
                'terhitung'   => $rak->filter(fn ($r) => $r->terakhir && $r->terakhir->gte($batas))->count(),
                'belum_pernah' => $rak->filter(fn ($r) => !$r->terakhir)->count(),
                'fy'          => $this->opname->selisihPeriode(AnalitikBiayaService::awalFY(), now()),
            ],
        ]);
    }

    public function mulai(Request $request)
    {
        $data = $request->validate([
            'kode_rak'    => ['required', 'string', 'max:30', 'regex:' . StockOpnameService::POLA_RAK],
            'petugas'     => ['required', 'string', 'max:100'],
            'hitung_buta' => ['nullable', 'boolean'],
        ]);
        abort_unless(Inventory::diRak($data['kode_rak'])->exists(), 404);

        $opname = $this->opname->mulai($data['kode_rak'], $data['petugas'], $request->boolean('hitung_buta'), $request->user());

        return redirect()->route('opname.hitung', $opname);
    }

    /** Layar hitung (dioptimalkan untuk HP). */
    public function hitung(StockOpname $opname)
    {
        if (!$opname->berjalan) {
            return redirect()->route('opname.tinjau', $opname);
        }

        $items = $opname->items()->with('inventory:id,part_no,deskripsi,lokasi_rak,foto_barang,kata_kunci')->get()
            ->map(fn (StockOpnameItem $i) => [
                'id'        => $i->id,
                'part_no'   => $i->inventory->part_no,
                'deskripsi' => $i->inventory->deskripsi,
                'kunci'     => $i->inventory->kata_kunci,
                'bin'       => $i->inventory->lokasi_rak,
                'laci'      => $i->inventory->lokasi['laci'] ?? '',
                'foto'      => $i->inventory->foto_barang_url,
                'tambahan'  => $i->tambahan,
                'fisik'     => $i->qty_fisik,
                'sistem'    => $i->qty_fisik === null && $opname->hitung_buta ? null : $i->stok_sistem,
                'catatan'   => $i->catatan,
            ])
            ->sortBy(fn ($i) => [$i['tambahan'] ? 1 : 0, strlen($i['laci']), $i['laci'], $i['deskripsi']])
            ->values();

        return view('opname.hitung', ['opname' => $opname, 'items' => $items, 'ringkasan' => $this->opname->ringkasan($opname)]);
    }

    /** Simpan satu hasil hitung (dipanggil lewat fetch dari layar hitung). */
    public function simpan(Request $request, StockOpname $opname, StockOpnameItem $item)
    {
        abort_unless((int) $item->stock_opname_id === (int) $opname->id, 404);
        if (!$opname->berjalan) {
            return response()->json(['pesan' => __('This count session has already been closed.')], 409);
        }

        $data = $request->validate([
            'qty_fisik' => ['required', 'integer', 'min:0', 'max:1000000'],
            'catatan'   => ['nullable', 'string', 'max:200'],
        ]);
        $item = $this->opname->simpanHitung($item, (int) $data['qty_fisik'], $data['catatan'] ?? null);

        return response()->json([
            'id'        => $item->id,
            'fisik'     => $item->qty_fisik,
            'sistem'    => $item->stok_sistem,
            'selisih'   => $item->selisih,
            'catatan'   => $item->catatan,
            'ringkasan' => $this->opname->ringkasan($opname),
        ]);
    }

    /** Tambahkan part yang ditemukan di rak tapi tidak tercantum di daftar rak ini. */
    public function tambah(Request $request, StockOpname $opname)
    {
        abort_unless($opname->berjalan, 409);
        $data = $request->validate(['part_no' => ['required', 'string', 'max:100']]);

        $part = Inventory::where('part_no', trim($data['part_no']))->first();
        if (!$part) {
            return back()->with('error', __('Part :part not found', ['part' => $data['part_no']]));
        }
        $this->opname->tambahItem($opname, $part);

        return redirect()->route('opname.hitung', ['opname' => $opname, 'cari' => $part->part_no])
            ->with('success', __('":item" added to this count.', ['item' => $part->deskripsi]));
    }

    /** Ringkasan selisih sebelum / sesudah posting (juga bisa dicetak). */
    public function tinjau(StockOpname $opname)
    {
        $items = $opname->items()->with('inventory:id,part_no,deskripsi,lokasi_rak')->get();

        return view('opname.tinjau', [
            'opname'    => $opname,
            'ringkasan' => $this->opname->ringkasan($opname),
            'selisih'   => $items->filter(fn ($i) => $i->selisih)->sortBy(fn ($i) => $i->selisih_rp)->values(),
            'belum'     => $items->whereNull('qty_fisik')->sortBy(fn ($i) => $i->inventory->lokasi_rak)->values(),
        ]);
    }

    public function posting(StockOpname $opname)
    {
        try {
            $hasil = $this->opname->posting($opname);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('opname.tinjau', $opname)
            ->with('success', __('Count posted: stock of :n parts adjusted. Remember to post the same adjustments in ERP.', ['n' => $hasil['disesuaikan']]));
    }

    public function batal(StockOpname $opname)
    {
        abort_unless($opname->berjalan, 409);
        $opname->update(['status' => 'batal']);

        return redirect()->route('opname.index')->with('success', __('Count session :kode cancelled; stock was not changed.', ['kode' => $opname->kode]));
    }
}
