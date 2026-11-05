@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Restock Needed')" :subtitle="__('The shopping list that is truly needed — stock is not enough for 3 months of demand')">

    {{-- ============ RINGKASAN ============ --}}
    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-5">
            <p class="kpi-label">{{ __('Parts to buy') }}</p>
            <p class="mt-1 text-3xl font-extrabold text-slate-900">{{ number_format($ringkasan['jumlah']) }}</p>
            <p class="text-xs font-semibold {{ $ringkasan['habis'] ? 'text-accent-600' : 'text-slate-400' }}">{{ __(':n already out of stock', ['n' => $ringkasan['habis']]) }}</p>
        </div>
        <div class="card bg-brand-700 p-5 text-white">
            <p class="kpi-label text-brand-100">{{ __('Estimated budget') }}</p>
            <p class="mt-1 whitespace-nowrap text-3xl font-extrabold">{{ $rp($ringkasan['rp']) }}</p>
            <p class="text-xs font-semibold text-brand-200">{{ __('To refill stock up to :n months of demand', ['n' => \App\Services\AnalitikBiayaService::BULAN_TARGET_STOK]) }}</p>
        </div>
        <div class="card p-5 text-sm text-slate-600">
            <p class="font-bold text-slate-800">{{ __('Rule') }}</p>
            <p class="mt-1">{!! __('Listed when <b>stock &lt; usage in the last 90 days</b>. Suggested buy = :n months of demand − current stock. Parts that are never used are not suggested.', ['n' => \App\Services\AnalitikBiayaService::BULAN_TARGET_STOK]) !!}</p>
        </div>
    </div>

    @if(!$cakupan['cukup_90'])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <b>{{ __('Only :n days of usage data so far.', ['n' => $cakupan['hari']]) }}</b> {{ __('3-month demand is computed from the available data, so this list may be shorter than reality. Accurate after ≥ 90 days of ERP data.') }}
        </div>
    @endif

    @can('kelola')
        <p class="-mb-2 text-right text-xs"><a href="{{ route('penerimaan.index') }}" class="font-bold text-brand-600 hover:underline">{{ __('Goods arrived? Record a goods receipt') }} &rarr;</a></p>
    @endcan

    {{-- ============ PURCHASE REQUEST ============ --}}
    <form method="GET" action="{{ route('restock.export') }}" class="card flex flex-col gap-3 border-brand-200 p-4 md:flex-row md:items-center">
        <input type="hidden" name="q" value="{{ $q }}">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <div class="flex-1">
            <p class="font-bold text-slate-800">{{ __('Purchase Request (Excel)') }}</p>
            <p class="text-xs text-slate-500">{{ __('Ready-to-submit form: priority, suggested qty, last price & supplier, totals and signature boxes. Order qty can still be edited in Excel.') }}{{ $q ? ' ' . __('Follows the current search.') : '' }}</p>
        </div>
        <select name="prioritas" class="input md:w-56">
            <option value="semua">{{ __('All :n parts', ['n' => $ringkasan['jumlah']]) }}</option>
            <option value="penting">{{ __('Urgent & high priority only') }}</option>
        </select>
        <button class="btn-success whitespace-nowrap" @disabled($barang->total() === 0)>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {{ __('Download Excel') }}
        </button>
    </form>

    {{-- PR yang dilacak: qty-nya dihitung "sudah dipesan" sampai barang diterima lewat Penerimaan Barang --}}
    @can('kelola')
        <form method="POST" action="{{ route('pr.store') }}" class="card -mt-2 flex flex-col gap-3 border-brand-200 bg-brand-50/30 p-4 md:flex-row md:items-center">
            @csrf
            <input type="hidden" name="q" value="{{ $q }}">
            <div class="flex-1">
                <p class="font-bold text-slate-800">{{ __('Create a tracked Purchase Request') }}</p>
                <p class="text-xs text-slate-500">{{ __('Saves the PR in the app: its quantities count as "on order" (not suggested again), and Goods Receipt fills in what has arrived.') }}</p>
            </div>
            <select name="prioritas" class="input md:w-56">
                <option value="semua">{{ __('All :n parts', ['n' => $ringkasan['jumlah']]) }}</option>
                <option value="penting">{{ __('Urgent & high priority only') }}</option>
            </select>
            <button class="btn-primary whitespace-nowrap" @disabled($barang->total() === 0)>{{ __('Create PR') }}</button>
            <a href="{{ route('pr.index') }}" class="whitespace-nowrap text-xs font-bold text-brand-600 hover:underline">{{ __('View PRs') }} &rarr;</a>
        </form>
    @endcan

    <form method="GET" action="{{ route('restock.index') }}" class="card flex gap-2 p-3">
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search part number or item name...') }}" class="input">
        <button class="btn-primary">{{ __('Search') }}</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Part') }}</th>
                        <th class="px-4 py-3">{{ __('Location') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Stock') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('On order (PR)') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Used 90 days') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Lasts for') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Suggested buy') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Estimated Rp') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($barang as $b)
                        @php $hari = $b->pakai_90 > 0 ? (int) floor($b->stock / ($b->pakai_90 / 90)) : null; @endphp
                        <tr class="hover:bg-brand-50/40">
                            <td class="px-4 py-3">
                                <a href="{{ route('inventory.index', ['q' => $b->part_no]) }}" class="font-bold text-slate-800 hover:text-brand-700">{{ $b->deskripsi }}</a>
                                <p class="font-mono text-[11px] text-slate-400">{{ $b->part_no }} · {{ $rp($b->harga_beli_terakhir) }}/pcs</p>
                            </td>
                            <td class="px-4 py-3"><x-lokasi :item="$b" /><p class="text-[11px] text-slate-400">{{ $b->area_rak }}</p></td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-bold {{ $b->stock <= 0 ? 'text-accent-600' : 'text-amber-600' }}">{{ $b->stock }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $b->dipesan ? 'text-sky-700' : 'text-slate-300' }}">{{ (int) $b->dipesan ?: '–' }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-600">{{ (int) $b->pakai_90 }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($b->stock <= 0)
                                    <span class="badge bg-accent-600 text-white">{{ __('OUT') }}</span>
                                @else
                                    <span class="badge {{ $hari < 30 ? 'bg-accent-50 text-accent-700' : 'bg-amber-100 text-amber-800' }}">± {{ __(':n days', ['n' => $hari]) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-bold text-brand-700">{{ (int) $b->saran_beli }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-slate-800">{{ $rp($b->saran_rp) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center font-semibold text-slate-400">{{ __('No parts have stock below 3 months of demand.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $barang->links() }}</div>
</x-shell>
