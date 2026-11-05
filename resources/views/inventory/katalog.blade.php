<x-shell :title="__('Photo Catalog')" :subtitle="__('Forgot the item name? Find it by category and photo')">

    <form method="GET" action="{{ route('inventory.katalog') }}" class="card flex flex-col gap-3 p-3 sm:flex-row sm:items-center">
        @if($kategori)<input type="hidden" name="kategori" value="{{ $kategori }}">@endif
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="{{ $q }}" class="input pl-10" placeholder="{{ $kategori && $kategori !== '__kosong' ? __('Search in :kategori...', ['kategori' => $kategori]) : __('Search name, size, brand, keyword...') }}">
        </div>
        <label class="flex items-center gap-2 px-1 text-sm font-semibold text-slate-600">
            <input type="checkbox" name="foto" value="1" @checked($hanyaFoto) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-200"> {{ __('With photo only') }}
        </label>
        <button class="btn-primary">{{ __('Search') }}</button>
    </form>

    @if($barang === null)
        {{-- ============ DAFTAR KATEGORI ============ --}}
        @if($daftarKategori->isEmpty())
            <div class="card p-10 text-center">
                <p class="text-lg font-bold text-slate-700">{{ __('No categories yet.') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Admins can set categories in Manage Parts. For now, use the search box above.') }}</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                @foreach($daftarKategori as $kat)
                    <a href="{{ route('inventory.katalog', ['kategori' => $kat->kategori]) }}" class="card group overflow-hidden transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift">
                        <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-gradient-to-br from-brand-50 to-slate-100">
                            @if($kat->cover)
                                <img src="{{ asset('storage/' . $kat->cover) }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            @else
                                <span class="text-4xl font-extrabold text-brand-200">{{ mb_substr($kat->kategori, 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between p-3">
                            <p class="font-bold text-slate-800 group-hover:text-brand-700">{{ $kat->kategori }}</p>
                            <span class="badge bg-slate-100 text-slate-500">{{ number_format($kat->jumlah) }}</span>
                        </div>
                    </a>
                @endforeach
                @if($tanpaKategori > 0)
                    <a href="{{ route('inventory.katalog', ['kategori' => '__kosong']) }}" class="card group flex flex-col items-center justify-center border-dashed p-4 text-center transition hover:border-brand-300">
                        <p class="text-2xl font-extrabold text-slate-300">{{ number_format($tanpaKategori) }}</p>
                        <p class="text-sm font-bold text-slate-500 group-hover:text-brand-700">{{ __('Others (uncategorized)') }}</p>
                    </a>
                @endif
            </div>
        @endif
    @else
        {{-- ============ GRID BARANG ============ --}}
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <a href="{{ route('inventory.katalog') }}" class="text-sm font-bold text-brand-600 hover:underline">&larr; {{ __('All categories') }}</a>
                <h2 class="mt-1 text-xl font-extrabold text-slate-900">
                    {{ $kategori === '__kosong' ? __('Uncategorized') : ($kategori ?: __('Search results')) }}
                    <span class="text-sm font-semibold text-slate-400">· {{ number_format($barang->total()) }} part</span>
                </h2>
            </div>
        </div>

        @if($barang->isEmpty())
            <div class="card p-10 text-center font-semibold text-slate-500">{{ __('No matching items.') }}</div>
        @else
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
                @foreach($barang as $b)
                    <a href="{{ route('inventory.index', ['q' => $b->part_no]) }}" class="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift">
                        <x-foto-barang :item="$b" class="aspect-[4/3]" />
                        <div class="flex flex-1 flex-col p-3">
                            <p class="line-clamp-2 text-[13px] font-bold leading-snug text-slate-800 group-hover:text-brand-700">{{ $b->deskripsi }}</p>
                            @if($b->kata_kunci)<p class="mt-0.5 line-clamp-1 text-[11px] text-slate-500">{{ $b->kata_kunci }}</p>@endif
                            <div class="mt-auto flex items-center justify-between pt-2">
                                <x-lokasi :item="$b" />
                                <span class="badge {{ $b->stock > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-accent-50 text-accent-700' }}">{{ $b->stock }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div>{{ $barang->links() }}</div>
        @endif
    @endif
</x-shell>
