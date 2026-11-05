@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $rpMinus = fn ($n) => ($n < 0 ? '−' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.');
    $persen = fn ($p) => $p === null ? null : ($p > 0 ? '▲ ' : ($p < 0 ? '▼ ' : '')) . number_format(abs($p), 1, ',', '.') . '%';
    $banding = fn ($p) => $persen($p) !== null ? $persen($p) . ' ' . __('vs :lalu', ['lalu' => $label_lalu]) : __('No data for :lalu', ['lalu' => $label_lalu]);
    // Bulan-bulan kosong sebelum data ERP pertama tidak ditampilkan (hanya mengisi halaman).
    $tren = collect($tren)->skipUntil(fn ($t) => $t['beli'] > 0 || $t['pakai'] > 0)->values()->whenEmpty(fn () => collect($tren)->take(-1));
    $maksTren = max(1, $tren->max(fn ($t) => max($t['beli'], $t['pakai'])));
    $maksMesin = max(1, (float) ($top_mesin->max('rp') ?? 1));
    $warnaStatus = ['emerald' => '#047857', 'amber' => '#b45309', 'accent' => '#e4002b', 'slate' => '#475569'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Spare Part Monthly Report') }} · {{ $judul }}</title>
    <style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; line-height: 1.35; }
        h1 { font-size: 17pt; margin: 0; color: #004e92; }
        h2 { font-size: 10.5pt; margin: 14pt 0 5pt; color: #004e92; border-bottom: 1.2pt solid #004e92; padding-bottom: 2pt; }
        p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .muted { color: #64748b; }
        .kecil { font-size: 7.3pt; }
        .tebal { font-weight: bold; }
        .kanan { text-align: right; white-space: nowrap; }
        .tengah { text-align: center; }
        .merah { color: #e4002b; }
        .hijau { color: #047857; }
        .kuning { color: #b45309; }
        .sorotan { background: #eef4fb; border-left: 3pt solid #004e92; padding: 7pt 10pt; margin-top: 10pt; }
        .sorotan ul { margin: 3pt 0 0 0; padding-left: 13pt; }
        .sorotan li { margin-bottom: 2pt; }
        .kpi td { width: 25%; border: 0.8pt solid #cbd5e1; padding: 6pt 7pt; vertical-align: top; }
        .kpi .label { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: .3pt; }
        .kpi .nilai { font-size: 12pt; font-weight: bold; margin-top: 2pt; }
        .data th { background: #004e92; color: #fff; font-size: 7.3pt; text-align: left; padding: 4pt 5pt; }
        .data th.kanan { text-align: right; }
        .data td { padding: 3.5pt 5pt; border-bottom: 0.6pt solid #e2e8f0; vertical-align: top; }
        .data tr.total td { border-top: 1pt solid #004e92; font-weight: bold; background: #f1f5f9; }
        .batang { height: 5pt; background: #e2e8f0; }
        .isi-biru { height: 5pt; background: #1567b3; }
        .isi-hijau { height: 5pt; background: #10b981; }
        .isi-merah { height: 5pt; background: #e4002b; }
        .catatan { background: #fffbeb; border: 0.6pt solid #fcd34d; padding: 6pt 8pt; color: #78350f; margin-top: 8pt; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #94a3b8; }
        .halaman:after { content: counter(page); }
        .putus { page-break-before: always; }
    </style>
</head>
<body>
    <div class="footer">
        <table><tr>
            <td>PT Detpak Indonesia · {{ __('Spare Part Monthly Report') }} · {{ $judul }} · {{ __('Generated :tgl', ['tgl' => $dibuat->translatedFormat('d M Y H:i')]) }}</td>
            <td class="kanan">{{ __('Page') }} <span class="halaman"></span></td>
        </tr></table>
    </div>

    {{-- ============ KOP ============ --}}
    <table class="kop">
        <tr>
            <td style="width: 60%">
                <p class="kecil tebal muted" style="letter-spacing: 1pt">PT DETPAK INDONESIA · MAINTENANCE PAPER DIVISION</p>
                <h1>{{ __('Spare Part Monthly Report') }}</h1>
                <p style="font-size: 12pt; font-weight: bold">{{ $judul }}{{ $sebagian ? ' (' . __('to date') . ')' : '' }}</p>
                <p class="muted">{{ __('Period :dari – :sampai · compared with :lalu and :fy', ['dari' => $awal->translatedFormat('d M Y'), 'sampai' => $akhir->translatedFormat('d M Y'), 'lalu' => $label_lalu, 'fy' => $label_fy]) }}</p>
            </td>
            <td class="kanan">
                {{-- Logo PNG butuh ekstensi GD di PHP; tanpa GD pakai logo teks agar PDF tetap jadi. --}}
                @if(extension_loaded('gd'))
                    <img src="{{ public_path('images/detpak.png') }}" style="height: 34pt" alt="">
                @else
                    <span style="font-size: 18pt; font-weight: bold; color: #004e92; letter-spacing: 1pt">DETPAK</span><br>
                    <span class="kecil muted">Detmold Group</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ============ SOROTAN ============ --}}
    <div class="sorotan">
        <p class="tebal">{{ __('Key points this month') }}</p>
        <ul>
            @foreach($sorotan as $s)<li>{{ $s }}</li>@endforeach
        </ul>
    </div>

    {{-- ============ KPI ============ --}}
    <h2>{{ __('Summary') }}</h2>
    <table class="kpi">
        <tr>
            <td>
                <p class="label">{{ __('Purchases') }}</p>
                <p class="nilai">{{ $rp($ini['beli']) }}</p>
                <p class="kecil muted">{{ $banding($ubah_beli) }}</p>
            </td>
            <td>
                <p class="label">{{ __('Usage') }}</p>
                <p class="nilai">{{ $rp($ini['pakai']) }}</p>
                <p class="kecil muted">{{ $banding($ubah_pakai) }}</p>
            </td>
            <td>
                <p class="label">{{ __('Money held in the warehouse') }}</p>
                <p class="nilai {{ $ini['tertahan'] > 0 ? 'kuning' : 'hijau' }}">{{ $rpMinus($ini['tertahan']) }}</p>
                <p class="kecil muted">{{ __('Purchases − usage this period') }}</p>
            </td>
            <td>
                <p class="label">{{ __('Purchases to review') }}</p>
                <p class="nilai {{ $ini['berisiko'] > 0 ? 'merah' : '' }}">{{ $rp($ini['berisiko']) }}</p>
                <p class="kecil muted">{{ $kualitas['total_rp'] > 0 ? __(':pct% of purchase value', ['pct' => round($ini['berisiko'] / $kualitas['total_rp'] * 100)]) : __('No purchases') }}</p>
            </td>
        </tr>
    </table>

    <table class="data" style="margin-top: 8pt">
        <tr><th></th><th class="kanan">{{ $judul }}</th><th class="kanan">{{ $label_lalu }}</th><th class="kanan">{{ __(':fy to date', ['fy' => $label_fy]) }}</th></tr>
        <tr><td>{{ __('Purchases') }}</td><td class="kanan">{{ $rp($ini['beli']) }}</td><td class="kanan">{{ $rp($lalu['beli']) }}</td><td class="kanan">{{ $rp($fy['beli']) }}</td></tr>
        <tr><td>{{ __('Usage') }}</td><td class="kanan">{{ $rp($ini['pakai']) }}</td><td class="kanan">{{ $rp($lalu['pakai']) }}</td><td class="kanan">{{ $rp($fy['pakai']) }}</td></tr>
        <tr class="total"><td>{{ __('Money held (purchases − usage)') }}</td><td class="kanan">{{ $rpMinus($ini['tertahan']) }}</td><td class="kanan">{{ $rpMinus($lalu['beli'] - $lalu['pakai']) }}</td><td class="kanan">{{ $rpMinus($fy['tertahan']) }}</td></tr>
    </table>

    {{-- ============ ARUS UANG ============ --}}
    @if($arus['tersedia'] ?? false)
        <h2>{{ __('Spare part money flow') }}</h2>
        <table class="data">
            <tr>
                <th>{{ __('Opening stock') }} ({{ $arus['tgl_awal']->translatedFormat('d M Y') }})</th>
                <th class="kanan">+ {{ __('Purchases') }}</th>
                <th class="kanan">− {{ __('Usage') }}</th>
                <th class="kanan">= {{ __('Estimated closing stock') }}</th>
                <th class="kanan">{{ __('Physical stock') }}</th>
            </tr>
            <tr>
                <td>{{ $rp($arus['awal']) }}</td>
                <td class="kanan">{{ $rp($arus['beli']) }}</td>
                <td class="kanan">{{ $rp($arus['pakai']) }}</td>
                <td class="kanan tebal">{{ $rp($arus['perkiraan']) }}</td>
                <td class="kanan">{{ $arus['aktual'] !== null ? $rp($arus['aktual']) : __('no snapshot yet') }}</td>
            </tr>
        </table>
        @if($arus['selisih'] !== null)
            <p class="kecil {{ $arus['selisih'] > 0 ? 'merah' : 'hijau' }}" style="margin-top: 3pt">{{ __('Stock count variance: :rp.', ['rp' => $rp($arus['selisih'])]) }} {{ $arus['selisih'] > 0 ? __('Goods value is lower than expected — a sign of items issued without record / lost.') : __('No sign of loss.') }}</p>
        @endif
    @endif

    {{-- ============ TREN ============ --}}
    <h2>{{ $tren->count() < 12 ? __('Purchases vs usage — since data starts') : __('Purchases vs usage — last 12 months') }}</h2>
    <table class="data">
        <tr><th style="width: 12%">{{ __('Month') }}</th><th class="kanan" style="width: 16%">{{ __('Purchases') }}</th><th class="kanan" style="width: 16%">{{ __('Usage') }}</th><th style="width: 38%"></th><th class="kanan">{{ __('Cumulative held') }}</th></tr>
        @foreach($tren as $t)
            <tr>
                <td>{{ $t['label'] }}</td>
                <td class="kanan">{{ $rp($t['beli']) }}</td>
                <td class="kanan">{{ $rp($t['pakai']) }}</td>
                <td>
                    <div class="batang"><div class="isi-biru" style="width: {{ round($t['beli'] / $maksTren * 100) }}%"></div></div>
                    <div class="batang" style="margin-top: 1pt"><div class="isi-hijau" style="width: {{ round($t['pakai'] / $maksTren * 100) }}%"></div></div>
                </td>
                <td class="kanan">{{ $rpMinus($t['tertahan']) }}</td>
            </tr>
        @endforeach
    </table>
    <p class="kecil muted" style="margin-top: 2pt"><span style="color:#1567b3">■</span> {{ __('Purchases') }} &nbsp; <span style="color:#10b981">■</span> {{ __('Usage') }}</p>

    {{-- ============ HALAMAN 2 ============ --}}
    <div class="putus"></div>

    <h2 style="margin-top: 0">{{ __('Top 5 machines by cost') }}</h2>
    <table class="data">
        <tr><th style="width: 4%">#</th><th>{{ __('Machine / Job') }}</th><th class="kanan">{{ __('Transactions') }}</th><th class="kanan">Qty</th><th style="width: 28%"></th><th class="kanan">{{ __('Cost') }}</th></tr>
        @forelse($top_mesin as $i => $m)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="tebal">{{ $m->job }}@if($m->belum_dipetakan)<br><span class="kecil kuning" style="font-weight: normal">{{ __('Job not mapped') }}</span>@endif</td>
                <td class="kanan">{{ $m->frek }}</td>
                <td class="kanan">{{ (int) $m->qty }}</td>
                <td><div class="batang"><div class="isi-merah" style="width: {{ round($m->rp / $maksMesin * 100) }}%"></div></div></td>
                <td class="kanan tebal">{{ $rp($m->rp) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted tengah">{{ __('No usage in this period yet.') }}</td></tr>
        @endforelse
    </table>

    <h2>{{ __('Were the purchases actually needed?') }}</h2>
    <table class="data">
        <tr><th>{{ __('Assessment') }}</th><th>{{ __('Meaning') }}</th><th class="kanan">{{ __('Purchases') }}</th><th class="kanan">{{ __('Value') }}</th></tr>
        @foreach(\App\Services\AnalitikBiayaService::STATUS_BELI as $kode => [$label, $warna, $arti])
            @php $ps = $kualitas['per_status'][$kode] ?? ['jumlah' => 0, 'rp' => 0]; @endphp
            <tr>
                <td class="tebal" style="color: {{ $warnaStatus[$warna] }}">{{ __($label) }}</td>
                <td class="kecil muted">{{ __($arti) }}</td>
                <td class="kanan">{{ $ps['jumlah'] }}</td>
                <td class="kanan">{{ $rp($ps['rp']) }}</td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="2">{{ __('Total') }}</td><td class="kanan">{{ collect($kualitas['per_status'])->sum('jumlah') }}</td><td class="kanan">{{ $rp($kualitas['total_rp']) }}</td></tr>
    </table>

    <h2>{{ __('Items with the largest purchases') }}</h2>
    <table class="data">
        <tr><th style="width: 4%">#</th><th>{{ __('Item') }}</th><th>{{ __('Part No') }}</th><th class="kanan">Qty</th><th class="kanan">{{ __('Value') }}</th></tr>
        @forelse($top_beli->take(8) as $i => $t)
            <tr><td>{{ $i + 1 }}</td><td>{{ \Illuminate\Support\Str::limit($t->deskripsi, 60) }}</td><td class="kecil muted">{{ $t->part_no }}</td><td class="kanan">{{ (int) $t->qty }}</td><td class="kanan">{{ $rp($t->rp) }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted tengah">{{ __('No purchases in this period yet.') }}</td></tr>
        @endforelse
    </table>

    <h2>{{ __('Restock needed now') }}</h2>
    <p class="kecil" style="margin-bottom: 4pt">{{ __(':n parts · estimated :rp · :habis out of stock', ['n' => $restock['jumlah'], 'rp' => $rp($restock['rp']), 'habis' => $restock['habis']]) }}</p>
    <table class="data">
        <tr><th>{{ __('Item') }}</th><th>{{ __('Part No') }}</th><th class="kanan">{{ __('Stock') }}</th><th class="kanan">{{ __('Used 90 days') }}</th><th class="kanan">{{ __('Buy') }}</th><th class="kanan">{{ __('Estimated Rp') }}</th></tr>
        @forelse($restock_top->take(8) as $b)
            <tr><td>{{ \Illuminate\Support\Str::limit($b->deskripsi, 55) }}</td><td class="kecil muted">{{ $b->part_no }}</td><td class="kanan {{ $b->stock <= 0 ? 'merah tebal' : '' }}">{{ $b->stock }}</td><td class="kanan">{{ (int) $b->pakai_90 }}</td><td class="kanan">{{ (int) $b->saran_beli }}</td><td class="kanan">{{ $rp($b->saran_rp) }}</td></tr>
        @empty
            <tr><td colspan="6" class="muted tengah">{{ __('No parts have stock below 3 months of demand.') }}</td></tr>
        @endforelse
    </table>

    <h2>{{ __('Idle money & losses') }}</h2>
    <table class="data">
        <tr><th>{{ __('Indicator') }}</th><th class="kanan">{{ __('Count') }}</th><th class="kanan">{{ __('Value') }}</th><th>{{ __('Meaning') }}</th></tr>
        <tr><td class="tebal">{{ $cakupan['cukup_tahun'] ? __('Dead stock') : __('Not moving yet*') }}</td><td class="kanan">{{ number_format($mengendap['dead_n'], 0, ',', '.') }}</td><td class="kanan">{{ $rp($mengendap['dead_rp']) }}</td><td class="kecil muted">{{ __(':n parts not issued for :hari days', ['n' => number_format($mengendap['dead_n'], 0, ',', '.'), 'hari' => $mengendap['hari_jendela']]) }}</td></tr>
        <tr><td class="tebal">Overstock{{ $cakupan['cukup_tahun'] ? '' : '*' }}</td><td class="kanan">{{ number_format($mengendap['over_n'], 0, ',', '.') }}</td><td class="kanan">{{ $rp($mengendap['over_rp']) }}</td><td class="kecil muted">{{ __(':n parts with stock > 12-month demand', ['n' => $mengendap['over_n']]) }}</td></tr>
        <tr><td class="tebal">{{ __('Issued without kiosk scan') }}</td><td class="kanan">{{ $kerugian['erp_tanpa_scan'] }}</td><td class="kanan">{{ $rp($kerugian['erp_tanpa_scan_rp']) }}</td><td class="kecil muted">{{ __('Recorded in ERP but never scanned — a sign items were taken without being recorded.') }}</td></tr>
        <tr><td class="tebal">{{ __('Missing from stock counts') }}</td><td class="kanan">{{ $kerugian['opname']['sesi'] }}</td><td class="kanan">{{ $rp($kerugian['opname']['kurang_rp']) }}</td><td class="kecil muted">{{ __('Physical < system, from posted counts') }} ({{ __(':n sessions posted', ['n' => $kerugian['opname']['sesi']]) }})</td></tr>
    </table>

    @if(!$cakupan['cukup_tahun'])
        <div class="catatan kecil">
            * {{ __('Only :n days of ERP data', ['n' => $cakupan['hari']]) }}{{ $cakupan['awal'] ? ' (' . __('since :tgl', ['tgl' => $cakupan['awal']->translatedFormat('d M Y')]) . ')' : '' }} — {{ __('some figures are still provisional.') }}
            {{ __('Purchases & usage are already accurate. The "too early", dead stock, and overstock assessments are only valid after ≥ 12 months of data.') }}
        </div>
    @endif

    <p class="kecil muted" style="margin-top: 8pt">{{ __('Sources: ERP Receipt (purchases) and Issue (usage) sheets, kiosk scans after the latest ERP date, M03 warehouse stock at the latest price. Stock adjustments from counts are not counted as usage.') }}</p>
</body>
</html>
