@php
    $ukuran = \App\Http\Controllers\LabelController::UKURAN;
    // Daftar besar (semua rak, atau ribuan label baru) diringkas per rak & dicetak lewat lingkup, bukan dicentang satu per satu.
    $ringkas = $semua || ($baru && ($jenis === 'laci' ? $laci->count() : $part->count()) > 300);
    $semuaKode = $jenis === 'laci' ? $laci->pluck('bin') : $part->pluck('id')->map(fn ($i) => (string) $i);
    $jumlahLabel = $jenis === 'laci' ? $laci->count() : $part->count();
    // Ringkasan per rak untuk mode "semua rak".
    $perRak = $part->groupBy(fn ($p) => \App\Models\Inventory::uraiLokasi($p->lokasi_rak)['kode_rak'] ?? $p->lokasi_rak)
        ->map(fn ($g, $kode) => (object) ['kode' => $kode, 'part' => $g->count(), 'laci' => $g->pluck('lokasi_rak')->unique()->count()])
        ->sortBy('kode', SORT_NATURAL)->values();
    // Perkiraan label per lembar stiker A4 (margin 8 mm): kecil 3x11, sedang 2x8, besar 1x5.
    $perLembar = ['kecil' => 33, 'sedang' => 16, 'besar' => 5];
@endphp
<x-shell :title="__('Print Labels')" :subtitle="__('Barcode labels for drawers and parts — scan them at the kiosk and during stock counts')">

    {{-- ============ STATUS LABEL ============ --}}
    @if($perlu['part'] || $perlu['laci'])
        <div class="card flex flex-col gap-4 border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-extrabold text-slate-900">{{ __('Labels to print: :p parts and :l drawers', ['p' => number_format($perlu['part'], 0, ',', '.'), 'l' => number_format($perlu['laci'], 0, ',', '.')]) }}</p>
                <p class="text-sm text-slate-600">{{ __('New parts (from ERP import or Goods Receipt), parts moved to another drawer, and new drawers. After printing and sticking them on, press "Mark as attached" on the print page.') }}</p>
            </div>
            <div class="flex shrink-0 gap-2">
                @if($perlu['part'])<a href="{{ route('label.index', ['jenis' => 'part', 'rak' => 'baru']) }}" class="btn-primary px-4 py-2 text-sm">{{ __(':n part labels', ['n' => number_format($perlu['part'], 0, ',', '.')]) }}</a>@endif
                @if($perlu['laci'])<a href="{{ route('label.index', ['jenis' => 'laci', 'rak' => 'baru']) }}" class="btn-ghost px-4 py-2 text-sm">{{ __(':n drawer labels', ['n' => number_format($perlu['laci'], 0, ',', '.')]) }}</a>@endif
            </div>
        </div>
    @else
        <div class="card flex items-center gap-3 border-emerald-200 bg-emerald-50/70 p-4 text-sm font-semibold text-emerald-800">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
            {{ __('All parts and drawers in the M03 warehouse already have labels.') }}
        </div>
    @endif

    {{-- ============ JENIS & RAK ============ --}}
    <div class="card flex flex-col gap-3 p-3 md:flex-row md:items-center">
        <div class="flex rounded-xl bg-slate-100 p-1">
            @foreach(['laci' => __('Drawer labels'), 'part' => __('Part labels')] as $kode => $label)
                <a href="{{ route('label.index', ['jenis' => $kode, 'rak' => $rak]) }}"
                   class="flex-1 whitespace-nowrap rounded-lg px-4 py-2 text-center text-sm font-bold transition {{ $jenis === $kode ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('label.index') }}" class="flex flex-1 items-center gap-2">
            <input type="hidden" name="jenis" value="{{ $jenis }}">
            <label for="rak" class="text-sm font-bold text-slate-600">{{ __('Rack') }}</label>
            <select id="rak" name="rak" class="input" onchange="this.form.submit()">
                <option value="baru" @selected($baru)>{{ __('Needs a label — new / moved (:n)', ['n' => number_format($perlu[$jenis], 0, ',', '.')]) }}</option>
                <option value="semua" @selected($semua)>{{ __('All racks (M03 warehouse)') }}</option>
                @foreach($daftarRak as $r)
                    <option value="{{ $r->kode }}" @selected($rak === $r->kode)>{{ $r->kode }} · {{ __(':n parts', ['n' => $r->jumlah]) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <form method="GET" action="{{ route('label.cetak') }}" target="_blank"
          x-data="{ pilih: @js($ringkas ? ['semua'] : $semuaKode->values()), cari: '', ukuran: 'sedang', salinan: 1 }" class="grid gap-6 lg:grid-cols-3">
        <input type="hidden" name="jenis" value="{{ $jenis }}">
        @if($ringkas)<input type="hidden" name="rak" value="{{ $rak }}">@endif

        {{-- ============ DAFTAR ============ --}}
        @if($ringkas)
            <div class="card overflow-hidden lg:col-span-2">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="font-bold text-slate-800">{{ $jenis === 'laci' ? __(':n drawer labels across :r racks', ['n' => number_format($jumlahLabel, 0, ',', '.'), 'r' => $perRak->count()]) : __(':n part labels across :r racks', ['n' => number_format($jumlahLabel, 0, ',', '.'), 'r' => $perRak->count()]) }}</p>
                    <p class="text-xs text-slate-500">{{ __('Printed in rack & drawer order (A1, A2 … D1, D2 … D10), so they can be stuck on rack by rack.') }}</p>
                </div>
                <div class="grid max-h-[60vh] grid-cols-2 gap-px overflow-y-auto bg-slate-100 scroll-thin sm:grid-cols-3 xl:grid-cols-4">
                    @foreach($perRak as $r)
                        <a href="{{ route('label.index', ['jenis' => $jenis, 'rak' => $r->kode]) }}" class="bg-white px-4 py-3 hover:bg-brand-50/40">
                            <p class="font-mono font-bold text-brand-700">{{ $r->kode }}</p>
                            <p class="text-[11px] text-slate-500">{{ $jenis === 'laci' ? __(':n drawers', ['n' => $r->laci]) : __(':n parts', ['n' => $r->part]) }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
        <div class="card overflow-hidden lg:col-span-2">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3">
                <p class="flex-1 font-bold text-slate-800">
                    @if($baru)
                        {{ $jenis === 'laci' ? __(':n drawers need a label', ['n' => $laci->count()]) : __(':n parts need a label (new or moved)', ['n' => $part->count()]) }}
                    @else
                        {{ $jenis === 'laci' ? __(':n drawers in rack :rak', ['n' => $laci->count(), 'rak' => $rak]) : __(':n parts in rack :rak', ['n' => $part->count(), 'rak' => $rak]) }}
                    @endif
                </p>
                @if($jenis === 'part')
                    <input type="search" x-model="cari" class="input w-full py-2 text-sm sm:w-56" placeholder="{{ __('Filter name / part no...') }}">
                @endif
                <button type="button" class="btn-ghost px-3 py-1.5 text-xs" @click="pilih = @js($semuaKode->values())">{{ __('Select all') }}</button>
                <button type="button" class="btn-ghost px-3 py-1.5 text-xs" @click="pilih = []">{{ __('Select none') }}</button>
            </div>

            <div class="max-h-[60vh] divide-y divide-slate-100 overflow-y-auto scroll-thin">
                @if($jenis === 'laci')
                    @forelse($laci as $l)
                        <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-brand-50/40">
                            <input type="checkbox" name="pilih[]" value="{{ $l->bin }}" x-model="pilih" class="h-5 w-5 rounded border-slate-300 text-brand-600">
                            <span class="w-24 shrink-0 font-mono text-sm font-bold text-brand-700">{{ $l->bin }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-700">{{ implode(' · ', $l->contoh) }}</span>
                                <span class="text-[11px] text-slate-400">{{ __(':n parts', ['n' => $l->jumlah]) }}{{ $l->lokasi['laci'] ? '' : ' · ' . __('no drawer (rack shelf)') }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ $baru ? __('Every drawer already has a label.') : __('No drawers in this rack.') }}</p>
                    @endforelse
                @else
                    @forelse($part as $p)
                        <label class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-brand-50/40"
                               x-show="!cari || @js(mb_strtolower($p->part_no . ' ' . $p->deskripsi . ' ' . $p->lokasi_rak)).includes(cari.toLowerCase().trim())">
                            <input type="checkbox" name="pilih[]" value="{{ $p->id }}" x-model="pilih" class="h-5 w-5 rounded border-slate-300 text-brand-600">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-700">{{ $p->deskripsi }}</span>
                                <span class="font-mono text-[11px] text-slate-400">{{ $p->part_no }} · <b class="text-brand-600">{{ $p->lokasi_rak }}</b></span>
                                @if($baru)
                                    <span class="badge ml-1 {{ $p->label_dicetak_at ? 'bg-sky-50 text-sky-700' : 'bg-amber-100 text-amber-800' }}">{{ $p->label_dicetak_at ? __('Moved from :bin', ['bin' => $p->label_lokasi]) : __('New') }}</span>
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ $baru ? __('Every part already has a label.') : __('No parts in this rack.') }}</p>
                    @endforelse
                @endif
            </div>
        </div>

        @endif

        {{-- ============ PENGATURAN ============ --}}
        <div class="space-y-4">
            <div class="card space-y-4 p-5">
                <div>
                    <label class="label" for="ukuran">{{ __('Label size') }}</label>
                    <select id="ukuran" name="ukuran" class="input" x-model="ukuran">
                        @foreach($ukuran as $kode => [$w, $h])
                            <option value="{{ $kode }}" @selected($kode === 'sedang')>{{ __('size.' . $kode) }} · {{ $w }} × {{ $h }} mm</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="mode">{{ __('Printer') }}</label>
                    <select id="mode" name="mode" class="input">
                        <option value="a4">{{ __('A4 sticker sheet (cut by hand)') }}</option>
                        <option value="printer">{{ __('Label printer (one label per page)') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="salinan">{{ __('Copies per label') }}</label>
                    <input id="salinan" type="number" name="salinan" value="1" min="1" max="10" class="input w-24 font-mono" x-model.number="salinan">
                </div>
                @if($jenis === 'laci')
                    <label class="flex items-start gap-3 text-sm">
                        <input type="hidden" name="nama_part" value="0">
                        <input type="checkbox" name="nama_part" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                        <span class="text-slate-600">{{ __('Show the part names inside the drawer') }}</span>
                    </label>
                @endif
                <button class="btn-primary w-full py-3 text-base" :disabled="pilih.length === 0">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    @if($ringkas)
                        <span>{{ __('Print all') }}</span>
                    @else
                        <span x-text="@js(__('Print (:n selected)', ['n' => '__N__'])).replace('__N__', pilih.length)"></span>
                    @endif
                </button>
                @if($ringkas)
                    <p class="text-center text-xs font-semibold text-slate-500"
                       x-text="@js(__(':n labels · about :s A4 sticker sheets', ['n' => '__N__', 's' => '__S__'])).replace('__N__', ({{ $jumlahLabel }} * (salinan || 1)).toLocaleString('id-ID')).replace('__S__', Math.ceil({{ $jumlahLabel }} * (salinan || 1) / (@js($perLembar)[ukuran] || 16)))"></p>
                    <p class="text-center text-[11px] text-slate-400">{{ __('Large print jobs take a moment to prepare. Print a single rack first to test the scanner.') }}</p>
                @endif
            </div>

            <div class="card space-y-2 p-5 text-sm text-slate-600">
                <p class="font-bold text-slate-800">{{ __('Tips') }}</p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>{{ __('In the print dialog, set scale to 100% (Actual size) and margins to None so the barcode is not shrunk.') }}</li>
                    <li>{{ __('Drawer barcode = bin code. Scanning it at the kiosk shows everything in that drawer; during a stock count it filters to that drawer.') }}</li>
                    <li>{{ __('Part barcode = part number. Scanning it opens the item directly.') }}</li>
                    <li>{{ __('Test-scan one label before printing a whole rack.') }}</li>
                </ul>
            </div>
        </div>
    </form>
</x-shell>
