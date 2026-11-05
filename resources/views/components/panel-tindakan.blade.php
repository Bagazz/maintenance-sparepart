{{--
    Panel "Perlu tindakan" di atas Dashboard admin. Kartu merah berdenyut = harus segera, kuning = perlu perhatian.
    Tidak tampil sama sekali bila semuanya beres (diganti satu baris hijau).
--}}
@php
    $tindakan = app(\App\Services\TindakanDashboardService::class)->daftar();
    $kritis = collect($tindakan)->where('tingkat', 'kritis')->count();
    $ikon = [
        'tangan'  => 'M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11',
        'kosong'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'jam'     => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'cakram'  => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
        'dokumen' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'label'   => 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'kamera'  => 'M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zm12 4a3 3 0 11-6 0 3 3 0 016 0z',
        'gembok'  => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    ];
@endphp

@if(empty($tindakan))
    <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
        {{ __('All clear — nothing needs your action right now.') }}
    </div>
@else
    <section aria-labelledby="judul-tindakan" class="space-y-3">
        <div class="flex items-center gap-3">
            @if($kritis)
                <span class="relative flex h-3.5 w-3.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-500 opacity-75"></span>
                    <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-accent-600"></span>
                </span>
            @endif
            <h2 id="judul-tindakan" class="text-lg font-extrabold text-slate-900">{{ __('Needs your action') }}</h2>
            <span class="rounded-full px-2.5 py-0.5 text-sm font-extrabold {{ $kritis ? 'bg-accent-600 text-white' : 'bg-amber-400 text-amber-950' }}">{{ count($tindakan) }}</span>
        </div>

        <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            @foreach($tindakan as $t)
                @php $merah = $t['tingkat'] === 'kritis'; @endphp
                <a href="{{ $t['url'] }}"
                   class="group relative flex gap-4 overflow-hidden rounded-2xl p-4 shadow-card ring-1 transition hover:-translate-y-0.5 hover:shadow-lift {{ $merah ? 'bg-gradient-to-br from-accent-600 to-accent-700 text-white ring-accent-700' : 'bg-amber-50 text-amber-950 ring-amber-300' }}">
                    @if($merah)<span class="absolute right-3 top-3 flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white"></span></span>@endif
                    <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl {{ $merah ? 'bg-white/15' : 'bg-amber-200/70' }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon[$t['ikon']] }}"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-baseline gap-2">
                            <span class="font-mono text-2xl font-extrabold leading-none">{{ is_int($t['angka']) ? number_format($t['angka'], 0, ',', '.') : $t['angka'] }}</span>
                            <span class="font-extrabold leading-tight">{{ $t['judul'] }}</span>
                        </p>
                        <p class="mt-1 text-xs {{ $merah ? 'text-white/85' : 'text-amber-900/80' }}">{{ $t['isi'] }}</p>
                        <p class="mt-2 text-xs font-extrabold uppercase tracking-wider {{ $merah ? 'text-white' : 'text-amber-800' }}">{{ $t['tombol'] }} <span class="inline-block transition group-hover:translate-x-1">&rarr;</span></p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
