<x-shell :title="__('ERP Import')" :subtitle="__('Load ERP Excel exports (stock master, Receipt, Issue) — including historical data')">

    {{-- ============ SETELAH IMPOR: LABEL BARCODE YANG PERLU DICETAK ============ --}}
    @if(($l = session('label_impor')) && ($l['part'] || $l['laci']))
        <div class="card flex flex-col gap-4 border-amber-300 bg-amber-50 p-5 sm:flex-row sm:items-center">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-200 text-amber-800">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-lg font-extrabold text-amber-950">{{ __('Next step: print the barcode labels') }}</p>
                <p class="text-sm text-amber-900">
                    {{ __(':b new parts came from this file. Labels to print in total: :p parts, :l drawers. Existing labels stay valid.', ['b' => number_format($l['baru'], 0, ',', '.'), 'p' => number_format($l['part'], 0, ',', '.'), 'l' => number_format($l['laci'], 0, ',', '.')]) }}
                </p>
            </div>
            @can('kelola')
                <a href="{{ route('label.index', ['jenis' => $l['part'] ? 'part' : 'laci', 'rak' => 'baru']) }}" class="btn-primary shrink-0 px-5 py-3">{{ __('Print labels') }} &rarr;</a>
            @else
                <p class="shrink-0 text-xs font-semibold text-amber-900">{{ __('The spare part head sees this reminder on the Dashboard.') }}</p>
            @endcan
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- ============ FORM UPLOAD ============ --}}
        <form method="POST" action="{{ route('import.stok') }}" enctype="multipart/form-data"
              x-data="{ nama: '', proses: false }" @submit="proses = true"
              class="card relative space-y-5 p-6 lg:col-span-3">
            @csrf
            <div x-show="proses" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center rounded-2xl bg-white/95">
                <svg class="mb-3 h-10 w-10 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <p class="font-bold text-brand-700">{{ __('Processing file... do not close this page') }}</p>
                <p class="text-xs text-slate-500">{{ __('Large files (1–2 years of data) can take 1–2 minutes.') }}</p>
            </div>

            <div>
                <p class="text-lg font-extrabold text-slate-900">{{ __('Upload ERP Excel file') }}</p>
                <p class="text-sm text-slate-500">{{ __('Two formats are recognised: the ERP report ("Data untuk Dasboard.xlsx": master + Receipt + Issue) and the monthly stock take (No | Part | Description | On Hand | Actual | UOM | Bin). Missing sheets are skipped.') }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ __('A backup is made automatically before stock is updated. Rows in the WASTE bin (items to be disposed of) are never counted as stock.') }}</p>
            </div>

            <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-brand-200 bg-brand-50/40 p-8 text-center transition hover:border-brand-400 hover:bg-brand-50">
                <svg class="mb-2 h-10 w-10 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span class="font-bold text-brand-700" x-text="nama || @js(__('Choose .xlsx file'))"></span>
                <span class="mt-1 text-xs text-slate-500">{{ __('Max. 50 MB') }}</span>
                <input type="file" name="file" accept=".xlsx,.xls" required class="hidden" @change="nama = $event.target.files[0]?.name">
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="tanggal_stok">{{ __('Stock position date in the file') }}</label>
                    <input id="tanggal_stok" type="date" name="tanggal_stok" value="{{ old('tanggal_stok', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="input">
                </div>
                <p class="text-xs leading-relaxed text-slate-500 sm:pt-6">
                    {{ __('Use the date the file was pulled from ERP (e.g. 31 August for sheet') }} <span class="font-mono">M0331agt</span>{{ __('; for a stock take, the day it was printed') }}).
                    @if($snapshotTerakhir)
                        {!! __('If older than <b>:tgl</b> (latest stock data), current stock is <b>not</b> overwritten.', ['tgl' => e(\Illuminate\Support\Carbon::parse($snapshotTerakhir)->format('d M Y'))]) !!}
                    @endif
                </p>
            </div>

            <button class="btn-primary w-full py-3 text-base" :disabled="!nama || proses">{{ __('Import now') }}</button>
        </form>

        {{-- ============ PANDUAN ============ --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card p-5">
                <p class="kpi-label">{{ __('ERP data loaded') }}</p>
                @if($cakupan && $cakupan->jumlah)
                    <p class="mt-2 text-lg font-extrabold text-slate-900">
                        {{ \Illuminate\Support\Carbon::parse($cakupan->awal)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($cakupan->akhir)->format('d M Y') }}
                    </p>
                    <p class="text-sm text-slate-500">{{ __(':n Receipt/Issue transactions', ['n' => number_format($cakupan->jumlah)]) }}</p>
                @else
                    <p class="mt-2 text-sm font-semibold text-slate-500">{{ __('None yet.') }}</p>
                @endif
                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                    {!! __('For per-fiscal-year analytics, import data from <b>1 July 2024</b>. Monthly/quarterly files in any order are fine — existing rows are skipped automatically.') !!}
                </p>
            </div>

            <div class="card p-5">
                <p class="kpi-label mb-3">{{ __('What the system does') }}</p>
                <ul class="space-y-2.5 text-sm text-slate-600">
                    <li class="flex gap-2"><span class="text-brand-600">●</span> <span>{!! __('import.master') !!}</span></li>
                    <li class="flex gap-2"><span class="text-emerald-600">●</span> <span>{!! __('import.receipt') !!}</span></li>
                    <li class="flex gap-2"><span class="text-accent-600">●</span> <span>{!! __('import.issue') !!}</span></li>
                    <li class="flex gap-2"><span class="text-amber-500">●</span> <span>{!! __('import.newpart') !!}</span></li>
                    <li class="flex gap-2"><span class="text-slate-400">●</span> <span>{!! __('import.adj') !!}</span></li>
                    <li class="flex gap-2"><span class="text-slate-400">●</span> <span>{{ __('After import,') }} <a href="{{ route('rekonsiliasi.index') }}" class="font-bold text-brand-600 hover:underline">{{ __('scan vs ERP reconciliation') }}</a> {{ __('runs automatically.') }}</span></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ============ RIWAYAT IMPORT ============ --}}
    <div class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="font-extrabold text-slate-900">{{ __('Import history') }}</p>
        </div>
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Uploaded at') }}</th>
                        <th class="px-4 py-3">{{ __('File') }}</th>
                        <th class="px-4 py-3">{{ __('Transaction period') }}</th>
                        <th class="px-4 py-3">{{ __('Stock') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('New') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Skipped') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Failed') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($batches as $b)
                        <tr class="hover:bg-brand-50/40">
                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $b->created_at->format('d M Y H:i') }}<p class="text-[11px] font-normal text-slate-400">{{ __('by :nama', ['nama' => $b->pengunggah?->name ?? '-']) }}</p></td>
                            <td class="px-4 py-3">
                                <p class="max-w-[220px] truncate font-semibold text-slate-800" title="{{ $b->file_name }}">{{ $b->file_name }}</p>
                                @if($b->part_baru)<p class="text-[11px] text-amber-700">{{ __(':n new parts', ['n' => $b->part_baru]) }}</p>@endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                @if($b->periode_awal)
                                    {{ \Illuminate\Support\Carbon::parse($b->periode_awal)->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($b->periode_akhir)->format('d M Y') }}
                                @else - @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($b->tanggal_stok)
                                    <span class="badge {{ $b->perbarui_master ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ \Illuminate\Support\Carbon::parse($b->tanggal_stok)->format('d M Y') }} · {{ $b->perbarui_master ? __('updated') : __('historical') }}
                                    </span>
                                @else - @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600">{{ number_format($b->inserted_rows) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-500">{{ number_format($b->skipped_rows) }}</td>
                            <td class="px-4 py-3 text-right font-mono {{ $b->failed_rows ? 'font-bold text-accent-600' : 'text-slate-400' }}">{{ number_format($b->failed_rows) }}</td>
                            <td class="px-4 py-3">
                                @if($b->status === 'success')
                                    <span class="badge bg-emerald-50 text-emerald-700">{{ __('Success') }}</span>
                                @elseif($b->status === 'failed')
                                    <span class="badge bg-accent-50 text-accent-700" title="{{ $b->error_message }}">{{ __('Failed') }}</span>
                                @else
                                    <span class="badge bg-amber-100 text-amber-800">{{ __('Processing') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center font-semibold text-slate-400">{{ __('No imports yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $batches->links() }}</div>
</x-shell>
