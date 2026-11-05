<x-shell :title="__('Manage Parts')" :subtitle="__('Complete photos, categories & keywords — sorted by most frequently issued parts')">

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach([
            [__('Total parts'), $statistik['total'], null, 'text-slate-800'],
            [__('With photo'), $statistik['ada_foto'], $statistik['total'], 'text-brand-700'],
            [__('With category'), $statistik['ada_kategori'], $statistik['total'], 'text-brand-700'],
            [__('With keywords'), $statistik['ada_kata_kunci'], $statistik['total'], 'text-brand-700'],
        ] as [$label, $nilai, $dari, $warna])
            <div class="card p-4">
                <p class="kpi-label">{{ $label }}</p>
                <p class="mt-1 text-2xl font-extrabold {{ $warna }}">{{ number_format($nilai) }}
                    @if($dari)<span class="text-sm font-semibold text-slate-400">/ {{ number_format($dari) }}</span>@endif
                </p>
                @if($dari)
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ round($nilai / max(1, $dari) * 100, 1) }}%"></div></div>
                @endif
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('inventory.kelola') }}" class="card flex flex-col gap-3 p-3 md:flex-row">
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search part no, name, category...') }}" class="input flex-1">
        <select name="filter" class="input md:w-56">
            @foreach(['baru_tanpa_foto' => __('New, no photo (:n)', ['n' => \App\Models\Inventory::baruTanpaFoto()->count()]), 'tanpa_foto' => __('No photo yet'), 'tanpa_kategori' => __('No category yet'), 'semua' => __('All parts')] as $v => $l)
                <option value="{{ $v }}" @selected($filter === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="btn-primary">{{ __('Show') }}</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Part') }}</th>
                        <th class="px-4 py-3">{{ __('Category · keywords') }}</th>
                        <th class="px-4 py-3">{{ __('Location') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Issued 12 mo') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($barang as $b)
                        <tr class="hover:bg-brand-50/40">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-foto-barang :item="$b" class="h-14 w-14 shrink-0 rounded-xl" />
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800">{{ $b->deskripsi }}</p>
                                        <p class="font-mono text-[11px] text-slate-400">{{ $b->part_no }} · {{ __('stock') }} {{ $b->stock }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold {{ $b->kategori ? 'text-brand-700' : 'text-slate-300' }}">{{ $b->kategori ?? __('None yet') }}</p>
                                <p class="line-clamp-1 text-xs text-slate-500">{{ $b->kata_kunci }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-lokasi :item="$b" />
                                <p class="text-[11px] text-slate-400">{{ $b->area_rak }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <p class="font-mono font-bold">{{ number_format($b->qty_keluar) }}</p>
                                <p class="text-[11px] text-slate-400">{{ __(':n transactions', ['n' => $b->frekuensi]) }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('inventory.edit', ['inventory' => $b, 'kembali' => url()->full()]) }}" class="btn-primary px-3 py-2 text-xs">{{ __('Complete') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center font-semibold text-slate-400">{{ __('No matching parts.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $barang->links() }}</div>
</x-shell>
