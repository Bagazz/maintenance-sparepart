@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); $terbuka = in_array($pr->status, \App\Models\PurchaseRequest::TERBUKA, true); @endphp
<x-shell :title="$pr->kode" :subtitle="__('Purchase Request') . ' · ' . $pr->created_at->translatedFormat('d M Y H:i') . ' · ' . $pr->dibuat_oleh">

    {{-- ============ ALUR STATUS ============ --}}
    @php
        $langkah = [['diajukan', __('Submitted')], ['po', __('PO issued')], ['sebagian', __('Partly received')], ['selesai', __('Completed')]];
        $posisi = array_search($pr->status, array_column($langkah, 0));
    @endphp
    <div class="card p-5">
        @if($pr->status === 'dibatalkan')
            <p class="font-bold text-slate-500">{{ __('This PR was cancelled on :tgl.', ['tgl' => $pr->selesai_at?->translatedFormat('d M Y')]) }}</p>
        @else
            <ol class="grid grid-cols-4 gap-2">
                @foreach($langkah as $i => [$kode, $label])
                    <li class="flex flex-col items-center gap-1.5 text-center">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-extrabold {{ $i <= $posisi ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400' }}">{{ $i < $posisi || $pr->status === 'selesai' ? '✓' : $i + 1 }}</span>
                        <span class="text-xs font-bold {{ $i <= $posisi ? 'text-slate-800' : 'text-slate-400' }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
            @if($pr->terlambat)
                <p class="mt-4 rounded-xl bg-accent-50 p-3 text-center text-sm font-bold text-accent-700">{{ __('Expected on :tgl — :n days late. Follow up with Purchasing / the supplier.', ['tgl' => $pr->perkiraan_datang->translatedFormat('d M Y'), 'n' => $pr->perkiraan_datang->diffInDays(today())]) }}</p>
            @endif
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        {{-- ============ BARANG ============ --}}
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
                <p class="font-bold text-slate-800">{{ __(':n parts', ['n' => $pr->items->count()]) }}</p>
                <a href="{{ route('pr.excel', $pr) }}" class="btn-ghost px-3 py-1.5 text-xs">{{ __('Download Excel') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-2">{{ __('Part') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Ordered') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Received') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('Outstanding') }}</th>
                            <th class="px-5 py-2 text-right">{{ __('Est. value') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pr->items as $it)
                            <tr>
                                <td class="px-5 py-2.5">
                                    <p class="font-bold text-slate-800">{{ $it->deskripsi }}</p>
                                    <p class="font-mono text-[11px] text-slate-400">{{ $it->part_no }} · {{ __('stock now') }} {{ (int) $it->inventory?->stock }}</p>
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">{{ $it->qty_minta }}</td>
                                <td class="px-3 py-2.5 text-right font-mono font-bold {{ $it->qty_diterima ? 'text-emerald-700' : 'text-slate-400' }}">{{ $it->qty_diterima }}</td>
                                <td class="px-3 py-2.5 text-right font-mono font-bold {{ $it->sisa ? 'text-amber-700' : 'text-emerald-700' }}">{{ $it->sisa ?: '✓' }}</td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-right font-mono">{{ $rp($it->qty_minta * $it->harga_perkiraan) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($penerimaan->isNotEmpty())
                <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-600">
                    <b>{{ __('Goods receipts') }}:</b>
                    @foreach($penerimaan as $pb)<a href="{{ route('penerimaan.show', $pb) }}" class="ml-2 font-mono font-bold text-brand-700 hover:underline">{{ $pb->kode }}</a>@endforeach
                </div>
            @endif
        </div>

        {{-- ============ TINDAKAN ============ --}}
        <aside class="space-y-4">
            @if(in_array($pr->status, ['diajukan', 'po'], true))
                <form method="POST" action="{{ route('pr.po', $pr) }}" class="card space-y-3 p-5">
                    @csrf
                    <p class="font-bold text-slate-800">{{ $pr->no_po ? __('PO details') : __('Step 2 — record the PO from Purchasing') }}</p>
                    <div>
                        <label class="label" for="no_po">{{ __('PO number') }}</label>
                        <input id="no_po" name="no_po" value="{{ old('no_po', $pr->no_po) }}" required maxlength="100" class="input font-mono">
                    </div>
                    <div>
                        <label class="label" for="pemasok">{{ __('Supplier') }}</label>
                        <input id="pemasok" name="pemasok" value="{{ old('pemasok', $pr->pemasok) }}" maxlength="150" class="input">
                    </div>
                    <div>
                        <label class="label" for="perkiraan_datang">{{ __('Expected arrival') }}</label>
                        <input id="perkiraan_datang" type="date" name="perkiraan_datang" value="{{ old('perkiraan_datang', $pr->perkiraan_datang?->toDateString()) }}" class="input">
                    </div>
                    <button class="btn-primary w-full">{{ __('Save PO') }}</button>
                </form>
            @elseif($pr->no_po)
                <div class="card space-y-1 p-5 text-sm">
                    <p class="kpi-label">{{ __('PO number') }}</p>
                    <p class="font-mono text-lg font-bold text-slate-800">{{ $pr->no_po }}</p>
                    <p class="text-slate-500">{{ $pr->pemasok }}</p>
                </div>
            @endif

            @if($terbuka)
                <a href="{{ route('penerimaan.index', ['pr' => $pr->id]) }}" class="card flex items-center gap-3 border-emerald-200 p-5 transition hover:shadow-lift">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    </span>
                    <span class="flex-1">
                        <span class="block font-bold text-slate-800">{{ __('Goods arrived? Receive them') }}</span>
                        <span class="text-xs text-slate-500">{{ __('Opens Goods Receipt filled with the outstanding items of this PR.') }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('pr.batal', $pr) }}" x-data="{ yakin: false }" class="card space-y-2 p-4">
                    @csrf
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" x-model="yakin" class="h-4 w-4 rounded border-slate-300 text-accent-600"> {{ __('Purchasing will not order this PR') }}
                    </label>
                    <button class="btn-ghost w-full text-xs hover:text-accent-700" :disabled="!yakin">{{ __('Cancel this PR') }}</button>
                </form>
            @endif
        </aside>
    </div>
</x-shell>
