<x-shell :title="__('Transaction History')" :subtitle="__('Ledger of items issued, received & stock adjustments')"
         xdata="{ activeTab: '{{ $activeTab }}' }">

    <x-slot:actions>
        @can('kelola')
        <a href="{{ route('import.index') }}" class="btn-success hidden sm:inline-flex">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            {{ __('Import ERP Excel') }}
        </a>
        @endcan
    </x-slot:actions>

    {{-- ============ KPI ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="card p-4">
            <p class="kpi-label">{{ __('Total logs') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">{{ number_format($totalTransaksi) }}</p>
        </div>
        <a href="{{ route('history.index', ['tab' => 'in']) }}" class="card p-4 transition hover:border-emerald-300">
            <p class="kpi-label text-emerald-700">{{ __('Parts in (IN)') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-emerald-600">+{{ number_format($barangMasuk) }}</p>
        </a>
        <a href="{{ route('history.index', ['tab' => 'out']) }}" class="card p-4 transition hover:border-accent-200">
            <p class="kpi-label text-accent-700">{{ __('Parts out (OUT)') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-accent-600">−{{ number_format($barangKeluar) }}</p>
        </a>
        <a href="{{ route('history.mesin') }}" class="card p-4 transition hover:border-brand-300">
            <p class="kpi-label">{{ __('Machine / Job') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-brand-700">{{ $mesin }}</p>
        </a>
        <a href="{{ route('history.operator') }}" class="card p-4 transition hover:border-brand-300">
            <p class="kpi-label">{{ __('Operator') }}</p>
            <p class="mt-1 text-2xl font-extrabold text-brand-700">{{ $operator }}</p>
        </a>
    </div>

    @can('kelola')<a href="{{ route('import.index') }}" class="btn-success w-full sm:hidden">{{ __('Import ERP Excel') }}</a>@endcan

    {{-- ============ BUKU BESAR ============ --}}
    <div class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex overflow-x-auto rounded-xl bg-slate-100 p-1">
                @foreach(['out' => [__('Items out'), 'text-accent-600'], 'in' => [__('Items in'), 'text-emerald-600'], 'adj' => [__('Adjustments'), 'text-brand-700']] as $tab => [$label, $warna])
                    <button @click="activeTab = '{{ $tab }}'; const u = new URL(location.href); u.searchParams.set('tab', '{{ $tab }}'); history.replaceState(null, '', u);"
                            :class="activeTab === '{{ $tab }}' ? 'bg-white shadow-sm {{ $warna }}' : 'text-slate-500 hover:text-slate-700'"
                            class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-bold transition">{{ $label }}</button>
                @endforeach
            </div>
            {{-- Ekspor mengikuti tab yang sedang dibuka + filter yang sama dengan tabel --}}
            @php $kueriEkspor = collect(request()->query())->except(['tab', 'out_page', 'in_page', 'adj_page'])->all(); @endphp
            <div class="flex items-center gap-2">
                <span class="hidden text-xs font-semibold text-slate-400 sm:inline">{{ __('Export this tab') }}</span>
                <a :href="urlEkspor(@js(route('history.export-pdf', $kueriEkspor)), activeTab)" class="btn-ghost">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    PDF
                </a>
                <a :href="urlEkspor(@js(route('history.export-xlsx', $kueriEkspor)), activeTab)" class="btn-success">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Excel
                </a>
                <a :href="urlEkspor(@js(route('history.export-csv', $kueriEkspor)), activeTab)" class="btn-ghost" title="{{ __('CSV = raw data for other systems (no logo or formatting). For a neat file to read or print, use Excel or PDF.') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    CSV
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('history.index') }}" x-data="historySearch(@js($q ?? ''))" class="flex flex-col gap-2 border-b border-slate-100 p-4 md:flex-row">
            <input type="hidden" name="tab" :value="activeTab">
            <input type="hidden" name="exact" :value="exact ? '1' : ''">
            <div class="relative flex-1">
                <input type="text" name="q" x-model="q" autocomplete="off"
                       @input.debounce.150ms="cari()" @keydown.escape="hasil = []; aktif = -1"
                       @keydown.arrow-down.prevent="turun()" @keydown.arrow-up.prevent="naik()" @keydown.enter="jikaAdaAktifPilih($event)"
                       placeholder="{{ __('Search part number, taker, machine / PM No...') }}" class="input">
                <div x-show="hasil.length > 0" x-cloak @click.outside="hasil = []; aktif = -1" class="card absolute z-20 mt-2 max-h-64 w-full overflow-y-auto p-1 shadow-lift">
                    <template x-for="(item, index) in hasil" :key="item.label + item.type">
                        <div @mousedown.prevent="pilih(item.label)" @mouseenter="aktif = index" :class="aktif === index ? 'bg-brand-50' : ''"
                             class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2">
                            <span class="text-sm font-bold text-slate-700" x-text="item.label"></span>
                            <span class="badge bg-slate-100 text-slate-500" x-text="item.type"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div x-show="activeTab === 'out'" class="flex gap-2">
                <select name="mesin" onchange="this.form.submit()" class="input md:w-44">
                    <option value="">{{ __('All machines') }}</option>
                    @foreach($daftarMesin as $m)<option value="{{ $m }}" @selected($filterMesin === $m)>{{ $m }}</option>@endforeach
                </select>
                <select name="operator" onchange="this.form.submit()" class="input md:w-44">
                    <option value="">{{ __('All operators') }}</option>
                    @foreach($daftarOperator as $o)<option value="{{ $o }}" @selected($filterOperator === $o)>{{ $o }}</option>@endforeach
                </select>
            </div>
            <button class="btn-primary">{{ __('Filter') }}</button>
            @if($q || $filterMesin || $filterOperator)
                <a href="{{ route('history.index') }}" class="btn-ghost">{{ __('Reset') }}</a>
            @endif
        </form>

        <div class="overflow-x-auto scroll-thin">
            {{-- OUT --}}
            <table x-show="activeTab === 'out'" class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Time') }}</th>
                        <th class="px-4 py-3">{{ __('Item') }}</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3">{{ __('Taken by') }}</th>
                        <th class="px-4 py-3">{{ __('Machine / Job') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($histories as $h)
                        <tr class="hover:bg-brand-50/40">
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800">{{ $h->created_at->format('d M Y') }}</p>
                                {{-- ERP tidak mencatat jam: jam/shift hanya untuk scan aplikasi --}}
                                <p class="text-[11px] text-slate-400">{{ $h->source_ref ? __('ERP import') : $h->created_at->format('H:i') . ' · ' . $h->label_shift }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800">{{ $h->item_name ?? '-' }}</p>
                                <p class="font-mono text-[11px] text-brand-600">{{ $h->part_no }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-accent-600">−{{ $h->jumlah }}</td>
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800">{{ $h->peminjam ?? '-' }}</p>
                                <p class="text-[11px] text-slate-400">{{ $h->source_ref ? __('ERP import') : __('App scan') . ' · ' . ($h->operator ?? '-') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-700">{{ $h->machine ?? '-' }}</p>
                                @if($h->no_pm && $h->no_pm !== $h->machine)<p class="font-mono text-[11px] text-slate-400">{{ $h->no_pm }}</p>@endif
                            </td>
                            <td class="px-4 py-3">
                                @if($h->sisa <= 0)
                                    <span class="badge bg-brand-50 text-brand-700">{{ __('Fully returned') }}</span>
                                @elseif($h->qty_returned > 0)
                                    <span class="badge bg-slate-100 text-slate-600">{{ __('Used') }} · {{ __(':n returned', ['n' => $h->qty_returned]) }}</span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-600">{{ __('Used') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center font-semibold text-slate-400">{{ __('No outgoing item history yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{-- IN --}}
            <table x-show="activeTab === 'in'" x-cloak class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Received date') }}</th>
                        <th class="px-4 py-3">{{ __('Item') }}</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">{{ __('Price') }}</th>
                        <th class="px-4 py-3">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($inHistories as $in)
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-3 font-bold text-slate-800">{{ optional($in->tanggal_transaksi ?? $in->created_at)->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800">{{ $in->inventory->deskripsi ?? '-' }}</p>
                                <p class="font-mono text-[11px] text-brand-600">{{ $in->inventory->part_no ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600">+{{ $in->qty }}</td>
                            <td class="px-4 py-3 text-right">
                                <p class="font-bold text-slate-800">Rp {{ number_format($in->harga_satuan, 0, ',', '.') }}</p>
                                <p class="text-[11px] text-slate-400">Total Rp {{ number_format($in->total_harga, 0, ',', '.') }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $in->keterangan ?? __('Purchasing Excel import') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-12 text-center font-semibold text-slate-400">{{ __('No incoming item history yet. Please import the ERP Excel.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            {{-- ADJ --}}
            <table x-show="activeTab === 'adj'" x-cloak class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Item') }}</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($adjHistories as $adj)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-bold text-slate-800">{{ optional($adj->tanggal_transaksi ?? $adj->created_at)->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-800">{{ $adj->inventory->deskripsi ?? '-' }}</p>
                                <p class="font-mono text-[11px] text-brand-600">{{ $adj->inventory->part_no ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $adj->qty > 0 ? 'text-emerald-600' : 'text-accent-600' }}">{{ $adj->qty > 0 ? '+' : '' }}{{ $adj->qty }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $adj->keterangan ?? __('System adjustment') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-12 text-center font-semibold text-slate-400">{{ __('No stock adjustment history yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-4 py-3">
            <div x-show="activeTab === 'out'">{{ $histories->appends(['tab' => 'out', 'q' => $q, 'mesin' => $filterMesin, 'operator' => $filterOperator])->links() }}</div>
            <div x-show="activeTab === 'in'" x-cloak>{{ $inHistories->appends(['tab' => 'in', 'q' => $q])->links() }}</div>
            <div x-show="activeTab === 'adj'" x-cloak>{{ $adjHistories->appends(['tab' => 'adj', 'q' => $q])->links() }}</div>
        </div>
    </div>


    <x-slot:scripts>
        <script>
            // Tautan ekspor + tab yang sedang dibuka.
            function urlEkspor(dasar, tab) { const u = new URL(dasar, location.origin); u.searchParams.set('tab', tab); return u.toString(); }

            function historySearch(awal) {
                return {
                    q: awal, hasil: [], adaFilterAktif: awal !== '', aktif: -1,
                    exact: false,   // true kalau q dipilih dari saran -> cocokkan persis
                    _abort: null,
                    cari() {
                        this.exact = false; this.aktif = -1;
                        if (this.q.length < 1) { this.hasil = []; if (this.adaFilterAktif) this.$el.submit(); return; }
                        this._abort?.abort();
                        this._abort = new AbortController();
                        fetch(`{{ route('history.suggest') }}?q=${encodeURIComponent(this.q)}`, { signal: this._abort.signal })
                            .then(r => r.json()).then(d => { this.hasil = d; this.aktif = -1; }).catch(() => {});
                    },
                    turun() { if (this.hasil.length) this.aktif = (this.aktif + 1) % this.hasil.length; },
                    naik() { if (this.hasil.length) this.aktif = (this.aktif - 1 + this.hasil.length) % this.hasil.length; },
                    jikaAdaAktifPilih(e) { if (this.aktif >= 0 && this.hasil[this.aktif]) { e.preventDefault(); this.pilih(this.hasil[this.aktif].label); } },
                    pilih(label) { this.q = label; this.hasil = []; this.aktif = -1; this.exact = true; this.$nextTick(() => this.$el.submit()); },
                };
            }
        </script>
    </x-slot:scripts>
</x-shell>
