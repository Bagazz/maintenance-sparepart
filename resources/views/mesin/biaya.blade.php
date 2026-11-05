@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $maks = max(1, (float) ($daftar->max('rp') ?? 1));
@endphp
<x-shell :title="__('Machine Costs')" :subtitle="__('Spare part cost per machine — click a machine for its history, frequently replaced parts and trend')"
         xdata="{ cari: '' }">

    {{-- ============ PERIODE + RINGKASAN ============ --}}
    <div class="grid gap-4 md:grid-cols-4">
        <form method="GET" action="{{ route('mesin.biaya') }}" class="card p-4">
            <label class="label" for="periode">{{ __('Period') }}</label>
            <select id="periode" name="periode" class="input" onchange="this.form.submit()">
                @foreach(['12-bulan' => __('Last 12 months'), 'fy' => __('Fiscal year to date'), 'semua' => __('All data')] as $v => $l)
                    <option value="{{ $v }}" @selected($periode === $v)>{{ $l }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-[11px] font-semibold text-slate-400">{{ $awal->translatedFormat('d M Y') }} – {{ $akhir->translatedFormat('d M Y') }}</p>
        </form>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Total usage cost') }}</p>
            <p class="mt-1 whitespace-nowrap text-xl font-extrabold text-slate-900 lg:text-2xl">{{ $rp($total) }}</p>
        </div>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Machines / Jobs with usage') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $daftar->count() }}</p>
        </div>
        <a @can('kelola') href="{{ route('mesin.index', ['filter' => 'belum']) }}" @endcan class="card p-4 transition hover:shadow-lift {{ $belum ? 'border-amber-200 bg-amber-50' : '' }}">
            <p class="kpi-label {{ $belum ? 'text-amber-800' : '' }}">{{ __('Jobs not mapped to a machine') }}</p>
            <p class="mt-1 text-2xl font-extrabold {{ $belum ? 'text-amber-700' : 'text-slate-900' }}">{{ $belum }}</p>
            @can('kelola')<p class="text-[11px] font-semibold text-slate-500">{{ __('Map them so costs group per machine') }} &rarr;</p>@endcan
        </a>
    </div>

    {{-- ============ PERINGKAT ============ --}}
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-4 py-3">
            <p class="flex-1 font-bold text-slate-800">{{ __('Ranking by cost') }} · {{ $label }}</p>
            <input type="search" x-model="cari" class="input w-full py-2 text-sm sm:w-64" placeholder="{{ __('Search machine or Job...') }}">
        </div>
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">{{ __('Machine / Job') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Replacements') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Different parts') }}</th>
                        <th class="px-4 py-3">{{ __('Cost') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('vs previous period') }}</th>
                        <th class="px-4 py-3">{{ __('Last') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($daftar as $i => $m)
                        <tr class="cursor-pointer hover:bg-brand-50/40" x-show="!cari || @js(mb_strtolower($m->nama)).includes(cari.toLowerCase().trim())"
                            onclick="location.href = @js(route('mesin.detail', ['nama' => $m->nama, 'periode' => $periode]))">
                            <td class="px-4 py-3 font-bold text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('mesin.detail', ['nama' => $m->nama, 'periode' => $periode]) }}" class="font-bold text-slate-800 hover:text-brand-700 {{ $m->belum_dipetakan ? 'font-mono' : '' }}">{{ $m->nama }}</a>
                                <p class="text-[11px] text-slate-400">
                                    @if($m->belum_dipetakan)<span class="font-bold text-amber-600">{{ __('Job not mapped') }}</span>@else{{ __(':n PM Jobs', ['n' => $m->jumlah_job]) }}@endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ (int) $m->frek }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $m->jumlah_part }}</td>
                            <td class="px-4 py-3">
                                <p class="whitespace-nowrap font-extrabold text-slate-800">{{ $rp($m->rp) }}</p>
                                <div class="mt-1 h-1.5 w-40 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-accent-500" style="width: {{ round($m->rp / $maks * 100) }}%"></div></div>
                            </td>
                            <td class="px-4 py-3 text-right text-xs font-bold">
                                @if($m->ubah === null)
                                    <span class="text-slate-300">{{ $m->rp_sebelum > 0 ? '' : __('new') }}</span>
                                @else
                                    <span class="{{ $m->ubah > 0 ? 'text-accent-600' : 'text-emerald-600' }}">{{ $m->ubah > 0 ? '▲' : '▼' }} {{ number_format(abs($m->ubah), 1) }}%</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($m->terakhir)->translatedFormat('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center font-semibold text-slate-400">{{ __('No usage in this period yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-shell>
