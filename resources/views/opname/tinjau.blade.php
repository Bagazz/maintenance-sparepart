@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $r = $ringkasan;
@endphp
<x-shell :title="__('Count result :kode', ['kode' => $opname->kode])"
         :subtitle="__('Rack') . ' ' . $opname->kode_rak . ' · ' . $opname->petugas . ' · ' . $opname->created_at->translatedFormat('d M Y')"
         :kembali="$opname->berjalan && Gate::allows('kelola') ? route('opname.hitung', $opname) : route('opname.index')">

    <x-slot:actions>
        <button type="button" onclick="window.print()" class="btn-ghost hidden sm:inline-flex print:hidden">{{ __('Print') }}</button>
    </x-slot:actions>

    {{-- ============ STATUS ============ --}}
    @if($opname->status === 'diposting')
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            <b>{{ __('Posted :tgl.', ['tgl' => $opname->diposting_at->translatedFormat('d M Y H:i')]) }}</b>
            {{ __('App stock has been adjusted. Post the same adjustments in ERP — the next ERP stock import overwrites app stock.') }}
        </div>
    @elseif($opname->status === 'batal')
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm font-semibold text-slate-600">{{ __('This session was cancelled; stock was not changed.') }}</div>
    @endif

    {{-- ============ KPI ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card p-4">
            <p class="kpi-label">{{ __('Counted') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $r['dihitung'] }} <span class="text-sm font-semibold text-slate-400">/ {{ $r['total'] }}</span></p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __(':n match the system', ['n' => $r['cocok']]) }}</p>
        </div>
        <div class="card p-4 {{ $r['kurang_rp'] > 0 ? 'border-accent-200 bg-accent-50' : '' }}">
            <p class="kpi-label {{ $r['kurang_rp'] > 0 ? 'text-accent-700' : '' }}">{{ __('Missing') }}</p>
            <p class="mt-1 whitespace-nowrap text-base font-extrabold {{ $r['kurang_rp'] > 0 ? 'text-accent-600' : 'text-slate-900' }} sm:text-xl lg:text-2xl">−{{ $rp($r['kurang_rp']) }}</p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __(':n parts less than system', ['n' => $r['kurang_n']]) }}</p>
        </div>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Surplus') }}</p>
            <p class="mt-1 whitespace-nowrap text-base font-extrabold text-emerald-700 sm:text-xl lg:text-2xl">+{{ $rp($r['lebih_rp']) }}</p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __(':n parts more than system', ['n' => $r['lebih_n']]) }}</p>
        </div>
        <div class="card p-4 {{ $r['bersih_rp'] < 0 ? 'bg-brand-700 text-white' : '' }}">
            <p class="kpi-label {{ $r['bersih_rp'] < 0 ? 'text-brand-100' : '' }}">{{ __('Net variance') }}</p>
            <p class="mt-1 whitespace-nowrap text-base font-extrabold sm:text-xl lg:text-2xl">{{ $r['bersih_rp'] < 0 ? '−' : '+' }}{{ $rp(abs($r['bersih_rp'])) }}</p>
            <p class="text-[11px] font-semibold {{ $r['bersih_rp'] < 0 ? 'text-brand-200' : 'text-slate-500' }}">{{ __('Physical − system × latest price') }}</p>
        </div>
    </div>

    {{-- ============ DAFTAR SELISIH ============ --}}
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="font-extrabold text-slate-900">{{ __('Parts with a variance') }}</p>
            <p class="text-xs text-slate-500">{{ __('Largest loss first. These are the lines to enter as stock adjustments in ERP.') }}</p>
        </div>
        {{-- HP: kartu ringkas --}}
        <div class="divide-y divide-slate-100 sm:hidden print:hidden">
            @forelse($selisih as $i)
                <div class="flex items-start justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800">{{ $i->inventory->deskripsi }}</p>
                        <p class="font-mono text-[11px] text-slate-400">{{ $i->inventory->part_no }} · <span class="font-bold text-brand-600">{{ $i->inventory->lokasi_rak }}</span></p>
                        <p class="text-xs font-semibold text-slate-500">{{ __('System') }} {{ $i->stok_sistem }} → {{ __('Physical') }} {{ $i->qty_fisik }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-mono text-lg font-bold {{ $i->selisih < 0 ? 'text-accent-600' : 'text-emerald-700' }}">{{ $i->selisih > 0 ? '+' : '' }}{{ $i->selisih }}</p>
                        <p class="whitespace-nowrap text-xs font-bold {{ $i->selisih < 0 ? 'text-accent-600' : 'text-emerald-700' }}">{{ $i->selisih < 0 ? '−' : '+' }}{{ $rp(abs($i->selisih_rp)) }}</p>
                    </div>
                </div>
            @empty
                <p class="py-10 text-center text-sm font-semibold text-emerald-600">{{ __('No variances — every counted part matches the system.') }}</p>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto scroll-thin sm:block print:block">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Part') }}</th>
                        <th class="px-4 py-3">{{ __('Bin') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('System') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Physical') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Variance') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($selisih as $i)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800">{{ $i->inventory->deskripsi }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $i->inventory->part_no }}@if($i->tambahan) · <span class="font-sans font-bold text-amber-600">{{ __('added during count') }}</span>@endif</p>
                                @if($i->catatan)<p class="text-[11px] text-slate-500">{{ $i->catatan }}</p>@endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-700">{{ $i->inventory->lokasi_rak }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $i->stok_sistem }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ $i->qty_fisik }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $i->selisih < 0 ? 'text-accent-600' : 'text-emerald-700' }}">{{ $i->selisih > 0 ? '+' : '' }}{{ $i->selisih }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold {{ $i->selisih < 0 ? 'text-accent-600' : 'text-emerald-700' }}">{{ $i->selisih < 0 ? '−' : '+' }}{{ $rp(abs($i->selisih_rp)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center font-semibold text-emerald-600">{{ __('No variances — every counted part matches the system.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ============ BELUM DIHITUNG ============ --}}
    @if($belum->isNotEmpty())
        <details class="card p-5" @if($opname->berjalan) open @endif>
            <summary class="cursor-pointer font-bold text-amber-700">{{ __(':n parts not counted — their stock will not be changed', ['n' => $belum->count()]) }}</summary>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach($belum->take(200) as $i)
                    <li class="flex justify-between gap-3 py-2">
                        <span class="text-slate-700">{{ $i->inventory->deskripsi }} <span class="font-mono text-[11px] text-slate-400">{{ $i->inventory->part_no }}</span></span>
                        <span class="font-mono text-xs font-bold text-brand-700">{{ $i->inventory->lokasi_rak }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- ============ AKSI ============ --}}
    @if($opname->berjalan && Gate::allows('kelola'))
        <div class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center print:hidden">
            <div class="flex-1 text-sm text-slate-600">
                <p class="font-bold text-slate-800">{{ __('Post the result?') }}</p>
                <p>{{ __('App stock of each counted part becomes the physical count (system stock changes from take-outs after counting are kept). This cannot be undone.') }}</p>
            </div>
            <a href="{{ route('opname.hitung', $opname) }}" class="btn-ghost">{{ __('Continue counting') }}</a>
            <form method="POST" action="{{ route('opname.posting', $opname) }}" x-data="{ kirim: false }"
                  @submit="if (!confirm(@js(__('Post this count and adjust stock?')))) { $event.preventDefault(); return; } kirim = true">
                @csrf
                <button class="btn-success w-full" :disabled="kirim || {{ $r['dihitung'] ? 'false' : 'true' }}">{{ __('Post adjustments') }}</button>
            </form>
        </div>
        <form method="POST" action="{{ route('opname.batal', $opname) }}" class="text-right print:hidden"
              onsubmit="return confirm(@js(__('Cancel this count session? Entered quantities will be discarded.')))">
            @csrf
            <button class="text-xs font-bold text-slate-400 hover:text-accent-600">{{ __('Cancel this session') }}</button>
        </form>
    @endif
</x-shell>
