@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Goods Receipt :kode', ['kode' => $dok->kode])"
         :subtitle="$dok->tanggal->translatedFormat('d M Y') . ' · ' . ($dok->pemasok ?: '-')"
         :kembali="route('penerimaan.index')">

    <x-slot:actions>
        <button type="button" onclick="window.print()" class="btn-ghost hidden sm:inline-flex print:hidden">{{ __('Print') }}</button>
    </x-slot:actions>

    @if($dok->status === 'batal')
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <b>{{ __('Cancelled :tgl.', ['tgl' => $dok->dibatalkan_at?->translatedFormat('d M Y H:i')]) }}</b> {{ __('Reason:') }} {{ $dok->alasan_batal }}
        </div>
    @endif

    <div class="card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            [__('Receipt number'), $dok->kode],
            [__('Receipt date'), $dok->tanggal->translatedFormat('d F Y')],
            [__('Supplier'), $dok->pemasok ?: '-'],
            [__('PO / delivery note no.'), $dok->no_dokumen ?: '-'],
        ] as [$label, $nilai])
            <div>
                <p class="kpi-label">{{ $label }}</p>
                <p class="mt-1 font-bold text-slate-800">{{ $nilai }}</p>
            </div>
        @endforeach
        @if($dok->catatan)
            <div class="sm:col-span-2 lg:col-span-4">
                <p class="kpi-label">{{ __('Notes') }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ $dok->catatan }}</p>
            </div>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">{{ __('Item') }}</th>
                        <th class="px-4 py-3">{{ __('Bin') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Unit price (Rp)') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Subtotal') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dok->baris as $i => $b)
                        <tr>
                            <td class="px-4 py-3 text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800">{{ $b->inventory?->deskripsi }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $b->inventory?->part_no }}</p>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs font-bold text-brand-700">{{ $b->inventory?->lokasi_rak }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold">{{ $b->qty }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $rp($b->harga_satuan) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-bold">{{ $rp($b->total_harga) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm font-semibold text-slate-400">{{ __('No item lines (the receipt was cancelled).') }}</td></tr>
                    @endforelse
                </tbody>
                @if($dok->baris->isNotEmpty())
                    <tfoot class="bg-slate-50 font-bold">
                        <tr>
                            <td class="px-4 py-3" colspan="3">{{ __('Total') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $dok->baris->sum('qty') }}</td>
                            <td></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $rp($dok->baris->sum('total_harga')) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="hidden grid-cols-2 gap-6 pt-6 text-center text-sm print:grid">
        <div><p class="font-bold">{{ __('Received by') }}</p><div class="mx-auto mt-16 w-48 border-b border-slate-400"></div><p class="mt-1 text-slate-600">{{ $dok->user?->name }}</p></div>
        <div><p class="font-bold">{{ __('Delivered by') }}</p><div class="mx-auto mt-16 w-48 border-b border-slate-400"></div><p class="mt-1 text-slate-600">{{ $dok->pemasok ?: '' }}</p></div>
    </div>

    @if($dok->status === 'aktif')
        <form method="POST" action="{{ route('penerimaan.batal', $dok) }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center print:hidden"
              onsubmit="return confirm(@js(__('Cancel this receipt? Stock will be reduced again.')))">
            @csrf
            <p class="flex-1 text-sm text-slate-600">{{ __('Entered something wrong? Cancel this receipt (only possible while the items have not been taken yet), then record it again.') }}</p>
            <input name="alasan" required maxlength="300" class="input sm:w-64" placeholder="{{ __('Reason for cancelling') }}">
            <button class="btn-ghost hover:text-accent-600">{{ __('Cancel receipt') }}</button>
        </form>
    @endif
</x-shell>
