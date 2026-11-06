@php
    use App\Services\AnalitikBiayaService as A;
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $rpRingkas = function ($n) {
        $abs = abs($n);
        $s = $abs >= 1e9 ? number_format($abs / 1e9, 2, ',', '.') . ' ' . __('unit.billion') : ($abs >= 1e6 ? number_format($abs / 1e6, 1, ',', '.') . ' ' . __('unit.million') : number_format($abs, 0, ',', '.'));
        return ($n < 0 ? '−' : '') . 'Rp ' . $s;
    };
    $r = $laporan['ringkasan'];
    $c = $laporan['cakupan'];
    $k = $laporan['kualitas'];
    $m = $laporan['mengendap'];
    $arus = $laporan['arus'];
    $warnaStatus = [
        'emerald' => ['bg-emerald-50', 'text-emerald-700', 'border-emerald-200', 'bg-emerald-500'],
        'amber'   => ['bg-amber-50', 'text-amber-800', 'border-amber-200', 'bg-amber-500'],
        'accent'  => ['bg-accent-50', 'text-accent-700', 'border-accent-200', 'bg-accent-600'],
        'slate'   => ['bg-slate-100', 'text-slate-600', 'border-slate-200', 'bg-slate-400'],
    ];
    $berisikoRp = ($k['per_status']['perlu_dicek']['rp'] ?? 0) + ($k['per_status']['terlalu_cepat']['rp'] ?? 0) + ($k['per_status']['belum_terpakai']['rp'] ?? 0);
    $barisTampil = $filterStatus ? $k['baris']->where('status', $filterStatus) : $k['baris'];
    $urlStatus = fn ($s) => request()->fullUrlWithQuery(['status' => $s]) . '#kualitas';
@endphp
<x-shell :title="__('Cost Analytics')" :subtitle="__('Spare part money flow, purchase quality & idle money') . ' · ' . $labelPeriode">

    {{-- ============ PERIODE ============ --}}
    <form method="GET" action="{{ route('analitik.index') }}" class="card flex flex-wrap items-end gap-3 p-4" x-data="{ p: @js($periode) }">
        <div class="min-w-[220px]">
            <label class="label" for="periode">{{ __('Period') }}</label>
            <select id="periode" name="periode" x-model="p" class="input" @change="if (p !== 'kustom') $el.form.submit()">
                @foreach($pilihan as $v => $l)
                    <option value="{{ $v }}" @selected($periode === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <template x-if="p === 'kustom'">
            <div class="flex flex-wrap items-end gap-3">
                <div><label class="label">{{ __('From') }}</label><input type="date" name="dari" value="{{ $awal->toDateString() }}" class="input"></div>
                <div><label class="label">{{ __('To') }}</label><input type="date" name="sampai" value="{{ $akhir->toDateString() }}" class="input"></div>
                <button class="btn-primary">{{ __('Apply') }}</button>
            </div>
        </template>
        <p class="ml-auto text-xs font-semibold text-slate-500">{{ $awal->translatedFormat('d M Y') }} – {{ $akhir->min(now())->translatedFormat('d M Y') }}</p>
    </form>

    {{-- ============ LAPORAN BULANAN (PRESDIR) ============ --}}
    <form method="GET" action="{{ route('analitik.laporan') }}" class="card flex flex-col gap-3 border-brand-200 p-4 md:flex-row md:items-center">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="flex-1">
            <p class="font-bold text-slate-800">{{ __('Monthly report for the President Director') }}</p>
            <p class="text-xs text-slate-500">{{ __('Key points, month vs last month & FY, 12-month trend, top machines, purchase quality, restock and losses. PDF to read or forward, Excel to dig into, CSV for other systems.') }}</p>
        </div>
        <select name="bulan" class="input md:w-56" aria-label="{{ __('Month') }}">
            @foreach($pilihanBulan as $v => $l)
                <option value="{{ $v }}" @selected($v === $bulanBawaan)>{{ $l }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button name="format" value="pdf" class="btn-primary flex-1 whitespace-nowrap">PDF</button>
            <button name="format" value="xlsx" class="btn-success flex-1 whitespace-nowrap">Excel</button>
            <button name="format" value="csv" class="btn-ghost flex-1 whitespace-nowrap" title="{{ __('CSV files (one per table) in a ZIP') }}">CSV</button>
        </div>
    </form>

    {{-- ============ PERINGATAN CAKUPAN DATA ============ --}}
    @if(!$c['cukup_tahun'])
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="font-bold">{{ __('Only :n days of ERP data', ['n' => $c['hari']]) }}{{ $c['awal'] ? ' (' . __('since :tgl', ['tgl' => $c['awal']->translatedFormat('d M Y')]) . ')' : '' }} — {{ __('some figures are still provisional.') }}</p>
                <p>{{ __('Purchases & usage are already accurate. The "too early", dead stock, and overstock assessments are only valid after ≥ 12 months of data.') }}
                    @can('kelola')<a href="{{ route('import.index') }}" class="font-bold underline">{{ __('Import ERP data since July 2024') }}</a> {{ __('for a full per-fiscal-year analysis.') }}@endcan</p>
            </div>
        </div>
    @endif

    {{-- ============ KPI ============ --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
            <p class="kpi-label">{{ __('Purchases') }}</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-extrabold text-slate-900 xl:text-[1.7rem]">{{ $rp($r['pembelian']) }}</p>
            <p class="text-xs font-semibold text-slate-400">{{ __('Money spent on incoming goods') }}</p>
        </div>
        <div class="card p-5">
            <p class="kpi-label">{{ __('Usage') }}</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-extrabold text-slate-900 xl:text-[1.7rem]">{{ $rp($r['pemakaian']) }}</p>
            <p class="text-xs font-semibold text-slate-400">{{ __('Cost consumed by machines / Jobs') }}{{ $r['rasio'] !== null ? ' · ' . __(':pct% of purchases', ['pct' => $r['rasio']]) : '' }}</p>
        </div>
        <div class="card p-5 {{ $r['tertahan'] > 0 ? 'border-amber-200 bg-amber-50' : '' }}">
            <p class="kpi-label {{ $r['tertahan'] > 0 ? 'text-amber-800' : '' }}">{{ __('Money held in the warehouse') }}</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-extrabold {{ $r['tertahan'] > 0 ? 'text-amber-700' : 'text-emerald-700' }} xl:text-[1.7rem]">{{ $rp($r['tertahan']) }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ __('Purchases − usage this period') }}</p>
        </div>
        <a href="#kualitas" class="card p-5 transition hover:shadow-lift {{ $berisikoRp > 0 ? 'border-accent-200 bg-accent-50' : '' }}">
            <p class="kpi-label {{ $berisikoRp > 0 ? 'text-accent-700' : '' }}">{{ __('Purchases to review') }}</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-extrabold {{ $berisikoRp > 0 ? 'text-accent-600' : 'text-slate-900' }} xl:text-[1.7rem]">{{ $rp($berisikoRp) }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ $k['total_rp'] > 0 ? __(':pct% of purchase value', ['pct' => round($berisikoRp / $k['total_rp'] * 100)]) : __('No purchases') }}</p>
        </a>
    </div>

    {{-- ============ ARUS UANG ============ --}}
    <section class="card p-5 sm:p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('Spare part money flow') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Where purchase money goes: consumed by machines, or held in the warehouse.') }}</p>
            </div>
            @if($arus['tersedia'] ?? false)
                <p class="text-xs font-semibold text-slate-400">{{ __('Computed from the stock snapshot :dari to :sampai', ['dari' => $arus['tgl_awal']->translatedFormat('d M Y'), 'sampai' => $arus['tgl_akhir']->translatedFormat('d M Y')]) }}</p>
            @endif
        </div>

        @if($arus['tersedia'] ?? false)
            <div class="grid items-stretch gap-3 lg:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr]">
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <p class="kpi-label">{{ __('Opening stock') }}</p>
                    <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $rpRingkas($arus['awal']) }}</p>
                    <p class="text-xs text-slate-400">{{ $arus['tgl_awal']->translatedFormat('d M Y') }}</p>
                </div>
                <div class="flex items-center justify-center text-2xl font-extrabold text-emerald-600">+</div>
                <div class="rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-100">
                    <p class="kpi-label text-brand-700">{{ __('Purchases') }}</p>
                    <p class="mt-1 text-xl font-extrabold text-brand-700">{{ $rpRingkas($arus['beli']) }}</p>
                    <p class="text-xs text-brand-500">{{ __('Money out') }}</p>
                </div>
                <div class="flex items-center justify-center text-2xl font-extrabold text-accent-600">−</div>
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
                    <p class="kpi-label text-emerald-700">{{ __('Usage') }}</p>
                    <p class="mt-1 text-xl font-extrabold text-emerald-700">{{ $rpRingkas($arus['pakai']) }}</p>
                    <p class="text-xs text-emerald-600">{{ __('Cost to machines / Jobs') }}</p>
                </div>
                <div class="flex items-center justify-center text-2xl font-extrabold text-slate-400">=</div>
                <div class="rounded-2xl bg-brand-700 p-4 text-white">
                    <p class="kpi-label text-brand-100">{{ __('Estimated closing stock') }}</p>
                    <p class="mt-1 text-xl font-extrabold">{{ $rpRingkas($arus['perkiraan']) }}</p>
                    <p class="text-xs text-brand-200">{{ $arus['tgl_akhir']->translatedFormat('d M Y') }}</p>
                </div>
            </div>

            {{-- Pecahan pembelian: terpakai vs tertahan --}}
            @php
                $total = max($arus['beli'], $arus['pakai'], 1);
                $pctPakai = min(100, round($arus['pakai'] / $total * 100));
            @endphp
            <div class="mt-6">
                <div class="mb-2 flex flex-wrap justify-between gap-2 text-xs font-bold">
                    <span class="text-emerald-700">{{ __('Consumed') }} {{ $rpRingkas($arus['pakai']) }}</span>
                    <span class="{{ $arus['beli'] - $arus['pakai'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ __('Held in warehouse') }} {{ $rpRingkas(max(0, $arus['beli'] - $arus['pakai'])) }}</span>
                </div>
                <div class="flex h-4 overflow-hidden rounded-full bg-amber-200">
                    <div class="h-full bg-emerald-500" style="width: {{ $pctPakai }}%"></div>
                </div>
            </div>

            <div class="mt-5 rounded-2xl p-4 text-sm {{ $arus['selisih'] !== null ? ($arus['selisih'] > 0 ? 'bg-accent-50 text-accent-800 ring-1 ring-accent-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200') : 'bg-slate-50 text-slate-600 ring-1 ring-slate-200' }}">
                @if($arus['selisih'] !== null)
                    <b>{{ __('Stock count variance: :rp.', ['rp' => $rp($arus['selisih'])]) }}</b>
                    {{ __('Physical stock :tgl = :rp.', ['tgl' => $arus['tgl_akhir']->translatedFormat('d M Y'), 'rp' => $rp($arus['aktual'])]) }}
                    {{ $arus['selisih'] > 0 ? __('Goods value is lower than expected — a sign of items issued without record / lost.') : __('No sign of loss.') }}
                @else
                    <b>{{ __('Stock count variance cannot be computed yet.') }}</b> {{ __('A second stock snapshot (import the latest ERP master file) is needed to compare expected stock with physical stock — the difference indicates lost items.') }}
                @endif
            </div>
        @else
            <p class="rounded-2xl bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">{{ __('No stock snapshot yet. Import the ERP master file first.') }}</p>
        @endif
    </section>

    {{-- ============ TREN & TOP JOB ============ --}}
    <div class="grid gap-6 xl:grid-cols-5">
        <section class="card p-5 xl:col-span-3">
            <h2 class="text-lg font-extrabold text-slate-900">{{ __('Purchases vs usage per month') }}</h2>
            <p class="mb-4 text-sm text-slate-500">{{ __('Line = cumulative money held in the warehouse. A line that keeps rising = buying faster than using.') }}</p>
            <div class="relative h-72"><canvas id="grafikTren" aria-label="{{ __('Purchases vs usage chart') }}"></canvas></div>
        </section>

        <section class="card p-5 xl:col-span-2">
            <h2 class="text-lg font-extrabold text-slate-900">{{ __('Top 5 machines by cost') }}</h2>
            <p class="mb-4 text-sm text-slate-500">{{ __('ERP PM Jobs are grouped into their machine via the') }} @can('kelola')<a href="{{ route('mesin.index') }}" class="font-bold text-brand-600 hover:underline">{{ __('Machines & PM Jobs') }}</a>@else<b>{{ __('Machines & PM Jobs') }}</b>@endcan {{ __('menu. Unmapped Jobs are shown by number.') }}</p>
            @php $maksJob = max(1, (float) ($laporan['top_job']->max('rp') ?? 1)); @endphp
            <div class="space-y-3">
                @forelse($laporan['top_job'] as $i => $j)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <p class="flex items-center gap-2 font-bold text-slate-800">
                                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-xs font-extrabold text-brand-700">{{ $i + 1 }}</span>
                                <a href="{{ route('mesin.detail', ['nama' => $j->job]) }}" class="hover:text-brand-700 hover:underline {{ $j->belum_dipetakan ? 'font-mono' : '' }}">{{ $j->job }}</a>
                                @if($j->belum_dipetakan && Gate::allows('kelola'))
                                    <a href="{{ route('mesin.index', ['q' => $j->job]) }}" class="badge bg-amber-50 text-amber-800 ring-1 ring-amber-200 hover:bg-amber-100">{{ __('map') }}</a>
                                @endif
                            </p>
                            <p class="font-extrabold text-slate-800">{{ $rpRingkas($j->rp) }}</p>
                        </div>
                        <div class="ml-8 mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ round($j->rp / $maksJob * 100) }}%"></div>
                        </div>
                        <p class="ml-8 mt-1 text-[11px] text-slate-400">{{ __(':n transactions', ['n' => $j->frek]) }} · {{ (int) $j->qty }} pcs{{ $j->jumlah_job ? ' · ' . __(':n PM Jobs', ['n' => $j->jumlah_job]) : '' }}</p>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No usage in this period yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <p class="-mt-3 text-right text-xs"><a href="{{ route('mesin.biaya') }}" class="font-bold text-brand-600 hover:underline">{{ __('All machine costs') }} &rarr;</a></p>

    {{-- ============ KUALITAS PEMBELIAN ============ --}}
    <section id="kualitas" class="card overflow-hidden">
        <div class="border-b border-slate-100 p-5 sm:p-6">
            <h2 class="text-lg font-extrabold text-slate-900">{{ __('Were the purchases actually needed?') }}</h2>
            <p class="text-sm text-slate-500">{{ __('Each purchase line is assessed by the stock at purchase time and whether the item was actually used afterwards.') }}</p>

            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-5">
                @foreach(A::STATUS_BELI as $kode => [$label, $warna, $arti])
                    @php $ps = $k['per_status'][$kode] ?? ['jumlah' => 0, 'rp' => 0]; [$bg, $tx, $bd] = $warnaStatus[$warna]; @endphp
                    <a href="{{ $urlStatus($filterStatus === $kode ? null : $kode) }}" title="{{ __($arti) }}"
                       class="rounded-2xl border p-4 transition hover:shadow-lift {{ $bg }} {{ $bd }} {{ $filterStatus === $kode ? 'ring-2 ring-brand-500' : '' }}">
                        <p class="text-xs font-bold uppercase tracking-wider {{ $tx }}">{{ __($label) }}</p>
                        <p class="mt-1 text-lg font-extrabold {{ $tx }}">{{ $rpRingkas($ps['rp']) }}</p>
                        <p class="text-[11px] font-semibold text-slate-500">{{ __(':n purchases', ['n' => $ps['jumlah']]) }}</p>
                    </a>
                @endforeach
            </div>

            {{-- Komposisi nilai pembelian per status --}}
            @if($k['total_rp'] > 0)
                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100">
                    @foreach(A::STATUS_BELI as $kode => [$label, $warna])
                        @php $pct = ($k['per_status'][$kode]['rp'] ?? 0) / $k['total_rp'] * 100; @endphp
                        @if($pct > 0)<div class="h-full {{ $warnaStatus[$warna][3] }}" style="width: {{ $pct }}%" title="{{ __($label) }} {{ round($pct) }}%"></div>@endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Item') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Bought') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Value') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Stock at purchase') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Used since purchase') }}</th>
                        <th class="hidden px-4 py-3 lg:table-cell">{{ __('Assessment') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($barisTampil->take(100) as $b)
                        @php [$label, $warna, $arti] = A::STATUS_BELI[$b->status]; [$bg, $tx] = $warnaStatus[$warna]; @endphp
                        <tr class="hover:bg-brand-50/40">
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-600">{{ $b->tgl->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('inventory.index', ['q' => $b->part_no]) }}" class="font-bold text-slate-800 hover:text-brand-700">{{ $b->deskripsi }}</a>
                                <p class="font-mono text-[11px] text-slate-400">{{ $b->part_no }}{{ $b->supplier ? ' · ' . $b->supplier : '' }}</p>
                                <span class="badge mt-1 {{ $bg }} {{ $tx }} lg:hidden">{{ __($label) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ $b->qty }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-slate-800">{{ $rp($b->rp) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $b->stok_saat_beli ?? '?' }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $b->pakai_sejak > 0 ? 'font-bold text-emerald-700' : 'text-slate-400' }}">{{ $b->pakai_sejak }}</td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <span class="badge {{ $bg }} {{ $tx }}">{{ __($label) }}</span>
                                <p class="mt-1 max-w-[260px] text-[11px] leading-snug text-slate-500">{{ __($arti) }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center font-semibold text-slate-400">{{ $filterStatus ? __('No purchases with this status in this period.') : __('No purchases in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($barisTampil->count() > 100)
            <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-400">{{ __('Showing the 100 largest purchases out of :n.', ['n' => $barisTampil->count()]) }}</p>
        @endif
    </section>

    {{-- ============ UANG MENGENDAP & TOP BELI ============ --}}
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="card overflow-hidden" x-data="{ tab: 'dead' }">
            <div class="border-b border-slate-100 p-5">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('Idle money in the warehouse') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Current stock position (M03 warehouse). This is the savings potential: don\'t buy more, use it up, or sell/return it to the supplier.') }}</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button type="button" @click="tab = 'dead'" :class="tab === 'dead' ? 'ring-2 ring-brand-500' : ''" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-left">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-800">{{ $c['cukup_tahun'] ? __('Dead stock') : __('Not moving yet*') }}</p>
                        <p class="mt-1 text-lg font-extrabold text-amber-800">{{ $rpRingkas($m['dead_rp']) }}</p>
                        <p class="text-[11px] font-semibold text-slate-500">{{ __(':n parts not issued for :hari days', ['n' => number_format($m['dead_n']), 'hari' => $m['hari_jendela']]) }}</p>
                    </button>
                    <button type="button" @click="tab = 'over'" :class="tab === 'over' ? 'ring-2 ring-brand-500' : ''" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-left">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-800">Overstock{{ $c['cukup_tahun'] ? '' : '*' }}</p>
                        <p class="mt-1 text-lg font-extrabold text-amber-800">{{ $rpRingkas($m['over_rp']) }}</p>
                        <p class="text-[11px] font-semibold text-slate-500">{{ __(':n parts with stock > 12-month demand', ['n' => $m['over_n']]) }}</p>
                    </button>
                </div>
                @if(!$c['cukup_tahun'])
                    <p class="mt-3 text-[11px] font-semibold text-amber-700">* {{ __('Provisional: computed from :n days of data. Becomes a valid dead stock / overstock figure only after 12 months of data.', ['n' => $m['hari_jendela']]) }}</p>
                @endif
            </div>
            <div class="divide-y divide-slate-100">
                <template x-if="tab === 'dead'"><div class="divide-y divide-slate-100">
                    @forelse($m['dead_top'] as $d)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $d->deskripsi }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $d->part_no }} · {{ __('stock') }} {{ $d->stock }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-extrabold text-amber-700">{{ $rpRingkas($d->rp) }}</p>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div></template>
                <template x-if="tab === 'over'"><div class="divide-y divide-slate-100">
                    @forelse($m['over_top'] as $o)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $o->deskripsi }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $o->part_no }} · {{ __('stock') }} {{ $o->stock }} · {{ __('needs ±:n/year', ['n' => (int) $o->kebutuhan_12]) }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-extrabold text-amber-700">{{ $rpRingkas($o->rp) }}</p>
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div></template>
            </div>
        </section>

        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 p-5">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('Items with the largest purchases') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Top 10 parts by purchase value in this period.') }}</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($laporan['top_beli'] as $i => $t)
                    <a href="{{ route('inventory.index', ['q' => $t->part_no]) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-brand-50/40">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs font-extrabold text-slate-500">{{ $i + 1 }}</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $t->deskripsi }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $t->part_no }} · {{ (int) $t->qty }} pcs · {{ __('bought :n×', ['n' => $t->kali]) }}</p>
                            </div>
                        </div>
                        <p class="shrink-0 text-sm font-extrabold text-slate-800">{{ $rpRingkas($t->rp) }}</p>
                    </a>
                @empty
                    <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No purchases in this period yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- ============ RESTOCK & KERUGIAN ============ --}}
    @php $op = $laporan['kerugian']['opname']; @endphp
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('restock.index') }}" class="card group flex items-center gap-4 p-5 transition hover:shadow-lift">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 group-hover:bg-brand-600 group-hover:text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <div class="flex-1">
                <p class="kpi-label">{{ __('What truly needs buying') }}</p>
                <p class="text-xl font-extrabold text-slate-900">{{ __(':n parts', ['n' => $laporan['restock']['jumlah']]) }} · {{ $rpRingkas($laporan['restock']['rp']) }}</p>
                <p class="text-xs text-slate-500">{{ __('Stock < 3-month demand. Purchases outside this list should be questioned.') }}</p>
            </div>
        </a>
        <a href="{{ route('rekonsiliasi.index') }}" class="card group flex items-center gap-4 p-5 transition hover:shadow-lift {{ $laporan['kerugian']['erp_tanpa_scan'] ? 'border-accent-200 bg-accent-50' : '' }}">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-600 group-hover:bg-accent-600 group-hover:text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div class="flex-1">
                <p class="kpi-label">{{ __('Issued without kiosk scan') }}</p>
                <p class="text-xl font-extrabold {{ $laporan['kerugian']['erp_tanpa_scan'] ? 'text-accent-600' : 'text-slate-900' }}">{{ __(':n transactions', ['n' => $laporan['kerugian']['erp_tanpa_scan']]) }} · {{ $rpRingkas($laporan['kerugian']['erp_tanpa_scan_rp']) }}</p>
                <p class="text-xs text-slate-500">{{ __('Recorded in ERP but never scanned — a sign items were taken without being recorded.') }}</p>
            </div>
        </a>
        <a href="{{ route('opname.index') }}" class="card group flex items-center gap-4 p-5 transition hover:shadow-lift {{ $op['kurang_rp'] > 0 ? 'border-accent-200 bg-accent-50' : '' }}">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-accent-50 text-accent-600 group-hover:bg-accent-600 group-hover:text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <div class="flex-1">
                <p class="kpi-label">{{ __('Missing from stock counts') }}</p>
                <p class="text-xl font-extrabold {{ $op['kurang_rp'] > 0 ? 'text-accent-600' : 'text-slate-900' }}">−{{ $rpRingkas($op['kurang_rp']) }} <span class="text-sm font-bold text-emerald-700">+{{ $rpRingkas($op['lebih_rp']) }}</span></p>
                <p class="text-xs text-slate-500">{{ $op['sesi'] ? __('From :n posted rack counts in this period. Physical count lower than system = lost or unrecorded items.', ['n' => $op['sesi']]) : __('No rack counts posted in this period yet. Start one in Stock Count.') }}</p>
            </div>
        </a>
    </div>

    {{-- ============ CARA MEMBACA ============ --}}
    <details class="card p-5 text-sm text-slate-600">
        <summary class="cursor-pointer font-bold text-slate-800">{{ __('How the figures on this page are calculated') }}</summary>
        <ul class="mt-3 list-disc space-y-1.5 pl-5">
            <li>{!! __('calc.purchases') !!}</li>
            <li>{!! __('calc.held') !!}</li>
            <li>{!! __('calc.assessment') !!}</li>
            <li>{!! __('calc.dead') !!}</li>
            <li>{!! __('calc.variance') !!}</li>
            <li>{!! __('calc.scope') !!}</li>
        </ul>
    </details>

    <x-slot:scripts>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = @json($laporan['tren']);
                const el = document.getElementById('grafikTren');
                if (!el || !window.Chart) return;
                const sat = @js(['m' => __('unit.billion'), 'jt' => __('unit.million'), 'rb' => __('unit.thousand')]);
                const ringkas = v => { const a = Math.abs(v); const s = a >= 1e9 ? (a / 1e9).toFixed(1) + ' ' + sat.m : a >= 1e6 ? Math.round(a / 1e6) + ' ' + sat.jt : a >= 1e3 ? Math.round(a / 1e3) + ' ' + sat.rb : a; return (v < 0 ? '−' : '') + 'Rp ' + s; };
                const rupiah = v => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);

                new Chart(el, {
                    data: {
                        labels: data.map(d => d.label),
                        datasets: [
                            { type: 'bar', label: @js(__('Purchases')), data: data.map(d => d.beli), backgroundColor: '#1567b3', borderRadius: 6, maxBarThickness: 34, order: 2 },
                            { type: 'bar', label: @js(__('Usage')), data: data.map(d => d.pakai), backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 34, order: 2 },
                            { type: 'line', label: @js(__('Cumulative held')), data: data.map(d => d.tertahan), borderColor: '#d97706', backgroundColor: '#d97706', borderWidth: 2.5, pointRadius: 3, cubicInterpolationMode: 'monotone', order: 1 },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { weight: 600 } } },
                            tooltip: { backgroundColor: '#0f172a', padding: 12, cornerRadius: 10, callbacks: { label: c => c.dataset.label + ': ' + rupiah(c.parsed.y) } },
                        },
                        scales: {
                            y: { beginAtZero: true, border: { display: false }, grid: { color: '#eef2f7' }, ticks: { color: '#94a3b8', callback: ringkas } },
                            x: { grid: { display: false }, ticks: { color: '#64748b', font: { weight: 600 } } },
                        },
                    },
                });
            });
        </script>
    </x-slot:scripts>
</x-shell>
