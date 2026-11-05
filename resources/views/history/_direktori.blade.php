{{-- Direktori mesin / operator. Variabel: $judul, $keterangan, $daftar, $param (nama filter history) --}}
<x-shell :title="$judul" :subtitle="$keterangan" :kembali="route('history.index')">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse($daftar as $nama)
            <a href="{{ route('history.index', ['tab' => 'out', $param => $nama]) }}" class="card group flex items-center gap-4 p-4 transition hover:border-brand-300 hover:shadow-lift">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 font-extrabold text-brand-600 group-hover:bg-brand-600 group-hover:text-white">
                    {{ mb_substr($nama, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <p class="truncate font-bold text-slate-800">{{ $nama }}</p>
                    <p class="text-xs font-semibold text-slate-400">{{ __('View history') }} &rarr;</p>
                </div>
            </a>
        @empty
            <div class="card col-span-full p-10 text-center font-semibold text-slate-400">{{ __('No records yet.') }}</div>
        @endforelse
    </div>
</x-shell>
