@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Machines & PM Jobs')" :subtitle="__('Map ERP PM Job numbers to machine names — cost reports become readable per machine')">

    {{-- ============ RINGKASAN ============ --}}
    @php $persen = $statistik['job'] ? round($statistik['dipetakan'] / $statistik['job'] * 100) : 0; @endphp
    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-5">
            <p class="kpi-label">{{ __('Jobs mapped') }}</p>
            <p class="mt-1 text-3xl font-extrabold text-slate-900">{{ $statistik['dipetakan'] }} <span class="text-base font-semibold text-slate-400">/ {{ $statistik['job'] }}</span></p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $persen }}%"></div></div>
        </div>
        <div class="card p-5 {{ $statistik['rp_belum'] > 0 ? 'border-amber-200 bg-amber-50' : '' }}">
            <p class="kpi-label {{ $statistik['rp_belum'] > 0 ? 'text-amber-800' : '' }}">{{ __('Cost with unknown machine') }}</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-extrabold {{ $statistik['rp_belum'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $rp($statistik['rp_belum']) }}</p>
            <p class="text-xs font-semibold text-slate-500">{{ __('of total ERP usage :rp', ['rp' => $rp($statistik['rp_total'])]) }}</p>
        </div>
        <div class="card p-5 text-sm text-slate-600">
            <p class="font-bold text-slate-800">{{ __('Why is this needed?') }}</p>
            <p class="mt-1">{!! __('ERP only records the PM Job number (e.g. <span class="font-mono">PM25467</span>). Once mapped, the Top 5 on the Dashboard & Cost Analytics show the <b>machine name</b> and combine all Jobs of the same machine.') !!}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- ============ DAFTAR JOB ============ --}}
        <section class="space-y-4 xl:col-span-2">
            <form method="GET" action="{{ route('mesin.index') }}" class="card flex flex-col gap-2 p-3 sm:flex-row">
                <input type="text" name="q" value="{{ $cari }}" class="input" placeholder="{{ __('Search Job number or machine name...') }}">
                <select name="filter" class="input sm:w-52" onchange="this.form.submit()">
                    @foreach(['semua' => __('All Jobs'), 'belum' => __('Not mapped'), 'sudah' => __('Mapped')] as $v => $l)
                        <option value="{{ $v }}" @selected($filter === $v)>{{ $l }}</option>
                    @endforeach
                </select>
                <button class="btn-primary">{{ __('Search') }}</button>
            </form>

            <datalist id="daftar-mesin">
                @foreach($mesin as $m)<option value="{{ $m->nama }}">@endforeach
            </datalist>

            <div class="card overflow-hidden">
                <div class="divide-y divide-slate-100">
                    @forelse($jobs as $j)
                        <div class="flex flex-col gap-3 px-4 py-3.5 lg:flex-row lg:items-center" x-data="{ ubah: {{ $j->mesin ? 'false' : 'true' }} }">
                            <div class="min-w-0 lg:w-56">
                                <a href="{{ route('mesin.detail', ['nama' => $j->mesin ?: $j->no_job]) }}" class="font-mono text-base font-bold text-slate-800 hover:text-brand-700 hover:underline">{{ $j->no_job }}</a>
                                <p class="text-[11px] font-semibold text-slate-400">{{ $rp($j->rp) }} · {{ __(':n transactions', ['n' => $j->frek]) }} · {{ __('last :tgl', ['tgl' => \Illuminate\Support\Carbon::parse($j->terakhir)->translatedFormat('d M Y')]) }}</p>
                            </div>

                            <div x-show="!ubah" class="flex flex-1 items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                <span class="badge bg-brand-50 px-2.5 py-1 text-sm text-brand-700">{{ $j->mesin }}</span>
                                @if($j->keterangan)<span class="truncate text-xs text-slate-500">{{ $j->keterangan }}</span>@endif
                                <div class="ml-auto flex gap-2">
                                    <button type="button" @click="ubah = true" class="btn-ghost px-3 py-1.5 text-xs">{{ __('Change') }}</button>
                                    <form method="POST" action="{{ route('mesin.hapus-padanan') }}">
                                        @csrf
                                        <input type="hidden" name="no_job" value="{{ $j->no_job }}">
                                        <button class="btn-ghost px-3 py-1.5 text-xs hover:text-accent-600">{{ __('Unmap') }}</button>
                                    </form>
                                </div>
                            </div>

                            <form x-show="ubah" x-cloak method="POST" action="{{ route('mesin.petakan') }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                @csrf
                                <input type="hidden" name="no_job" value="{{ $j->no_job }}">
                                <input name="mesin" list="daftar-mesin" value="{{ $j->mesin }}" required class="input sm:w-48" placeholder="{{ __('Machine name') }}">
                                <input name="keterangan" value="{{ $j->keterangan }}" class="input" placeholder="{{ __('Notes (optional)') }}">
                                <button class="btn-primary whitespace-nowrap">{{ __('Save') }}</button>
                                @if($j->mesin)<button type="button" @click="ubah = false" class="btn-ghost">{{ __('Cancel') }}</button>@endif
                            </form>
                        </div>
                    @empty
                        <p class="py-12 text-center font-semibold text-slate-400">{{ $filter === 'belum' ? __('All Jobs are mapped.') : __('No matching Jobs.') }}</p>
                    @endforelse
                </div>
            </div>
            <div>{{ $jobs->links() }}</div>
        </section>

        {{-- ============ MESIN & IMPORT ============ --}}
        <aside class="space-y-6">
            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 p-5">
                    <p class="font-extrabold text-slate-900">{{ __('Machine list') }}</p>
                    <p class="text-xs text-slate-500">{{ __('Also used as the "Target machine" options at the kiosk.') }}</p>
                    <form method="POST" action="{{ route('mesin.tambah') }}" class="mt-3 flex gap-2">
                        @csrf
                        <input name="nama" required maxlength="100" class="input uppercase" placeholder="{{ __('New machine name') }}">
                        <button class="btn-primary">{{ __('Add') }}</button>
                    </form>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($mesin as $m)
                        <div class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div>
                                <a href="{{ route('mesin.detail', ['nama' => $m->nama]) }}" class="font-bold text-slate-800 hover:text-brand-700 hover:underline">{{ $m->nama }}</a>
                                <p class="text-[11px] text-slate-400">{{ __(':n Jobs mapped', ['n' => $m->jumlah_job]) }}</p>
                            </div>
                            @if(!$m->jumlah_job)
                                <form method="POST" action="{{ route('mesin.hapus', $m) }}" onsubmit="return confirm(@js(__('Remove machine :nama from the list?', ['nama' => $m->nama])))">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg p-1.5 text-slate-300 hover:bg-accent-50 hover:text-accent-600" aria-label="{{ __('Remove') }} {{ $m->nama }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm text-slate-400">{{ __('No machines yet.') }}</p>
                    @endforelse
                </div>
            </div>

            <form method="POST" action="{{ route('mesin.import') }}" enctype="multipart/form-data" class="card space-y-3 p-5" x-data="{ nama: '' }">
                @csrf
                <p class="font-extrabold text-slate-900">{{ __('Import mapping from planner') }}</p>
                <p class="text-xs text-slate-500">{{ __('Excel/CSV file, first row is the header:') }} <span class="font-mono font-bold">No Job | Mesin | Keterangan</span>. {{ __('Existing Jobs are updated; new machines are added automatically.') }}</p>
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 p-5 text-center transition hover:border-brand-400 hover:bg-brand-50/40">
                    <span class="text-sm font-bold text-brand-600" x-text="nama || @js(__('Choose .xlsx / .csv file'))"></span>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="hidden" required @change="nama = $event.target.files[0]?.name">
                </label>
                <button class="btn-ghost w-full" :disabled="!nama">{{ __('Import') }}</button>
            </form>
        </aside>
    </div>
</x-shell>
