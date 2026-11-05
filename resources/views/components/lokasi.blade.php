@props(['item', 'besar' => false])
@php $l = $item->lokasi; @endphp

@if($besar)
    {{-- Versi besar: dipakai di halaman detail barang --}}
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-brand-100 bg-brand-50/60 p-4']) }}>
        <div class="flex items-center justify-between gap-2">
            <p class="kpi-label text-brand-700">{{ __('Item location') }}</p>
            @if($item->area_rak)<span class="badge bg-white text-brand-700 ring-1 ring-brand-100">{{ $item->area_rak }}</span>@endif
        </div>
        @if($l['rak'])
            <div class="mt-3 grid {{ $l['laci'] ? 'grid-cols-3' : 'grid-cols-2' }} gap-2">
                <div class="rounded-xl bg-white p-3 text-center ring-1 ring-brand-100">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Warehouse') }}</p>
                    <p class="font-mono text-xl font-bold text-slate-700">{{ $l['gudang'] }}</p>
                    <p class="truncate text-[10px] font-semibold text-slate-400">{{ __($l['nama_gudang'] ?? '') }}</p>
                </div>
                <div class="rounded-xl bg-white p-3 text-center ring-1 ring-brand-100">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Rack') }}</p>
                    <p class="font-mono text-3xl font-bold text-brand-700">{{ $l['rak'] }}</p>
                </div>
                @if($l['laci'])
                    <div class="rounded-xl bg-brand-600 p-3 text-center text-white shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-100">{{ __('Drawer') }}</p>
                        <p class="font-mono text-3xl font-bold">{{ $l['laci'] }}</p>
                    </div>
                @endif
            </div>
            <p class="mt-2 text-center font-mono text-xs font-bold text-slate-400">{{ __('Bin code:') }} {{ $l['kode'] }}</p>
        @else
            <p class="mt-2 font-mono text-3xl font-bold text-brand-700">{{ $item->lokasi_rak ?: '-' }}</p>
        @endif
    </div>
@else
    {{-- Versi ringkas: kartu hasil pencarian / tabel --}}
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 font-mono text-sm font-bold text-brand-700']) }} title="{{ $item->area_rak }}">
        <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        @if($l['rak'])
            {{ $l['rak'] }}@if($l['laci'])<span class="text-slate-300">/</span>{{ $l['laci'] }}@endif
        @else
            {{ $item->lokasi_rak ?: '-' }}
        @endif
    </span>
@endif
