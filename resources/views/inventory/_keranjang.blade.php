{{--
    Panel keranjang Parts Issuance. Dirender saat halaman dimuat dan dikirim ulang (JSON "html")
    setiap kali isi keranjang berubah lewat scan / tombol, sehingga halaman tidak perlu dimuat ulang.
    Sengaja tanpa directive Alpine: semua tombol ditangani oleh komponen induk (data-ajax / data-aksi).
--}}
@php
    $totalPcs = collect($keranjang)->sum('jumlah');
    $perluMesin = collect($keranjang)->contains(fn ($b) => empty($b['mesin']));
    $baruMasuk = $baruMasuk ?? session('baru_masuk');
    // Tandai baris yang akan menunggu persetujuan Kepala Spare Part (hanya teknisi; admin tidak perlu).
    $aturanSetuju = auth()->user()->role === 'admin' ? null : \App\Services\PersetujuanService::aturan();
    $hargaPart = $aturanSetuju ? \App\Models\Inventory::whereIn('part_no', collect($keranjang)->pluck('part_no'))->pluck('harga_beli_terakhir', 'part_no') : collect();
    $tandaSetuju = $aturanSetuju ? \App\Services\PersetujuanService::tandaiKeranjang($keranjang, $hargaPart, $aturanSetuju) : [];
    $jumlahPerluSetuju = count(array_filter($tandaSetuju));
@endphp
<div class="card overflow-hidden">
    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 font-extrabold text-slate-900">
                {{ __('Cart') }}
                <span class="flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-1.5 text-xs font-extrabold {{ count($keranjang) ? 'bg-accent-600 text-white' : 'bg-slate-100 text-slate-400' }}">{{ count($keranjang) }}</span>
            </p>
            <p class="text-xs font-semibold text-slate-400">
                {{ count($keranjang) ? __(':n items (:pcs pcs)', ['n' => count($keranjang), 'pcs' => $totalPcs]) : __('Step 3 — confirm everything at once') }}
            </p>
        </div>
        @if(count($keranjang))
            <button type="button" data-aksi="tanya-kosongkan"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-accent-200 hover:bg-accent-50 hover:text-accent-700">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                {{ __('Clear all') }}
            </button>
        @endif
    </div>

    <div class="max-h-[55vh] overflow-y-auto scroll-thin">
        @forelse($keranjang as $i => $baris)
            <div class="border-b border-slate-100 px-5 py-3.5 transition-colors duration-700 {{ $baruMasuk === $baris['part_no'] ? 'baris-baru bg-emerald-50' : '' }}">
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold leading-snug text-slate-800">{{ $baris['deskripsi'] }}</p>
                        <p class="mt-1 flex flex-wrap gap-1.5">
                            <span class="badge bg-slate-100 font-mono text-slate-500">{{ $baris['part_no'] }}</span>
                            @if(!empty($baris['lokasi']))<span class="badge bg-slate-100 font-mono text-slate-500">{{ $baris['lokasi'] }}</span>@endif
                            @if(!empty($baris['mesin']))<span class="badge bg-brand-50 text-brand-700">{{ $baris['mesin'] }}</span>@endif
                            @if(!empty($baris['no_pm']))<span class="badge bg-slate-100 font-mono text-slate-600">{{ $baris['no_pm'] }}</span>@endif
                            @if(!empty($tandaSetuju[$i]))<span class="badge bg-amber-100 text-amber-800">{{ __('Needs approval') }}</span>@endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('inventory.keranjang.hapus', $i) }}" data-ajax>@csrf
                        <button class="rounded-md p-1.5 text-slate-400 transition hover:bg-accent-50 hover:text-accent-600" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                        </button>
                    </form>
                </div>
                <div class="mt-2 inline-flex items-center rounded-lg border border-slate-200 bg-white">
                    <form method="POST" action="{{ route('inventory.keranjang.kurangi', $i) }}" data-ajax>@csrf<button class="h-8 w-8 rounded-l-lg font-bold text-slate-500 hover:bg-slate-100" aria-label="{{ __('Decrease') }}">−</button></form>
                    <span class="w-10 border-x border-slate-200 text-center font-mono text-sm font-bold leading-8">{{ $baris['jumlah'] }}</span>
                    <form method="POST" action="{{ route('inventory.keranjang.tambah-satu', $i) }}" data-ajax>@csrf<button class="h-8 w-8 rounded-r-lg font-bold text-slate-500 hover:bg-slate-100" aria-label="{{ __('Increase') }}">+</button></form>
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <p class="mt-2 text-sm font-semibold text-slate-400">{{ __('No items yet.') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Scan a part barcode — it goes straight into the cart.') }}</p>
            </div>
        @endforelse
    </div>

    @if(count($keranjang))
        <form method="POST" action="{{ route('inventory.ambil-semua') }}" class="space-y-3 bg-slate-50 p-4"
              onsubmit="this.querySelector('[type=submit]').disabled = true">
            @csrf
            @if($perluMesin)
                <div>
                    <label class="label" for="mesin">{{ __('Target machine') }}</label>
                    <select id="mesin" name="mesin" required class="input">
                        <option value="" disabled @selected(!old('mesin'))>{{ __('Choose machine...') }}</option>
                        @foreach($daftarMesin as $m)
                            <option value="{{ $m }}" @selected(old('mesin') === $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="no_pm">{{ __('PM / Job No') }} <span class="normal-case font-semibold text-slate-400">({{ __('optional') }})</span></label>
                    <input id="no_pm" name="no_pm" value="{{ old('no_pm') }}" class="input font-mono uppercase" placeholder="PM25xxx" maxlength="50">
                </div>
            @endif
            <button type="submit" class="btn-success w-full py-3.5 text-base">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                {{ __('Confirm :n items (:pcs pcs)', ['n' => count($keranjang), 'pcs' => $totalPcs]) }}
            </button>
            @if($jumlahPerluSetuju)
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-center text-[11px] font-semibold text-amber-800">{{ __(':n items need approval from the spare part head; the rest are recorded immediately.', ['n' => $jumlahPerluSetuju]) }}</p>
            @endif
            <p class="text-center text-[11px] font-semibold text-slate-400">{{ __('Stock is reduced immediately & recorded under :nama.', ['nama' => $karyawan?->nama ?? auth()->user()->name]) }}</p>
        </form>

        {{-- Bilah keranjang melayang di HP --}}
        <a href="#keranjang" class="fixed inset-x-4 bottom-4 z-30 flex items-center justify-between rounded-2xl bg-brand-700 px-5 py-3.5 text-white shadow-lift xl:hidden">
            <span class="font-bold">{{ __('Cart') }} · {{ __(':n items (:pcs pcs)', ['n' => count($keranjang), 'pcs' => $totalPcs]) }}</span>
            <span class="text-sm font-bold text-brand-100">{{ __('Confirm') }} &darr;</span>
        </a>
    @endif
</div>
