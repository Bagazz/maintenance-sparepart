@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Purchase Requests')" :subtitle="__('Track every PR from submission to PO to goods received')">

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach([
            ['terbuka', __('Still waiting for goods'), $hitung['terbuka'], 'text-brand-700'],
            ['terlambat', __('Past expected arrival'), $hitung['terlambat'], $hitung['terlambat'] ? 'text-accent-600' : 'text-slate-800'],
            ['selesai', __('Completed / cancelled'), null, 'text-slate-500'],
        ] as [$kode, $label, $nilai, $warna])
            <a href="{{ route('pr.index', ['status' => $kode]) }}" class="card p-4 transition hover:border-brand-300 {{ $status === $kode ? 'ring-2 ring-brand-500' : '' }}">
                <p class="kpi-label">{{ $label }}</p>
                <p class="mt-1 text-3xl font-extrabold {{ $warna }}">{{ $nilai ?? '→' }}</p>
            </a>
        @endforeach
    </div>

    @if($hitung['diajukan'])
        <div class="card flex items-center gap-3 border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-900">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ __(':n PRs are submitted but have no PO number yet. Ask Purchasing for the PO and record it, so arrival can be tracked.', ['n' => $hitung['diajukan']]) }}</span>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3">
            <p class="font-bold text-slate-800">{{ __('Purchase Request list') }}</p>
            <a href="{{ route('restock.index') }}" class="btn-primary px-3 py-1.5 text-xs">+ {{ __('New PR from Purchase Recommendations') }}</a>
        </div>
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[820px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">{{ __('PR') }}</th>
                        <th class="px-3 py-3">{{ __('Status') }}</th>
                        <th class="px-3 py-3">{{ __('PO / supplier') }}</th>
                        <th class="px-3 py-3">{{ __('Expected arrival') }}</th>
                        <th class="px-3 py-3 text-right">{{ __('Received') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Est. value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($daftar as $pr)
                        @php $persen = $pr->total_minta ? (int) round($pr->total_diterima / $pr->total_minta * 100) : 0; @endphp
                        <tr class="hover:bg-brand-50/40">
                            <td class="px-5 py-3">
                                <a href="{{ route('pr.show', $pr) }}" class="font-mono font-bold text-brand-700 hover:underline">{{ $pr->kode }}</a>
                                <p class="text-[11px] text-slate-400">{{ $pr->created_at->translatedFormat('d M Y') }} · {{ __(':n parts', ['n' => $pr->items_count]) }}</p>
                            </td>
                            <td class="px-3 py-3"><x-status-pr :pr="$pr" /></td>
                            <td class="px-3 py-3">
                                @if($pr->no_po)<p class="font-mono font-bold text-slate-700">{{ $pr->no_po }}</p>@else<p class="text-xs text-slate-400">{{ __('No PO yet') }}</p>@endif
                                <p class="text-[11px] text-slate-500">{{ $pr->pemasok }}</p>
                            </td>
                            <td class="px-3 py-3 {{ $pr->terlambat ? 'font-bold text-accent-600' : 'text-slate-600' }}">{{ $pr->perkiraan_datang?->translatedFormat('d M Y') ?? '—' }}</td>
                            <td class="px-3 py-3 text-right">
                                <p class="font-mono font-bold text-slate-700">{{ (int) $pr->total_diterima }} / {{ (int) $pr->total_minta }}</p>
                                <div class="ml-auto mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-emerald-500" style="width: {{ $persen }}%"></div></div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-bold text-slate-800">{{ $rp($pr->nilai) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center font-semibold text-slate-400">{{ __('No Purchase Requests here yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $daftar->links() }}</div>
</x-shell>
