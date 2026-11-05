@props(['item'])
{{-- Thumbnail barang: foto bila ada, kalau belum ada pakai inisial kategori --}}
<div {{ $attributes->merge(['class' => 'relative overflow-hidden bg-slate-100 flex items-center justify-center']) }}>
    @if($item->foto_barang)
        <img src="{{ $item->foto_barang_url }}" alt="{{ $item->deskripsi }}" loading="lazy" class="h-full w-full object-cover">
    @else
        <div class="flex flex-col items-center gap-1 text-slate-300">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span class="text-[9px] font-bold uppercase tracking-widest">{{ $item->kategori ?? __('No photo yet') }}</span>
        </div>
    @endif
</div>
