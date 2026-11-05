<x-shell :title="__('Returns')" :subtitle="__('Return items that ended up unused — stock & machine cost are corrected automatically')">

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card flex items-start gap-3 p-5 lg:col-span-2">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
            </div>
            <div class="text-sm text-slate-600">
                <p class="font-bold text-slate-800">{{ __('When to use this page?') }}</p>
                <p class="mt-1">{!! __('Spare parts taken are counted as <b>used</b>. If some end up unused and still in good condition, put them back on the rack and record it here — any time, no deadline. Partial returns are fine, e.g. take 10 bolts, use 7, return 3.') !!}</p>
            </div>
        </div>
        <div class="card p-5">
            <p class="kpi-label">{{ __('Returned this month') }}</p>
            <p class="mt-1 text-3xl font-extrabold text-slate-800">{{ number_format($returBulanIni) }} <span class="text-sm font-semibold text-slate-400">pcs</span></p>
        </div>
    </div>

    <form method="GET" action="{{ route('pengembalian.index') }}" class="card flex items-center gap-2 p-3">
        <input type="text" name="q" value="{{ $q }}" class="input" placeholder="{{ __('Search part, item name, machine, technician, or PM No...') }}">
        <button class="btn-primary">{{ __('Search') }}</button>
    </form>

    @if($q !== '')
        <section class="space-y-3">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('Search results for ":q"', ['q' => $q]) }} <span class="text-sm font-semibold text-slate-400">· {{ __(':n transactions', ['n' => $transaksi->count()]) }}</span></h2>
            @forelse($transaksi as $t)
                @include('pengembalian._baris', ['t' => $t])
            @empty
                <div class="card p-8 text-center font-semibold text-slate-400">{{ __('No matching take-outs.') }}</div>
            @endforelse
            <p class="text-xs text-slate-400">{{ __('Usage imported from ERP is not shown here because it is already final in ERP.') }}</p>
        </section>
    @else
        <section class="space-y-3">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('Take-outs in the last 7 days') }} <span class="text-sm font-semibold text-slate-400">· {{ __(':n transactions', ['n' => $terbaru->count()]) }}</span></h2>
            @forelse($terbaru as $t)
                @include('pengembalian._baris', ['t' => $t])
            @empty
                <div class="card p-8 text-center font-semibold text-slate-400">{{ __('No take-outs in the last 7 days. Older take-outs can be found with the search box above.') }}</div>
            @endforelse
        </section>
    @endif
</x-shell>
