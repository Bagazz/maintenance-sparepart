{{-- Satu pengambilan + form retur. Variabel: $t (HistoryLog) --}}
<div class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-1.5">
            @if($t->status === 'diretur_semua')
                <span class="badge bg-slate-100 text-slate-600">{{ __('Fully returned') }}</span>
            @elseif($t->status === 'diretur_sebagian')
                <span class="badge bg-brand-50 text-brand-700">{{ __('Partially returned') }}</span>
            @else
                <span class="badge bg-emerald-50 text-emerald-700">{{ __('Used') }}</span>
            @endif
            <span class="font-mono text-[11px] text-slate-400">{{ $t->part_no }}</span>
        </div>
        <p class="mt-1 font-bold text-slate-800">{{ $t->item_name ?? '-' }}</p>
        <p class="mt-0.5 text-xs font-semibold text-slate-500">
            {{ $t->peminjam ?? $t->operator ?? '-' }}
            · {{ __('Machine') }} {{ $t->machine ?? '-' }}
            @if($t->no_pm) · <span class="font-mono">{{ $t->no_pm }}</span>@endif
            · {{ $t->created_at->format('d M Y H:i') }} ({{ $t->shift }})
        </p>
    </div>

    <div class="flex items-center gap-4 text-center">
        <div><p class="text-[10px] font-bold uppercase text-slate-400">{{ __('Taken') }}</p><p class="font-mono font-bold">{{ $t->jumlah }}</p></div>
        <div><p class="text-[10px] font-bold uppercase text-slate-400">{{ __('Returned') }}</p><p class="font-mono font-bold text-brand-600">{{ $t->qty_returned }}</p></div>
        <div><p class="text-[10px] font-bold uppercase text-slate-400">{{ __('Used') }}</p><p class="font-mono font-bold text-slate-800">{{ $t->sisa }}</p></div>
    </div>

    <div class="sm:w-56">
        @if($t->sisa <= 0)
            <p class="text-center text-sm font-bold text-slate-400 sm:text-right">{{ __('All back in stock') }}</p>
        @else
            <form action="{{ route('pengembalian.kembalikan', $t->id) }}" method="POST" class="flex items-center gap-2"
                  x-data="{ kirim: false }" @submit="kirim = true">
                @csrf
                <input type="hidden" name="q" value="{{ request('q') }}">
                <input type="number" name="jumlah" value="1" min="1" max="{{ $t->sisa }}" class="input w-20 text-center font-mono" aria-label="{{ __('Quantity returned') }}">
                <button class="btn-primary flex-1" :disabled="kirim">{{ __('Return to stock') }}</button>
            </form>
        @endif
    </div>
</div>
