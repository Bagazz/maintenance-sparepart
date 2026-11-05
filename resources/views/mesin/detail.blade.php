@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $r = $ringkas;
    $belumDipetakan = $job_peta->isEmpty() && str_starts_with($nama, 'PM');
    $sumber = ['issue' => 'ERP', 'app' => __('Kiosk'), 'app_return' => __('Return')];
@endphp
<x-shell :title="$nama" :subtitle="__('Machine detail') . ' · ' . $label" :kembali="route('mesin.biaya', ['periode' => $periode])">

    {{-- ============ IDENTITAS + PERIODE ============ --}}
    <div class="card flex flex-col gap-3 p-4 md:flex-row md:items-center">
        <div class="flex-1">
            @if($belumDipetakan)
                <p class="text-sm text-slate-600">
                    <span class="badge bg-amber-100 text-amber-800">{{ __('Job not mapped') }}</span>
                    {{ __('This is an ERP PM Job number, not yet linked to a machine name.') }}
                    @can('kelola')<a href="{{ route('mesin.index', ['q' => $nama]) }}" class="font-bold text-brand-600 hover:underline">{{ __('Map it to a machine') }} &rarr;</a>@endcan
                </p>
            @else
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('PM Jobs mapped to this machine') }}</p>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    @forelse($job_peta as $j)
                        <span class="badge bg-slate-100 font-mono text-slate-600">{{ $j }}</span>
                    @empty
                        <span class="text-sm text-slate-400">{{ __('None yet.') }}</span>
                    @endforelse
                </div>
            @endif
        </div>
        <form method="GET" action="{{ route('mesin.detail') }}" class="flex items-center gap-2">
            <input type="hidden" name="nama" value="{{ $nama }}">
            <select name="periode" class="input md:w-52" onchange="this.form.submit()" aria-label="{{ __('Period') }}">
                @foreach(['12-bulan' => __('Last 12 months'), 'fy' => __('Fiscal year to date'), 'semua' => __('All data')] as $v => $l)
                    <option value="{{ $v }}" @selected($periode === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- ============ KPI ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card bg-brand-700 p-4 text-white">
            <p class="kpi-label text-brand-100">{{ __('Spare part cost') }}</p>
            <p class="mt-1 whitespace-nowrap text-xl font-extrabold lg:text-2xl">{{ $rp($r->rp ?? 0) }}</p>
            <p class="text-[11px] font-semibold text-brand-200">
                @if($r && $r->ubah !== null)
                    {{ $r->ubah > 0 ? '▲' : '▼' }} {{ number_format(abs($r->ubah), 1) }}% {{ __('vs previous period') }}
                @else
                    {{ $label }}
                @endif
            </p>
        </div>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Cost rank') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $peringkat ? '#' . $peringkat : '–' }} <span class="text-sm font-semibold text-slate-400">/ {{ $dari }}</span></p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __('among machines / Jobs with usage') }}</p>
        </div>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Average per month') }}</p>
            <p class="mt-1 whitespace-nowrap text-xl font-extrabold text-slate-900 lg:text-2xl">{{ $rp($per_bulan) }}</p>
        </div>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Replacements') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ (int) ($r->frek ?? 0) }}</p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __(':n different parts', ['n' => $r->jumlah_part ?? 0]) }}</p>
        </div>
    </div>

    {{-- ============ TREN ============ --}}
    <section class="card p-5">
        <h2 class="text-lg font-extrabold text-slate-900">{{ __('Monthly cost trend') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Bars = spare part cost, line = number of replacements. A rising trend may mean the machine needs an overhaul.') }}</p>
        <div class="relative h-64"><canvas id="grafikMesin" aria-label="{{ __('Monthly cost trend') }}"></canvas></div>
    </section>

    {{-- ============ PART SERING DIGANTI ============ --}}
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 p-5">
            <h2 class="text-lg font-extrabold text-slate-900">{{ __('Parts replaced most often') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Parts replaced at least twice get an average interval and an expected next replacement — use it to plan preventive maintenance and keep stock ready.') }}</p>
        </div>
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Part') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Times') }}</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">{{ __('Cost') }}</th>
                        <th class="px-4 py-3">{{ __('Every') }}</th>
                        <th class="px-4 py-3">{{ __('Last') }}</th>
                        <th class="px-4 py-3">{{ __('Next expected') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Stock') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sering as $p)
                        @php $lewat = $p->berikut && $p->berikut->isPast(); $dekat = $p->berikut && !$lewat && $p->berikut->lte(now()->addDays(30)); @endphp
                        <tr class="hover:bg-brand-50/40">
                            <td class="px-4 py-3">
                                <a href="{{ route('inventory.index', ['q' => $p->part_no]) }}" class="font-bold text-slate-800 hover:text-brand-700">{{ $p->deskripsi }}</a>
                                <p class="font-mono text-[11px] text-slate-400">{{ $p->part_no }} · {{ $p->lokasi_rak }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-bold {{ $p->kali >= 3 ? 'text-accent-600' : 'text-slate-800' }}">{{ (int) $p->kali }}×</td>
                            <td class="px-4 py-3 text-right font-mono">{{ (int) $p->qty }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold">{{ $rp($p->rp) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $p->jarak ? __('± :n days', ['n' => $p->jarak]) : '–' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $p->terakhir->translatedFormat('d M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if($p->berikut)
                                    <span class="badge {{ $lewat ? 'bg-accent-50 text-accent-700' : ($dekat ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $p->berikut->translatedFormat('d M Y') }}{{ $lewat ? ' · ' . __('overdue') : ($dekat ? ' · ' . __('soon') : '') }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('needs ≥ 2 replacements') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $p->stock <= 0 ? 'text-accent-600' : 'text-slate-700' }}">{{ $p->stock }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center font-semibold text-slate-400">{{ __('No usage in this period yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- ============ PER JOB ============ --}}
        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 p-5">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('Cost per PM Job') }}</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($jobs as $j)
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <div>
                            <p class="font-mono font-bold text-slate-800">{{ $j->job }}</p>
                            <p class="text-[11px] text-slate-400">{{ __(':n transactions', ['n' => (int) $j->frek]) }} · {{ __('last :tgl', ['tgl' => \Illuminate\Support\Carbon::parse($j->terakhir)->translatedFormat('d M Y')]) }}</p>
                        </div>
                        <p class="whitespace-nowrap text-sm font-extrabold text-slate-800">{{ $rp($j->rp) }}</p>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('None.') }}</p>
                @endforelse
            </div>
        </section>

        {{-- ============ RIWAYAT ============ --}}
        <section class="card overflow-hidden xl:col-span-2">
            <div class="border-b border-slate-100 p-5">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('Recent spare part history') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Latest :n transactions in this period.', ['n' => 50]) }}</p>
            </div>
            <div class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto scroll-thin">
                @forelse($riwayat as $h)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-20 shrink-0 text-xs font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse($h->tgl)->translatedFormat('d M Y') }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-800">{{ $h->deskripsi }}</p>
                            <p class="text-[11px] text-slate-400"><span class="font-mono">{{ $h->part_no }}</span>@if($h->job) · <span class="font-mono">{{ $h->job }}</span>@endif · {{ $sumber[$h->sumber] ?? $h->sumber }}@if($h->peminjam) · {{ $h->peminjam }}@endif</p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-sm font-bold {{ $h->qty < 0 ? 'text-emerald-600' : '' }}">{{ $h->qty < 0 ? '+' . abs($h->qty) : '−' . $h->qty }}</p>
                            <p class="whitespace-nowrap text-[11px] font-semibold text-slate-500">{{ $rp($h->rp) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No usage in this period yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <x-slot:scripts>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = @json($tren);
                const el = document.getElementById('grafikMesin');
                if (!el || !window.Chart) return;
                const sat = @js(['m' => __('unit.billion'), 'jt' => __('unit.million'), 'rb' => __('unit.thousand')]);
                const ringkas = v => v >= 1e9 ? (v / 1e9).toFixed(1) + ' ' + sat.m : v >= 1e6 ? Math.round(v / 1e6) + ' ' + sat.jt : v >= 1e3 ? Math.round(v / 1e3) + ' ' + sat.rb : v;
                new Chart(el, {
                    data: {
                        labels: data.map(d => d.label),
                        datasets: [
                            { type: 'bar', label: @js(__('Cost')), data: data.map(d => d.rp), backgroundColor: '#e4002b', borderRadius: 6, maxBarThickness: 34, yAxisID: 'y' },
                            { type: 'line', label: @js(__('Replacements')), data: data.map(d => d.frek), borderColor: '#004e92', backgroundColor: '#004e92', borderWidth: 2.5, pointRadius: 3, cubicInterpolationMode: 'monotone', yAxisID: 'y1' },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { weight: 600 } } },
                            tooltip: { backgroundColor: '#0f172a', padding: 12, cornerRadius: 10, callbacks: { label: c => c.dataset.yAxisID === 'y' ? c.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(c.parsed.y)) : c.dataset.label + ': ' + c.parsed.y } },
                        },
                        scales: {
                            y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef2f7' }, ticks: { color: '#94a3b8', callback: v => 'Rp ' + ringkas(v) } },
                            y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { color: '#004e92', precision: 0 } },
                            x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 600 } } },
                        },
                    },
                });
            });
        </script>
    </x-slot:scripts>
</x-shell>
