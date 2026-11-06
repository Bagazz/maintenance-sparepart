<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Services\StockOpnameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cetak label barcode untuk ditempel di rak:
 *  - label LACI: barcode = kode bin (mis. M03-B5D1). Di-scan di kiosk -> isi laci; di opname -> saring laci.
 *  - label PART: barcode = part number. Di-scan di kiosk -> langsung ke detail barang.
 */
class LabelController extends Controller
{
    /** Ukuran label (mm): lebar, tinggi. */
    public const UKURAN = [
        'kecil'  => [50, 25],
        'sedang' => [70, 35],
        'besar'  => [100, 50],
        // 3 kolom per A4 (hemat kertas), jarak 2 mm antar label agar mudah digunting.
        'tiga'   => [63, 33],
    ];

    public const SEMUA = 'semua';

    /** Lingkup khusus: hanya part / laci yang labelnya belum ditempel (baru atau pindah laci). */
    public const BARU = 'baru';

    public const POLA_BIN = '/^[A-Z]\d{2}-[A-Z]+\d*(D\d+)?$/';

    public function index(Request $request, StockOpnameService $opname)
    {
        $jenis = $request->get('jenis') === 'part' ? 'part' : 'laci';
        $daftarRak = $opname->daftarRak();
        $rak = $request->get('rak');
        $semua = $rak === self::SEMUA;
        $baru = $rak === self::BARU;
        if (!$semua && !$baru && (!$rak || !$daftarRak->firstWhere('kode', $rak))) {
            $rak = $daftarRak->first()?->kode;
        }

        $part = $rak ? $this->queryLingkup($rak, $jenis)->orderBy('deskripsi')
            ->get(['id', 'part_no', 'deskripsi', 'lokasi_rak', 'stock', 'label_dicetak_at', 'label_lokasi'])->sortBy('lokasi_rak', SORT_NATURAL)->values() : collect();

        // Satu baris per laci (bin), berisi part-part di dalamnya.
        $laci = $part->groupBy('lokasi_rak')
            ->map(fn ($isi, $bin) => (object) [
                'bin'    => $bin,
                'lokasi' => Inventory::uraiLokasi($bin),
                'jumlah' => $isi->count(),
                'contoh' => $isi->take(2)->pluck('deskripsi')->all(),
            ])
            ->sortBy('bin', SORT_NATURAL)
            ->values();

        $perlu = ['part' => Inventory::perluLabel()->count(), 'laci' => Inventory::laciPerluLabel()->count()];

        return view('label.index', compact('jenis', 'daftarRak', 'rak', 'part', 'laci', 'semua', 'baru', 'perlu'));
    }

    /** Lingkup cetak: satu rak (M03-B5), semua rak gudang M03, atau yang belum berlabel (baru). */
    private function queryLingkup(string $rak, string $jenis)
    {
        return match (true) {
            $rak === self::SEMUA => Inventory::gudangAktif(),
            $rak === self::BARU && $jenis === 'laci' => Inventory::whereIn('lokasi_rak', Inventory::laciPerluLabel()),
            $rak === self::BARU => Inventory::perluLabel(),
            default => Inventory::diRak($rak),
        };
    }

    public function cetak(Request $request)
    {
        $data = $request->validate([
            'jenis'     => ['required', 'in:laci,part'],
            // Pilih satu per satu, ATAU seluruh isi rak (rak=M03-B5) / semua rak (rak=semua) tanpa batas jumlah.
            'pilih'     => ['required_without:rak', 'array', 'min:1', 'max:1000'],
            'rak'       => ['nullable', 'string', 'max:30', 'regex:/^(semua|baru|[A-Z]\d{2}-[A-Z]+\d*)$/'],
            'pilih.*'   => ['required', 'string', 'max:100'],
            'ukuran'    => ['nullable', 'in:' . implode(',', array_keys(self::UKURAN))],
            'mode'      => ['nullable', 'in:a4,printer'],
            'salinan'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'nama_part' => ['nullable', 'boolean'],
        ]);
        $ukuran = $data['ukuran'] ?? 'sedang';
        $salinan = (int) ($data['salinan'] ?? 1);

        if (!empty($data['rak'])) {
            $lingkup = $this->queryLingkup($data['rak'], $data['jenis']);
            $data['pilih'] = $data['jenis'] === 'laci'
                ? $lingkup->distinct()->pluck('lokasi_rak')->all()
                : $lingkup->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        if ($data['jenis'] === 'laci') {
            $bin = collect($data['pilih'])->map(fn ($b) => strtoupper(trim($b)))
                ->filter(fn ($b) => preg_match(self::POLA_BIN, $b))->unique()->sort(SORT_NATURAL)->values();
            $isi = Inventory::whereIn('lokasi_rak', $bin)->orderBy('deskripsi')->get(['deskripsi', 'lokasi_rak'])->groupBy('lokasi_rak');

            $label = $bin->filter(fn ($b) => $isi->has($b))->map(function ($b) use ($isi) {
                $l = Inventory::uraiLokasi($b);
                return [
                    'kode'   => $b,
                    'judul'  => $l['laci'] ? $l['rak'] . ' · ' . $l['laci'] : $l['rak'],
                    'gudang' => $l['gudang'],
                    'jumlah' => $isi[$b]->count(),
                    'isi'    => $isi[$b]->pluck('deskripsi')->all(),
                ];
            });
        } else {
            $label = Inventory::whereIn('id', array_filter($data['pilih'], 'ctype_digit'))
                ->orderBy('deskripsi')
                ->get(['id', 'part_no', 'deskripsi', 'lokasi_rak'])
                ->sortBy('lokasi_rak', SORT_NATURAL)->values()   // urut rak & laci seperti di gudang: D1, D2, … D10
                ->map(fn ($p) => ['id' => $p->id, 'kode' => $p->part_no, 'judul' => $p->deskripsi, 'bin' => $p->lokasi_rak]);
        }

        abort_if($label->isEmpty(), 404);

        // Untuk tombol "Tandai sudah ditempel" di halaman cetak.
        $tandai = $label->pluck($data['jenis'] === 'laci' ? 'kode' : 'id')->values();

        // Salinan: label yang sama dicetak berurutan (mis. 2 = depan & belakang laci).
        $label = $label->flatMap(fn ($l) => array_fill(0, $salinan, $l))->values();

        return view('label.cetak', [
            'jenis'    => $data['jenis'],
            'label'    => $label,
            'ukuran'   => $ukuran,
            'mm'       => self::UKURAN[$ukuran],
            'mode'     => $data['mode'] ?? 'a4',
            'namaPart' => $request->boolean('nama_part', true),
            'tandai'   => $tandai,
        ]);
    }

    /**
     * Tandai label sudah dicetak & ditempel. Dipisah dari tombol cetak karena aplikasi tidak bisa
     * tahu apakah kertasnya benar-benar keluar dari printer dan sudah ditempel di rak.
     */
    public function tandai(Request $request)
    {
        $data = $request->validate([
            'jenis'   => ['required', 'in:laci,part'],
            // Satu kolom berisi daftar dipisah koma (ribuan label tidak kena batas max_input_vars PHP).
            'daftar'  => ['required', 'string', 'max:200000'],
        ]);
        $data['pilih'] = array_values(array_filter(array_map('trim', explode(',', $data['daftar']))));
        abort_if(empty($data['pilih']), 422);

        if ($data['jenis'] === 'laci') {
            $bin = collect($data['pilih'])->map(fn ($b) => strtoupper(trim($b)))->filter(fn ($b) => preg_match(self::POLA_BIN, $b))->unique();
            $sekarang = now();
            foreach ($bin->chunk(500) as $potong) {
                DB::table('label_laci')->upsert($potong->map(fn ($b) => ['kode' => $b, 'dicetak_at' => $sekarang])->values()->all(), ['kode'], ['dicetak_at']);
            }
            $n = $bin->count();
        } else {
            $n = 0;
            foreach (array_chunk(array_filter($data['pilih'], 'ctype_digit'), 500) as $potong) {
                $n += Inventory::whereIn('id', $potong)->update(['label_dicetak_at' => now(), 'label_lokasi' => DB::raw('lokasi_rak')]);
            }
        }

        return redirect()->route('label.index', ['jenis' => $data['jenis'], 'rak' => self::BARU])
            ->with('success', __(':n labels marked as printed and attached.', ['n' => number_format($n, 0, ',', '.')]));
    }
}
