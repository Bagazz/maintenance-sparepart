@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Dashboard')" :subtitle="__('Spare part cost, M03 warehouse stock & maintenance activity')"
         xdata="{ showCostModal: false, showMesinModal: false, activeMesinName: '', activeMesinData: [], allMesinData: {{ json_encode($detailMesin) }} }">

    {{-- ============ PERLU TINDAKAN (admin): persetujuan, stok habis, PR terlambat, backup, label, akun terkunci ============ --}}
    @can('kelola')
        <x-panel-tindakan />
    @endcan

    {{-- ============ KPI UTAMA ============ --}}
    <div class="grid gap-4 md:grid-cols-3">
        <div class="card relative overflow-hidden bg-brand-700 p-5 text-white">
            <div class="absolute -bottom-10 -right-10 h-36 w-36 rounded-full bg-white/5"></div>
            <p class="kpi-label text-brand-100">{{ __('M03 warehouse stock value') }}</p>
            <p class="mt-2 whitespace-nowrap text-xl font-extrabold lg:text-2xl 2xl:text-3xl">{{ $rp($totalAset) }}</p>
            <p class="mt-1 text-xs font-semibold text-brand-200">{{ __(':n part types · latest ERP price', ['n' => number_format($totalSparePart)]) }}</p>
        </div>

        <button @click="showCostModal = true" class="card group p-5 text-left transition hover:border-brand-300 hover:shadow-lift">
            <div class="flex items-center justify-between">
                <p class="kpi-label">{{ __('Cost this month') }}</p>
                <span class="text-xs font-bold text-brand-600 opacity-0 transition group-hover:opacity-100">{{ __('Details') }} &rarr;</span>
            </div>
            <p class="mt-2 whitespace-nowrap text-xl font-extrabold text-slate-900 lg:text-2xl 2xl:text-3xl">{{ $rp($biayaBulanIni) }}</p>
            <p class="mt-1 text-xs font-bold">
                @if($biayaBulanIni <= 0)
                    <span class="text-slate-400">{{ __('No data this month yet · last month :rp', ['rp' => $rp($biayaBulanLalu)]) }}</span>
                @elseif($trenBiaya > 0)
                    <span class="text-accent-600">▲ {{ number_format(abs($trenBiaya), 1) }}% {{ __('vs last month') }}</span>
                @elseif($trenBiaya < 0)
                    <span class="text-emerald-600">▼ {{ number_format(abs($trenBiaya), 1) }}% {{ __('vs last month') }}</span>
                @else
                    <span class="text-slate-400">{{ __('Last month :rp', ['rp' => $rp($biayaBulanLalu)]) }}</span>
                @endif
            </p>
        </button>

        <a href="{{ route('analitik.index') }}" class="card group p-5 transition hover:border-brand-300 hover:shadow-lift">
            <div class="flex items-center justify-between">
                <p class="kpi-label">{{ __(':fy to date', ['fy' => $labelFY]) }}</p>
                <span class="text-xs font-bold text-brand-600 opacity-0 transition group-hover:opacity-100">{{ __('Analytics') }} &rarr;</span>
            </div>
            <p class="mt-2 whitespace-nowrap text-xl font-extrabold text-slate-900 lg:text-2xl 2xl:text-3xl">{{ $rp($biayaFY) }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-400">{{ __('Usage · purchases :rp', ['rp' => $rp($beliFY)]) }}</p>
        </a>
    </div>

    {{-- ============ INDIKATOR OPERASIONAL ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach([
            [__('Restock needed'), $restock['jumlah'], __('Suggested buy :rp', ['rp' => $rp($restock['rp'])]), route('restock.index'), $restock['jumlah'] > 0 ? 'amber' : false, 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z'],
            [__('Purchases to review'), $beliPerluDicek, $labelFY . ' · ' . __('see analytics'), route('analitik.index') . '#kualitas', $beliPerluDicek > 0 ? 'accent' : false, 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            [__('Issued without scan'), $rekonStat['erp_tanpa_scan'], __('30 days') . ' · ' . $rp($rekonStat['erp_tanpa_scan_rp']), route('rekonsiliasi.index'), $rekonStat['erp_tanpa_scan'] > 0 ? 'accent' : false, 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
            [__('Kiosk scans today'), $aktivitasHariIni, __('Take-outs by technicians'), route('history.index'), false, 'M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8'],
        ] as [$label, $nilai, $ket, $url, $sorot, $ikon])
            <a href="{{ $url }}" class="card group flex items-center gap-4 p-4 transition hover:border-brand-300 hover:shadow-lift
                {{ $sorot === 'accent' ? 'border-accent-200 bg-accent-50' : '' }} {{ $sorot === 'amber' ? 'border-amber-200 bg-amber-50' : '' }}">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                    {{ $sorot === 'accent' ? 'bg-accent-600 text-white' : ($sorot === 'amber' ? 'bg-amber-500 text-white' : 'bg-brand-50 text-brand-600 group-hover:bg-brand-600 group-hover:text-white') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                    <p class="text-2xl font-extrabold leading-tight {{ $sorot === 'accent' ? 'text-accent-600' : ($sorot === 'amber' ? 'text-amber-700' : 'text-slate-900') }}">{{ number_format($nilai) }}</p>
                    <p class="truncate text-[11px] font-semibold text-slate-400">{{ $ket }}</p>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ============ GRAFIK & TOP MESIN ============ --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="font-extrabold text-slate-900">{{ __('Spare part purchases vs usage') }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ __('Last 12 months · by original transaction date') }}</p>
                </div>
                <a href="{{ route('analitik.index') }}" class="text-xs font-bold text-brand-600 hover:underline">{{ __('Full analytics') }} &rarr;</a>
            </div>
            <div class="relative h-72 sm:h-80"><canvas id="grafikBiaya" aria-label="{{ __('12-month cost chart') }}"></canvas></div>
        </div>

        <div class="card flex flex-col overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="font-extrabold text-slate-900">{{ __('Top 5 most costly machines') }}</p>
                <p class="text-xs font-semibold text-slate-500">{{ __('Last 90 days · by Rp value · click for details') }}</p>
                @if($jobBelumDipetakan && Gate::allows('kelola'))
                    <a href="{{ route('mesin.index', ['filter' => 'belum']) }}" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100">
                        {{ __(':n PM Jobs not yet mapped to a machine', ['n' => $jobBelumDipetakan]) }} &rarr;
                    </a>
                @endif
            </div>
            <div class="flex-1 divide-y divide-slate-100">
                @php $maksMesin = max(1, (float) ($mesinTerboros->max('total_biaya') ?? 1)); @endphp
                @forelse($mesinTerboros as $i => $m)
                    <button type="button"
                            @click="activeMesinName = @js($m->machine); activeMesinData = allMesinData[@js($m->machine)] || []; showMesinModal = true"
                            class="group block w-full px-5 py-3 text-left transition hover:bg-brand-50/50">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-extrabold text-slate-500 group-hover:bg-brand-600 group-hover:text-white">{{ $i + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-800 {{ $m->belum_dipetakan ? 'font-mono' : '' }}">{{ $m->machine }}</p>
                                    <p class="text-[11px] text-slate-400">
                                        {{ __(':n transactions', ['n' => $m->freq]) }} · {{ $m->total_qty }} qty
                                        @if($m->jumlah_job) · {{ $m->jumlah_job }} Job PM @endif
                                        @if($m->belum_dipetakan) · <span class="font-bold text-amber-600">{{ __('Job not mapped') }}</span> @endif
                                    </p>
                                </div>
                            </div>
                            <p class="shrink-0 text-sm font-extrabold text-accent-600">{{ $rp($m->total_biaya) }}</p>
                        </div>
                        <div class="ml-10 mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-accent-500" style="width: {{ round($m->total_biaya / $maksMesin * 100) }}%"></div>
                        </div>
                    </button>
                @empty
                    <div class="flex h-full flex-col items-center justify-center py-12 text-center">
                        <p class="text-sm font-bold text-slate-400">{{ __('No usage in the last 90 days.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <p class="text-xs text-slate-400">
        {{ __('Data note: official cost comes from the ERP Issue sheet; take-outs recorded in this app are counted for dates after the latest ERP data.') }}
        {{ __('ERP records PM Job numbers (e.g. PM25094); machine names come from the mapping in the') }} @can('kelola')<a href="{{ route('mesin.index') }}" class="font-semibold underline">{{ __('Machines & PM Jobs') }}</a>@else<b>{{ __('Machines & PM Jobs') }}</b>@endcan {{ __('menu.') }}
    </p>

    {{-- ============ MODAL RINCIAN MESIN ============ --}}
    <div x-show="showMesinModal" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @keydown.escape.window="showMesinModal = false">
        <div @click.outside="showMesinModal = false" class="card flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <p class="text-lg font-extrabold text-slate-900" x-text="activeMesinName"></p>
                    <p class="text-xs font-semibold text-slate-500">{{ __('Parts issued in the last 90 days') }}</p>
                    <a :href="@js(route('mesin.detail')) + '?nama=' + encodeURIComponent(activeMesinName)" class="mt-1 inline-block text-xs font-bold text-brand-600 hover:underline">{{ __('Open machine detail') }} &rarr;</a>
                </div>
                <button @click="showMesinModal = false" class="rounded-lg p-2 text-xl text-slate-400 hover:bg-slate-100" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="overflow-y-auto scroll-thin">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr><th class="px-6 py-3">{{ __('Part') }}</th><th class="px-6 py-3 text-right">Qty</th><th class="px-6 py-3 text-right">{{ __('Cost') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="part in activeMesinData" :key="part.part">
                            <tr>
                                <td class="px-6 py-3 font-semibold text-slate-800" x-text="part.part"></td>
                                <td class="px-6 py-3 text-right font-mono font-bold" x-text="part.qty"></td>
                                <td class="px-6 py-3 text-right font-bold text-slate-700" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(part.biaya || 0))"></td>
                            </tr>
                        </template>
                        <tr x-show="activeMesinData.length === 0"><td colspan="3" class="py-10 text-center font-semibold text-slate-400">{{ __('No usage data found.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ MODAL RINCIAN BIAYA ============ --}}
    <div x-show="showCostModal" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @keydown.escape.window="showCostModal = false">
        <div @click.outside="showCostModal = false" class="card flex max-h-[85vh] w-full max-w-5xl flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <p class="text-lg font-extrabold text-slate-900">{{ __('Cost details: this month vs last month') }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ __('Top 10 parts by cost per month') }}</p>
                </div>
                <button @click="showCostModal = false" class="rounded-lg p-2 text-xl text-slate-400 hover:bg-slate-100" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="grid flex-1 overflow-y-auto scroll-thin md:grid-cols-2 md:divide-x md:divide-slate-100">
                @foreach([
                    [__('Last month'), $biayaBulanLalu, $rincianBiayaBulanLalu, 'text-slate-700'],
                    [__('This month'), $biayaBulanIni, $rincianBiayaBulanIni, $trenBiaya > 0 ? 'text-accent-600' : 'text-brand-700'],
                ] as [$judul, $total, $rincian, $warna])
                    <div class="p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="badge bg-slate-100 text-slate-600">{{ $judul }}</span>
                            <p class="text-xl font-extrabold {{ $warna }}">{{ $rp($total) }}</p>
                        </div>
                        <div class="space-y-2">
                            @forelse($rincian as $r)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $r->deskripsi }}</p>
                                        <p class="text-[11px] text-slate-400">{{ __(':n qty used', ['n' => $r->total_qty]) }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-extrabold text-slate-700">{{ $rp($r->total_biaya) }}</p>
                                </div>
                            @empty
                                <p class="py-8 text-center text-sm font-semibold text-slate-400">{{ __('No spending.') }}</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = @json($grafikData ?? []);   // urut lama -> baru
                const ctx = document.getElementById('grafikBiaya');
                if (!ctx || !window.Chart) return;

                const sat = @js(['m' => __('unit.billion'), 'jt' => __('unit.million'), 'rb' => __('unit.thousand')]);
                const rupiahPendek = v => v >= 1e9 ? (v / 1e9).toFixed(1) + ' ' + sat.m : v >= 1e6 ? Math.round(v / 1e6) + ' ' + sat.jt : v >= 1e3 ? Math.round(v / 1e3) + ' ' + sat.rb : v;
                Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.label),
                        datasets: [
                            { label: @js(__('Purchases')), data: data.map(d => d.beli), backgroundColor: '#1567b3', borderRadius: 6, maxBarThickness: 30 },
                            { label: @js(__('Usage')), data: data.map(d => d.total), backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 30 },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { weight: 600 } } },
                            tooltip: {
                                backgroundColor: '#0f172a', padding: 12, cornerRadius: 10,
                                callbacks: { label: c => c.dataset.label + ': ' + new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(c.parsed.y) },
                            },
                        },
                        scales: {
                            y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef2f7' }, ticks: { color: '#94a3b8', font: { weight: 600 }, callback: v => 'Rp ' + rupiahPendek(v) } },
                            x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 600 } } },
                        },
                    },
                });
            });
        </script>
    </x-slot:scripts>
</x-shell>
