@php
    $s = $laporan['statistik'];
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $q = fn ($tab) => route('rekonsiliasi.index', ['dari' => $awal->toDateString(), 'sampai' => $akhir->toDateString(), 'tab' => $tab]);
@endphp
<x-shell :title="__('ERP Reconciliation')" :subtitle="__('Matches items scanned at the kiosk with official usage in ERP (Issue sheet)')">

    <x-slot:actions>
        @can('kelola')
        <form method="POST" action="{{ route('rekonsiliasi.jalankan') }}">
            @csrf
            <button class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span class="hidden sm:inline">{{ __('Run matching') }}</span>
            </button>
        </form>
        @endcan
    </x-slot:actions>

    {{-- ============ PERIODE ============ --}}
    <form method="GET" action="{{ route('rekonsiliasi.index') }}" class="card flex flex-wrap items-end gap-3 p-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div>
            <label class="label" for="dari">{{ __('From') }}</label>
            <input id="dari" type="date" name="dari" value="{{ $awal->toDateString() }}" class="input">
        </div>
        <div>
            <label class="label" for="sampai">{{ __('To') }}</label>
            <input id="sampai" type="date" name="sampai" value="{{ $akhir->toDateString() }}" class="input">
        </div>
        <button class="btn-ghost">{{ __('Apply') }}</button>
        <p class="ml-auto text-xs font-semibold text-slate-500">
            @if($laporan['mulai_scan'])
                {!! __('Kiosk in use since <b>:tgl</b>. ERP data before that date is not assessed.', ['tgl' => e($laporan['mulai_scan']->format('d M Y'))]) !!}
            @else
                {{ __('No kiosk scans from registered employees yet — reconciliation starts after the first scan.') }}
            @endif
        </p>
    </form>

    {{-- ============ KPI ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card p-5">
            <p class="kpi-label">{{ __('Match rate') }}</p>
            <p class="mt-1 text-3xl font-extrabold {{ ($s['persen_cocok'] ?? 0) >= 90 ? 'text-emerald-600' : 'text-slate-900' }}">{{ $s['persen_cocok'] !== null ? $s['persen_cocok'] . '%' : '–' }}</p>
            <p class="text-xs font-semibold text-slate-400">{{ __(':n matched pairs', ['n' => $s['cocok']]) }}</p>
        </div>
        <a href="{{ $q('erp') }}" class="card p-5 transition hover:border-accent-200 {{ $s['erp_tanpa_scan'] ? 'border-accent-200 bg-accent-50' : '' }}">
            <p class="kpi-label {{ $s['erp_tanpa_scan'] ? 'text-accent-700' : '' }}">{{ __('ERP without scan') }}</p>
            <p class="mt-1 text-3xl font-extrabold {{ $s['erp_tanpa_scan'] ? 'text-accent-600' : 'text-slate-900' }}">{{ $s['erp_tanpa_scan'] }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ $rp($s['erp_tanpa_scan_rp']) }} · {{ __('issued without kiosk record') }}</p>
        </a>
        <a href="{{ $q('scan') }}" class="card p-5 transition hover:border-amber-200 {{ $s['scan_terlambat'] ? 'border-amber-200 bg-amber-50' : '' }}">
            <p class="kpi-label {{ $s['scan_terlambat'] ? 'text-amber-700' : '' }}">{{ __('Scan not in ERP yet') }}</p>
            <p class="mt-1 text-3xl font-extrabold text-slate-900">{{ $s['scan_belum'] }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ $rp($s['scan_belum_rp']) }} · {{ __(':n over :hari days', ['n' => $s['scan_terlambat'], 'hari' => \App\Services\RekonsiliasiService::BATAS_POSTING_HARI]) }}</p>
        </a>
        <a href="{{ $q('cocok') }}" class="card p-5 transition hover:border-brand-300">
            <p class="kpi-label">{{ __('Qty difference') }}</p>
            <p class="mt-1 text-3xl font-extrabold {{ $s['selisih_qty'] ? 'text-amber-600' : 'text-slate-900' }}">{{ $s['selisih_qty'] }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ __('pairs with different quantities') }}</p>
        </a>
    </div>

    {{-- ============ TAB ============ --}}
    <div class="card overflow-hidden">
        <div class="flex overflow-x-auto border-b border-slate-100 px-2">
            @foreach(['erp' => [__('ERP without scan'), $s['erp_tanpa_scan']], 'scan' => [__('Scan not in ERP yet'), $s['scan_belum']], 'cocok' => [__('Matched'), $s['cocok']]] as $kode => [$label, $jumlah])
                <a href="{{ $q($kode) }}" class="whitespace-nowrap border-b-2 px-4 py-3.5 text-sm font-bold transition {{ $tab === $kode ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                    {{ $label }} <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ $jumlah }}</span>
                </a>
            @endforeach
        </div>

        <div class="divide-y divide-slate-100">
            @if($tab === 'erp')
                <p class="bg-slate-50 px-5 py-3 text-xs text-slate-500">{{ __('Items ERP says were issued, but with no matching kiosk scan. Follow up with the technicians on that PM Job; once clear (e.g. taken by the shift 1 keeper without the kiosk), mark it with a note.') }}</p>
                @forelse($laporan['erp_tanpa_scan'] as $e)
                    <div class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-800">{{ $e->item_name ?? $e->part_no }}</p>
                            <p class="text-xs font-semibold text-slate-500"><span class="font-mono">{{ $e->part_no }}</span> · {{ __('Job') }} <span class="font-mono">{{ $e->no_pm }}</span> · {{ $e->created_at->format('d M Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono font-bold">{{ $e->jumlah }} pcs</p>
                            <p class="text-xs font-bold text-accent-600">{{ $rp($laporan['nilai_erp'][$e->source_ref] ?? 0) }}</p>
                        </div>
                        @can('kelola')
                        <form method="POST" action="{{ route('rekonsiliasi.abaikan', $e) }}" class="flex gap-2 md:w-80">
                            @csrf
                            <input name="catatan" required maxlength="200" class="input py-2 text-xs" placeholder="{{ __('Explanation note...') }}">
                            <button class="btn-ghost px-3 py-2 text-xs">{{ __('Mark') }}</button>
                        </form>
                        @endcan
                    </div>
                @empty
                    <p class="py-12 text-center font-semibold text-emerald-600">{{ __('No ERP usage without a scan in this period.') }}</p>
                @endforelse

            @elseif($tab === 'scan')
                <p class="bg-slate-50 px-5 py-3 text-xs text-slate-500">{{ __('Taken via the kiosk but no Issue line in ERP yet. Make sure the spare part head has posted it to ERP (with the same PM No), then re-import. If posted but not detected, pair it manually.') }}</p>
                @forelse($laporan['scan_belum'] as $sc)
                    @php $calon = $kandidat->get($sc->id, collect()); @endphp
                    <div class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="font-bold text-slate-800">{{ $sc->item_name }}</p>
                                @if($sc->created_at->lt(now()->subDays(\App\Services\RekonsiliasiService::BATAS_POSTING_HARI)))
                                    <span class="badge bg-amber-100 text-amber-800">{{ __('Late posting') }}</span>
                                @endif
                            </div>
                            <p class="text-xs font-semibold text-slate-500">
                                <span class="font-mono">{{ $sc->part_no }}</span> · {{ $sc->peminjam }} · {{ __('Machine') }} {{ $sc->machine }}
                                @if($sc->no_pm) · <span class="font-mono">{{ $sc->no_pm }}</span>@endif
                                · {{ $sc->created_at->format('d M Y H:i') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono font-bold">{{ $sc->jumlah - $sc->qty_returned }} pcs</p>
                            <p class="text-xs font-bold text-slate-500">{{ $rp($laporan['nilai_scan'][$sc->id] ?? 0) }}</p>
                        </div>
                        @can('kelola')
                        <div class="flex flex-col gap-2 sm:flex-row lg:w-[28rem]">
                            @if($calon->isNotEmpty())
                                <form method="POST" action="{{ route('rekonsiliasi.pasangkan') }}" class="flex flex-1 gap-2">
                                    @csrf
                                    <input type="hidden" name="scan_id" value="{{ $sc->id }}">
                                    <select name="erp_id" class="input py-2 text-xs">
                                        @foreach($calon as $c)
                                            <option value="{{ $c->id }}">{{ $c->created_at->format('d M') }} · {{ $c->no_pm }} · {{ $c->jumlah }} pcs</option>
                                        @endforeach
                                    </select>
                                    <button class="btn-primary px-3 py-2 text-xs">{{ __('Pair') }}</button>
                                </form>
                            @else
                                <p class="flex-1 self-center text-xs font-semibold text-slate-400">{{ __('No ERP line for this part yet (±30 days).') }}</p>
                            @endif
                            <form method="POST" action="{{ route('rekonsiliasi.abaikan', $sc) }}" class="flex gap-2" x-data="{ buka: false }">
                                @csrf
                                <input x-show="buka" x-cloak name="catatan" required maxlength="200" class="input py-2 text-xs" placeholder="{{ __('Reason...') }}">
                                <button type="button" x-show="!buka" @click="buka = true" class="btn-ghost px-3 py-2 text-xs">{{ __('Mark') }}</button>
                                <button x-show="buka" x-cloak class="btn-ghost px-3 py-2 text-xs">{{ __('Save') }}</button>
                            </form>
                        </div>
                        @endcan
                    </div>
                @empty
                    <p class="py-12 text-center font-semibold text-emerald-600">{{ __('All scans in this period are already in ERP.') }}</p>
                @endforelse

            @else
                @forelse($laporan['cocok'] as $sc)
                    @php $p = $sc->pasangan; $beda = $p && ($sc->jumlah - $sc->qty_returned) !== (int) $p->jumlah; @endphp
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-[1fr_auto_1fr_auto] md:items-center">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ __('Kiosk scan') }}</p>
                            <p class="font-bold text-slate-800">{{ $sc->item_name }}</p>
                            <p class="text-xs text-slate-500">{{ $sc->peminjam }} · {{ $sc->created_at->format('d M Y H:i') }} · <b>{{ $sc->jumlah - $sc->qty_returned }}</b> pcs</p>
                        </div>
                        <svg class="hidden h-5 w-5 text-emerald-500 md:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">ERP Issue</p>
                            @if($p)
                                <p class="font-mono text-sm font-bold text-slate-800">{{ $p->no_pm }}</p>
                                <p class="text-xs text-slate-500">{{ $p->created_at->format('d M Y') }} · <b class="{{ $beda ? 'text-amber-600' : '' }}">{{ $p->jumlah }}</b> pcs · {{ __($sc->rekon_metode) }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($beda)<span class="badge bg-amber-100 text-amber-800">{{ __('Qty differs') }}</span>@endif
                            @can('kelola')
                            <form method="POST" action="{{ route('rekonsiliasi.lepas', $sc) }}">
                                @csrf
                                <button class="btn-ghost px-3 py-1.5 text-xs">{{ __('Unpair') }}</button>
                            </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <p class="py-12 text-center font-semibold text-slate-400">{{ __('No pairs in this period yet.') }}</p>
                @endforelse
            @endif
        </div>
    </div>
</x-shell>
