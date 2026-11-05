@php
    $isAdmin = auth()->user()->role === 'admin';
    $bolehAmbil = Gate::allows('ambil');   // viewer hanya melihat: tanpa form ambil & keranjang
    $totalKeranjang = collect($keranjang)->sum('jumlah');
@endphp
<x-shell :title="$bolehAmbil ? __('Take Items') : __('Find Items')" :subtitle="!$bolehAmbil ? __('Search parts, check stock and rack location (view only)') : ($isAdmin ? __('Search, check location, and record spare part take-outs') : __('Step 2 of 3 — find items & add them to the cart'))">

    {{-- ============ BILAH TEKNISI (kiosk) ============ --}}
    @if($karyawan)
        <div class="card flex flex-wrap items-center gap-4 p-4"
             x-data="sesiKiosk({{ 10 * 60 }}, '{{ route('inventory.ganti-peminjam') }}')" x-init="mulai()">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-lg font-extrabold text-white">
                {{ mb_substr($karyawan->nama, 0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Technician') }}</p>
                <p class="truncate text-lg font-extrabold text-slate-900">{{ $karyawan->nama }}
                    @if($karyawan->nomor_kartu)<span class="ml-1 font-mono text-xs font-bold text-slate-400">#{{ $karyawan->nomor_kartu }}</span>@endif
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Session ends in') }}</p>
                <p class="font-mono text-lg font-bold" :class="sisa < 60 ? 'text-accent-600' : 'text-slate-700'" x-text="label"></p>
            </div>
            <a href="{{ route('inventory.ganti-peminjam') }}" class="btn-ghost">{{ __('Sign out') }}</a>
        </div>
    @endif

    {{-- ============ STATUS PERMINTAAN TEKNISI (7 hari terakhir) ============ --}}
    @if($permintaanSaya->isNotEmpty())
        <div class="card divide-y divide-slate-100 overflow-hidden">
            <p class="bg-slate-50 px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('Your approval requests') }}</p>
            @foreach($permintaanSaya as $p)
                @php [$warna, $label] = match ($p->status) {
                    'disetujui' => ['bg-emerald-50 text-emerald-700', __('Approved — you may take the items')],
                    'ditolak'   => ['bg-accent-50 text-accent-700', __('Rejected')],
                    default     => ['bg-amber-100 text-amber-800', __('Waiting for approval')],
                }; @endphp
                <div class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                    <span class="badge {{ $warna }}">{{ $label }}</span>
                    <span class="font-mono text-xs font-bold text-slate-500">{{ $p->kode }}</span>
                    <span class="min-w-0 flex-1 truncate font-semibold text-slate-700">{{ $p->items->map(fn ($i) => $i->deskripsi . ' x' . $i->jumlah)->implode(', ') }}</span>
                    @if($p->alasan_tolak)<span class="w-full text-xs text-accent-700">{{ __('Reason:') }} {{ $p->alasan_tolak }}</span>@endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 {{ $bolehAmbil ? 'xl:grid-cols-[minmax(0,1fr)_360px]' : '' }}">
        <div class="min-w-0 space-y-6">

            {{-- ============ PENCARIAN / SCAN ============ --}}
            <div x-data="cariBarang('{{ route('inventory.suggest') }}', @js(request('q', '')), @js($bolehAmbil ? route('inventory.keranjang.scan') : null))" @fokus-cari.window="fokus()" class="relative z-20">
                <form x-ref="form" @submit.prevent="enter()" method="GET" action="{{ route('inventory.index') }}"
                      class="card flex items-center gap-2 p-2 focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-100">
                    <div class="pl-3 text-brand-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <input x-ref="input" type="text" name="q" x-model="q" autocomplete="off" @if(!isset($item)) autofocus @endif
                           @input.debounce.250ms="saran()" @focus="buka = daftar.length > 0" @click.outside="buka = false"
                           @keydown.arrow-down.prevent="pindah(1)" @keydown.arrow-up.prevent="pindah(-1)"
                           @keydown.enter.prevent="enter()" @keydown.escape="buka = false"
                           placeholder="{{ $bolehAmbil ? __('Scan a part barcode — it goes straight into the cart. Or type a name / keyword...') : __('Scan a drawer barcode, or type name / part no / keyword...') }}"
                           class="min-w-0 flex-1 border-0 bg-transparent py-3 text-base font-bold text-slate-900 placeholder:font-medium placeholder:text-slate-400 focus:outline-none focus:ring-0 sm:text-lg">
                    <button class="btn-primary px-5 py-3 sm:px-7" :class="sibuk && 'opacity-80'"><svg x-show="sibuk" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>{{ __('Search') }}</button>
                </form>

                <div x-show="buka" x-cloak x-transition.opacity
                     class="card absolute left-0 right-0 top-full mt-2 max-h-[60vh] overflow-y-auto scroll-thin p-1 shadow-lift">
                    <template x-for="(s, i) in daftar" :key="s.part_no">
                        <button type="button" @click="pilih(s)" @mouseenter="aktif = i"
                                :class="aktif === i ? 'bg-brand-50' : ''"
                                class="flex w-full items-center gap-3 rounded-xl p-2.5 text-left">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                <template x-if="s.foto"><img :src="s.foto" alt="" class="h-full w-full object-cover"></template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-800" x-text="s.deskripsi"></p>
                                <p class="flex flex-wrap items-center gap-x-2 text-xs font-semibold text-slate-400">
                                    <span class="font-mono" x-text="s.part_no"></span>
                                    <span class="font-mono font-bold text-brand-600" x-text="s.lokasi_rak"></span>
                                </p>
                            </div>
                            <span class="badge" :class="s.stock > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-accent-50 text-accent-700'" x-text="@js(__('Stock')) + ' ' + s.stock"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- ============ DETAIL BARANG ============ --}}
            @if(isset($item))
                <div class="card overflow-hidden">
                    <div class="grid lg:grid-cols-2">
                        {{-- Foto --}}
                        <div class="border-b border-slate-100 bg-slate-50 p-4 lg:border-b-0 lg:border-r"
                             x-data="{ foto: @js($item->foto_barang_url ?? $item->foto_lokasi_url) }">
                            <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                <template x-if="foto"><a :href="foto" target="_blank"><img :src="foto" alt="{{ $item->deskripsi }}" class="h-full w-full object-contain"></a></template>
                                <template x-if="!foto">
                                    <div class="flex h-full flex-col items-center justify-center text-slate-300">
                                        <svg class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <p class="mt-2 text-xs font-bold uppercase tracking-widest">{{ __('No photo yet') }}</p>
                                    </div>
                                </template>
                            </div>
                            @if($item->foto_barang && $item->foto_lokasi)
                                <div class="mt-3 flex gap-2">
                                    <button type="button" @click="foto = @js($item->foto_barang_url)" class="h-14 w-20 overflow-hidden rounded-lg ring-2" :class="foto === @js($item->foto_barang_url) ? 'ring-brand-500' : 'ring-transparent'"><img src="{{ $item->foto_barang_url }}" alt="{{ __('Item') }}" class="h-full w-full object-cover"></button>
                                    <button type="button" @click="foto = @js($item->foto_lokasi_url)" class="relative h-14 w-20 overflow-hidden rounded-lg ring-2" :class="foto === @js($item->foto_lokasi_url) ? 'ring-brand-500' : 'ring-transparent'"><img src="{{ $item->foto_lokasi_url }}" alt="{{ __('Rack') }}" class="h-full w-full object-cover"><span class="absolute inset-x-0 bottom-0 bg-black/60 text-[9px] font-bold uppercase text-white">{{ __('Rack') }}</span></button>
                                </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="space-y-4 p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge bg-slate-100 font-mono text-slate-600">{{ $item->part_no }}</span>
                                @if($item->kategori)
                                    <a href="{{ route('inventory.katalog', ['kategori' => $item->kategori]) }}" class="badge bg-brand-50 text-brand-700 hover:bg-brand-100">{{ $item->kategori }}</a>
                                @endif
                                @if($isAdmin)
                                    <a href="{{ route('inventory.edit', ['inventory' => $item, 'kembali' => url()->full()]) }}" class="ml-auto text-xs font-bold text-brand-600 hover:underline">{{ __('Edit photo & data') }}</a>
                                    <a href="{{ route('label.cetak', ['jenis' => 'part', 'pilih' => [$item->id], 'ukuran' => 'sedang']) }}" target="_blank" class="text-xs font-bold text-brand-600 hover:underline">{{ __('Print label') }}</a>
                                @endif
                            </div>
                            <div>
                                <h2 class="text-2xl font-extrabold leading-tight text-slate-900 sm:text-3xl">{{ $item->deskripsi }}</h2>
                                @if($item->kata_kunci)<p class="mt-1 text-sm text-slate-500">{{ __('Also known as:') }} {{ $item->kata_kunci }}</p>@endif
                            </div>

                            <x-lokasi :item="$item" besar />
                            @if(!empty($item->lokasi_lain))
                                <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
                                    {{ __('Also stored in:') }}
                                    @foreach($item->lokasi_lain as $l)
                                        <span class="badge bg-white font-mono text-brand-700 ring-1 ring-slate-200">{{ $l['bin'] }} · {{ $l['qty'] }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-center justify-between rounded-2xl border p-4 {{ $item->stock > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-accent-100 bg-accent-50' }}">
                                <p class="kpi-label {{ $item->stock > 0 ? 'text-emerald-700' : 'text-accent-700' }}">{{ __('Stock available') }}</p>
                                <p class="font-mono text-3xl font-bold {{ $item->stock > 0 ? 'text-emerald-700' : 'text-accent-600' }}">{{ number_format($item->stock) }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Form pengambilan --}}
                    @if($bolehAmbil)
                    <form method="POST" action="{{ route('inventory.keranjang.tambah') }}" x-data="{ jumlah: 1, maks: {{ max(0, $item->stock) }} }"
                          class="border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6">
                        @csrf
                        <input type="hidden" name="part_no" value="{{ $item->part_no }}">

                        {{-- Mesin & No PM dipilih sekali di keranjang saat konfirmasi --}}
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="sm:w-48">
                                <label class="label">{{ __('Quantity') }}</label>
                                <div class="flex items-center rounded-xl border border-slate-300 bg-white">
                                    <button type="button" @click="jumlah = Math.max(1, jumlah - 1)" class="px-3 py-2.5 text-lg font-bold text-slate-500 hover:text-brand-600" aria-label="{{ __('Decrease') }}">−</button>
                                    <input name="jumlah" type="number" min="1" :max="maks" x-model.number="jumlah" class="w-full border-0 bg-transparent p-0 text-center font-mono text-lg font-bold focus:ring-0">
                                    <button type="button" @click="jumlah = Math.min(maks, jumlah + 1)" class="px-3 py-2.5 text-lg font-bold text-slate-500 hover:text-brand-600" aria-label="{{ __('Increase') }}">+</button>
                                </div>
                            </div>
                            <button class="btn-primary flex-1 py-3.5 text-base" @disabled($item->stock <= 0)>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4v16m8-8H4"/></svg>
                                {{ __('Add to cart') }}
                            </button>
                        </div>
                        <p class="mt-2 text-xs font-semibold text-slate-400">{{ __('The target machine and PM No are chosen once in the cart, when you confirm.') }}</p>
                        @if($item->stock <= 0)
                            <p class="mt-3 text-sm font-bold text-accent-600">{{ __('Out of stock — report to the spare part head for restock.') }}</p>
                        @endif
                    </form>
                    @endif
                </div>

            {{-- ============ BANYAK HASIL ============ --}}
            @elseif($hasil->isNotEmpty())
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">{{ __(':n items match ":q"', ['n' => $hasil->count() >= 48 ? '48+' : $hasil->count(), 'q' => request('q')]) }}</h2>
                        <p class="text-sm text-slate-500">{{ __('Pick the one you mean. Add another word (size/brand) to narrow it down.') }}</p>
                    </div>
                    <a href="{{ route('inventory.katalog', ['q' => request('q')]) }}" class="text-sm font-bold text-brand-600 hover:underline">{{ __('View in catalog') }} &rarr;</a>
                </div>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 2xl:grid-cols-4">
                    @foreach($hasil as $h)
                        <a href="{{ route('inventory.index', ['q' => $h->part_no]) }}" class="card group flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift">
                            <x-foto-barang :item="$h" class="aspect-[4/3]" />
                            <div class="flex flex-1 flex-col p-3">
                                <p class="line-clamp-2 text-sm font-bold leading-snug text-slate-800 group-hover:text-brand-700">{{ $h->deskripsi }}</p>
                                <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $h->part_no }}</p>
                                <div class="mt-auto flex items-center justify-between pt-3">
                                    <x-lokasi :item="$h" />
                                    <span class="badge {{ $h->stock > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-accent-50 text-accent-700' }}">{{ __('Stock') }} {{ $h->stock }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

            {{-- ============ TIDAK KETEMU ============ --}}
            @elseif(request('q'))
                <div class="card px-6 py-14 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-accent-50 text-accent-600">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM8 10h4"/></svg>
                    </div>
                    <h2 class="text-xl font-extrabold text-slate-900">{{ __('":q" not found', ['q' => request('q')]) }}</h2>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ __('Try a shorter word (e.g. "relay" or "6205"), or find it by its shape in the photo catalog.') }}</p>
                    <a href="{{ route('inventory.katalog') }}" class="btn-primary mt-6">{{ __('Open photo catalog') }}</a>
                </div>

            {{-- ============ AWAL ============ --}}
            @else
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach([
                        [__('Scan barcode'), $bolehAmbil ? __('Click here, then scan the part label — the item goes straight into the cart.') : __('Point the scanner at the drawer barcode label — the item opens instantly.'), 'M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8'],
                        [__('Type freely'), __('No label? Click here and type the name in any order: "omron relay", "bearing 6205".'), 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
                        [__('Forgot the name?'), __('Browse the photo catalog by category, then match the shape.'), 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ] as $i => [$judul, $isi, $path])
                        @php $isiKotak = '<div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '"/></svg></div><p class="font-bold text-slate-800">' . e($judul) . '</p><p class="mt-1 text-sm text-slate-500">' . e($isi) . '</p>'; @endphp
                        @if($i === 2)
                            <a href="{{ route('inventory.katalog') }}" class="card block p-5 transition hover:border-brand-300 hover:shadow-lift">{!! $isiKotak !!}</a>
                        @else
                            {{-- Klik = aktifkan kolom pencarian, siap menerima scan / ketikan --}}
                            <button type="button" x-data @click="$dispatch('fokus-cari')" class="card block w-full p-5 text-left transition hover:border-brand-300 hover:shadow-lift focus:outline-none focus:ring-4 focus:ring-brand-100">{!! $isiKotak !!}</button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============ KERANJANG ============ --}}
        @if($bolehAmbil)
        <aside id="keranjang" class="xl:sticky xl:top-28 xl:self-start"
               x-data="panelKeranjang({{ count($keranjang) }}, {{ $totalKeranjang }}, '{{ route('inventory.keranjang.kosongkan') }}', '{{ route('inventory.keranjang.urung') }}')"
               @keranjang-baru.window="ganti($event.detail)" @click="klik($event)" @submit="kirim($event)">
            <div x-ref="isi">@include('inventory._keranjang', ['baruMasuk' => session('baru_masuk')])</div>

            {{-- Dialog konfirmasi kosongkan keranjang (di dalam halaman, bukan pop-up browser) --}}
            <template x-teleport="body">
                <div x-show="tanyaKosong" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
                     @keydown.escape.window="tanyaKosong = false">
                    <div x-show="tanyaKosong" x-transition.opacity class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" @click="tanyaKosong = false"></div>
                    <div x-show="tanyaKosong" x-transition.scale.95 role="dialog" aria-modal="true" aria-labelledby="judul-kosongkan"
                         class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="flex gap-4 p-6">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                            </div>
                            <div>
                                <h3 id="judul-kosongkan" class="text-lg font-extrabold text-slate-900">{{ __('Clear the cart?') }}</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ __('All') }} <span class="font-bold text-slate-800" x-text="baris + ' ' + @js(__('items')) + ' (' + pcs + ' pcs)'"></span>
                                    {{ __('will be removed from the cart. Stock is not affected — nothing has been taken yet.') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                            <button type="button" class="btn-ghost" @click="tanyaKosong = false" x-ref="batal">{{ __('Cancel') }}</button>
                            <button type="button" class="btn-danger" @click="kosongkan()" :disabled="sibuk">{{ __('Yes, clear cart') }}</button>
                        </div>
                    </div>
                </div>
            </template>
        </aside>
        @endif
    </div>
    @if($bolehAmbil)<div class="h-16 xl:hidden" aria-hidden="true"></div>@endif

    {{-- Notifikasi singkat hasil scan / aksi keranjang --}}
    <div x-data="notifKeranjang('{{ route('inventory.keranjang.urung') }}')" @notif-keranjang.window="tampil($event.detail)"
         class="pointer-events-none fixed inset-x-4 top-20 z-[60] flex flex-col items-center gap-2 sm:left-auto sm:right-6 sm:w-96 sm:items-stretch">
        <template x-for="n in daftar" :key="n.id">
            <div x-transition.opacity class="pointer-events-auto flex w-full items-start gap-3 rounded-xl border bg-white p-3.5 text-sm font-semibold shadow-lift"
                 :class="{ 'border-emerald-200 text-emerald-800': n.jenis === 'sukses', 'border-accent-100 text-accent-700': n.jenis === 'galat', 'border-slate-200 text-slate-700': n.jenis === 'info' }">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-white"
                      :class="{ 'bg-emerald-600': n.jenis === 'sukses', 'bg-accent-600': n.jenis === 'galat', 'bg-slate-500': n.jenis === 'info' }">
                    <svg x-show="n.jenis === 'sukses'" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                    <svg x-show="n.jenis !== 'sukses'" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 7v6m0 4h.01"/></svg>
                </span>
                <p class="flex-1" x-text="n.pesan"></p>
                <button type="button" x-show="n.urung" @click="urung(n)" class="shrink-0 font-extrabold text-brand-600 hover:underline">{{ __('Undo') }}</button>
                <button type="button" @click="tutup(n.id)" class="shrink-0 text-slate-400 hover:text-slate-600" aria-label="{{ __('Close') }}">&times;</button>
            </div>
        </template>
    </div>

    <x-slot:scripts>
        <script>
            const tokenCsrf = () => document.querySelector('meta[name=csrf-token]')?.content;

            // POST ke endpoint keranjang; balasan JSON berisi panel keranjang terbaru.
            async function kirimKeranjang(url, data = {}) {
                const body = new FormData();
                Object.entries(data).forEach(([k, v]) => body.append(k, v));
                const r = await fetch(url, {
                    method: 'POST', body,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tokenCsrf() },
                });
                const json = await r.json().catch(() => ({}));
                if (r.status === 401 && json.url) { window.location.href = json.url; return null; }
                if (r.status === 419) { window.location.reload(); return null; }   // sesi kedaluwarsa
                if (!r.ok) throw new Error(json.message || r.statusText);
                return json;
            }

            // Sebarkan hasil ke panel keranjang & notifikasi.
            function terapkanKeranjang(json) {
                if (!json) return;
                if (json.html !== undefined) window.dispatchEvent(new CustomEvent('keranjang-baru', { detail: json }));
                if (json.pesan) window.dispatchEvent(new CustomEvent('notif-keranjang', { detail: json }));
            }

            function cariBarang(urlSaran, awal, urlScan) {
                return {
                    q: awal, daftar: [], buka: false, aktif: -1, _ctrl: null,
                    antrian: [], sibuk: false,
                    init() {
                        // Halaman dibuka dengan teks lama di kolom: blok teksnya supaya scan berikutnya
                        // MENGGANTI teks itu, bukan tersambung di belakangnya.
                        this.$nextTick(() => { if (document.activeElement === this.$refs.input) this.$refs.input.select(); });

                        // Scanner = keyboard. Ketikan di luar kolom isian (mis. setelah mengklik tombol)
                        // dialihkan ke kolom pencarian, supaya hasil scan tidak hilang di mana pun kursornya.
                        window.addEventListener('keydown', (e) => {
                            const el = document.activeElement;
                            const sedangMengisi = el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);
                            if (sedangMengisi || e.ctrlKey || e.metaKey || e.altKey || e.key.length !== 1) return;
                            if (document.querySelector('[role=dialog]')?.offsetParent) return;   // dialog terbuka
                            e.preventDefault();
                            window.getSelection()?.removeAllRanges();
                            this.q = e.key;
                            this.$refs.input.focus();
                        });
                    },
                    // Dipanggil dari kotak "Scan barcode" / "Pencarian bebas": aktifkan kolom & blok teks lama.
                    fokus() {
                        this.$refs.input.focus();
                        this.$refs.input.select();
                    },
                    async saran() {
                        if (this.q.trim().length < 2) { this.daftar = []; this.buka = false; return; }
                        this._ctrl?.abort();
                        this._ctrl = new AbortController();
                        try {
                            const r = await fetch(urlSaran + '?q=' + encodeURIComponent(this.q), { signal: this._ctrl.signal });
                            this.daftar = await r.json();
                            this.buka = this.daftar.length > 0 && this.q.trim().length >= 2;
                            this.aktif = -1;
                        } catch (e) { /* dibatalkan / offline: abaikan */ }
                    },
                    pindah(n) { if (!this.buka) return; this.aktif = Math.max(0, Math.min(this.daftar.length - 1, this.aktif + n)); },
                    // Klik saran = lihat detail barang dulu (tidak langsung masuk keranjang).
                    pilih(s) { this.buka = false; window.location.href = this.$refs.form.action + '?q=' + encodeURIComponent(s.part_no); },
                    // Enter (dari scanner atau tombol Cari).
                    enter() {
                        if (this.buka && this.aktif >= 0) return this.pilih(this.daftar[this.aktif]);
                        const kode = this.q.trim();
                        if (!kode) return;
                        // Viewer tidak punya keranjang: cukup cari.
                        if (!urlScan) { window.location.href = this.$refs.form.action + '?q=' + encodeURIComponent(kode); return; }
                        // Kolom langsung dikosongkan & kode masuk antrean, jadi scan beruntun
                        // (berapa pun cepatnya) tidak pernah tersambung menjadi satu kode panjang.
                        this.q = ''; this.daftar = []; this.buka = false; this._ctrl?.abort();
                        this.antrian.push(kode);
                        this.proses();
                    },
                    async proses() {
                        if (this.sibuk) return;
                        this.sibuk = true;
                        try {
                            while (this.antrian.length) {
                                const kode = this.antrian.shift();
                                let json;
                                try { json = await kirimKeranjang(urlScan, { kode }); }
                                catch (e) { terapkanKeranjang({ jenis: 'galat', pesan: @js(__('Connection problem — scan ":kode" again.')).replace(':kode', kode) }); continue; }
                                if (!json) return;
                                // Kata bebas (bukan barcode): buka hasil pencarian, kecuali masih ada scan yang menunggu.
                                if (json.status === 'cari') {
                                    if (!this.antrian.length) { window.location.href = json.url; return; }
                                    terapkanKeranjang({ jenis: 'galat', pesan: @js(__('Barcode ":kode" is not registered in the parts master.')).replace(':kode', kode) });
                                    continue;
                                }
                                terapkanKeranjang(json);
                            }
                        } finally {
                            this.sibuk = false;
                        }
                    },
                };
            }

            function panelKeranjang(baris, pcs, urlKosongkan, urlUrung) {
                return {
                    baris, pcs, tanyaKosong: false, sibuk: false,
                    ganti(json) {
                        this.$refs.isi.innerHTML = json.html;
                        this.baris = json.baris; this.pcs = json.pcs;
                        if (!this.baris) this.tanyaKosong = false;
                        // Sorot sebentar baris yang baru masuk, lalu gulir ke sana.
                        const baru = this.$refs.isi.querySelector('.baris-baru');
                        if (baru) {
                            baru.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                            setTimeout(() => baru.classList.remove('bg-emerald-50'), 1200);
                        }
                    },
                    klik(e) {
                        if (e.target.closest('[data-aksi=tanya-kosongkan]')) {
                            this.tanyaKosong = true;
                            this.$nextTick(() => this.$refs.batal?.focus());
                        }
                    },
                    // Tombol + / − / hapus: kirim tanpa memuat ulang halaman.
                    async kirim(e) {
                        const form = e.target;
                        if (!form.hasAttribute('data-ajax')) return;   // tombol Konfirmasi tetap submit biasa
                        e.preventDefault();
                        if (this.sibuk) return;
                        this.sibuk = true;
                        try { terapkanKeranjang(await kirimKeranjang(form.action)); }
                        catch (err) { form.submit(); }
                        finally { this.sibuk = false; }
                    },
                    async kosongkan() {
                        this.sibuk = true;
                        try { terapkanKeranjang(await kirimKeranjang(urlKosongkan)); }
                        finally { this.sibuk = false; this.tanyaKosong = false; }
                    },
                };
            }

            function notifKeranjang(urlUrung) {
                let urut = 0;
                return {
                    daftar: [],
                    tampil(d) {
                        const n = { id: ++urut, pesan: d.pesan, jenis: d.jenis || 'sukses', urung: !!d.urung };
                        this.daftar = [n, ...this.daftar].slice(0, 3);
                        setTimeout(() => this.tutup(n.id), n.urung ? 7000 : (n.jenis === 'galat' ? 6000 : 3000));
                        // Bunyi pendek beda untuk berhasil / gagal, supaya teknisi tidak perlu melihat layar.
                        try {
                            const ctx = this._ctx ??= new (window.AudioContext || window.webkitAudioContext)();
                            const o = ctx.createOscillator(), g = ctx.createGain();
                            o.frequency.value = n.jenis === 'galat' ? 220 : 880;
                            g.gain.value = 0.05;
                            o.connect(g); g.connect(ctx.destination);
                            o.start(); o.stop(ctx.currentTime + (n.jenis === 'galat' ? 0.35 : 0.08));
                        } catch (e) { /* audio tidak tersedia */ }
                    },
                    tutup(id) { this.daftar = this.daftar.filter(n => n.id !== id); },
                    async urung(n) {
                        this.tutup(n.id);
                        terapkanKeranjang(await kirimKeranjang(urlUrung));
                    },
                };
            }

            // Hitung mundur sesi kiosk; setiap sentuhan/ketikan memperpanjang sesi.
            function sesiKiosk(detik, urlKeluar) {
                return {
                    batas: detik, sisa: detik, label: '',
                    mulai() {
                        const reset = () => { this.sisa = this.batas; };
                        ['click', 'keydown', 'touchstart'].forEach(e => document.addEventListener(e, reset, { passive: true }));
                        const tik = () => {
                            this.sisa--;
                            const m = Math.max(0, Math.floor(this.sisa / 60)), s = Math.max(0, this.sisa % 60);
                            this.label = m + ':' + String(s).padStart(2, '0');
                            if (this.sisa <= 0) window.location.href = urlKeluar;
                        };
                        tik(); setInterval(tik, 1000);
                    },
                };
            }
        </script>
    </x-slot:scripts>
</x-shell>
