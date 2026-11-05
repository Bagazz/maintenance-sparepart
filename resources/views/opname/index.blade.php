@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $siklus = \App\Services\StockOpnameService::HARI_SIKLUS;
    $fy = $statistik['fy'];
@endphp
<x-shell :title="__('Stock Count')" :subtitle="__('Count physical stock per rack with a phone — variances are calculated automatically')"
         xdata="{ pilih: null, cari: '', saring: 'semua' }">

    {{-- ============ RINGKASAN ============ --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card p-4">
            <p class="kpi-label">{{ __('Racks counted (:n days)', ['n' => $siklus]) }}</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $statistik['terhitung'] }} <span class="text-sm font-semibold text-slate-400">/ {{ $statistik['rak'] }}</span></p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $statistik['rak'] ? round($statistik['terhitung'] / $statistik['rak'] * 100) : 0 }}%"></div></div>
        </div>
        <div class="card p-4 {{ $statistik['belum_pernah'] ? 'border-amber-200 bg-amber-50' : '' }}">
            <p class="kpi-label {{ $statistik['belum_pernah'] ? 'text-amber-800' : '' }}">{{ __('Never counted') }}</p>
            <p class="mt-1 text-2xl font-extrabold {{ $statistik['belum_pernah'] ? 'text-amber-700' : 'text-slate-900' }}">{{ $statistik['belum_pernah'] }}</p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __('racks') }}</p>
        </div>
        <div class="card p-4 {{ $fy['kurang_rp'] > 0 ? 'border-accent-200 bg-accent-50' : '' }}">
            <p class="kpi-label {{ $fy['kurang_rp'] > 0 ? 'text-accent-700' : '' }}">{{ __('Missing (FY to date)') }}</p>
            <p class="mt-1 whitespace-nowrap text-base font-extrabold {{ $fy['kurang_rp'] > 0 ? 'text-accent-600' : 'text-slate-900' }} sm:text-xl lg:text-2xl">{{ $rp($fy['kurang_rp']) }}</p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __('Physical < system, from posted counts') }}</p>
        </div>
        <div class="card p-4">
            <p class="kpi-label">{{ __('Surplus (FY to date)') }}</p>
            <p class="mt-1 whitespace-nowrap text-base font-extrabold text-emerald-700 sm:text-xl lg:text-2xl">{{ $rp($fy['lebih_rp']) }}</p>
            <p class="text-[11px] font-semibold text-slate-500">{{ __(':n sessions posted', ['n' => $fy['sesi']]) }}</p>
        </div>
    </div>

    {{-- ============ SESI BERJALAN ============ --}}
    @if($berjalan->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-base font-extrabold text-slate-900">{{ __('Counts in progress') }}</h2>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($berjalan as [$o, $r])
                    <a href="{{ Gate::allows('kelola') ? route('opname.hitung', $o) : route('opname.tinjau', $o) }}" class="card flex items-center gap-4 border-brand-200 p-4 transition hover:shadow-lift">
                        <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-brand-600 text-white">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-brand-100">{{ __('Rack') }}</span>
                            <span class="font-mono text-lg font-bold leading-none">{{ \Illuminate\Support\Str::after($o->kode_rak, '-') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-800">{{ $o->kode }} · {{ $o->petugas }}</p>
                            <p class="text-xs font-semibold text-slate-500">{{ __(':a of :b parts counted', ['a' => $r['dihitung'], 'b' => $r['total']]) }} · {{ $o->created_at->diffForHumans() }}</p>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $r['persen'] }}%"></div></div>
                        </div>
                        <span class="btn-primary px-4 py-2 text-sm">{{ Gate::allows('kelola') ? __('Continue') : __('View') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============ PILIH RAK ============ --}}
    <section class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-extrabold text-slate-900">{{ __('Choose a rack to count') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Count each rack at least every :n days. Start with racks that were never counted or hold the most value.', ['n' => $siklus]) }}</p>
            </div>
            <div class="flex w-full gap-2 sm:w-auto">
                <input type="search" x-model="cari" class="input sm:w-40" placeholder="{{ __('Rack, e.g. B5') }}">
                <select x-model="saring" class="input sm:w-44">
                    <option value="semua">{{ __('All racks') }}</option>
                    <option value="perlu">{{ __('Due for counting') }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
            @foreach($rak as $r)
                @php
                    $perlu = !$r->terakhir || $r->terakhir->lt(now()->subDays($siklus));
                    $data = ['kode' => $r->kode, 'rak' => $r->rak, 'jumlah' => $r->jumlah, 'berjalan' => $r->berjalan ? route('opname.hitung', $r->berjalan) : null];
                @endphp
                <button type="button" @can('kelola') @click="pilih = @js($data)" @else disabled @endcan
                        x-show="(saring === 'semua' || {{ $perlu ? 'true' : 'false' }}) && @js(mb_strtolower($r->kode)).includes(cari.toLowerCase().trim())"
                        class="card p-4 text-left transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift {{ $r->berjalan ? 'ring-2 ring-brand-500' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-mono text-2xl font-bold text-brand-700">{{ $r->rak }}</p>
                        @if($r->berjalan)
                            <span class="badge bg-brand-600 text-white">{{ __('In progress') }}</span>
                        @elseif(!$r->terakhir)
                            <span class="badge bg-amber-100 text-amber-800">{{ __('Never') }}</span>
                        @elseif($perlu)
                            <span class="badge bg-amber-100 text-amber-800">{{ __('Due') }}</span>
                        @else
                            <span class="badge bg-emerald-50 text-emerald-700">{{ __('OK') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ __(':n parts', ['n' => $r->jumlah]) }} · {{ $rp($r->nilai) }}</p>
                    <p class="text-[11px] text-slate-400">{{ $r->terakhir ? __('Last counted :tgl', ['tgl' => $r->terakhir->translatedFormat('d M Y')]) : __('Never counted') }}</p>
                </button>
            @endforeach
        </div>
    </section>

    {{-- ============ RIWAYAT ============ --}}
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="font-extrabold text-slate-900">{{ __('Count history') }}</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($riwayat as $o)
                @php $r = $ringkasRiwayat[$o->id]; @endphp
                <a href="{{ route('opname.tinjau', $o) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-brand-50/40">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800">{{ $o->kode }} · {{ __('Rack') }} <span class="font-mono">{{ $o->kode_rak }}</span></p>
                        <p class="text-xs font-semibold text-slate-500">{{ $o->petugas }} · {{ ($o->diposting_at ?? $o->updated_at)->translatedFormat('d M Y H:i') }} · {{ __(':a of :b parts counted', ['a' => $r['dihitung'], 'b' => $r['total']]) }}</p>
                    </div>
                    @if($o->status === 'batal')
                        <span class="badge bg-slate-100 text-slate-500">{{ __('Cancelled') }}</span>
                    @else
                        <div class="text-right">
                            <p class="text-sm font-extrabold {{ $r['bersih_rp'] < 0 ? 'text-accent-600' : 'text-emerald-700' }}">{{ $r['bersih_rp'] < 0 ? '−' : '+' }}{{ $rp(abs($r['bersih_rp'])) }}</p>
                            <p class="text-[11px] font-semibold text-slate-400">{{ __(':n variances', ['n' => $r['kurang_n'] + $r['lebih_n']]) }}</p>
                        </div>
                    @endif
                </a>
            @empty
                <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No counts yet. Pick a rack above to start the first one.') }}</p>
            @endforelse
        </div>
    </section>
    <div>{{ $riwayat->links() }}</div>

    {{-- ============ MODAL MULAI (admin saja) ============ --}}
    @can('kelola')
    <div x-show="pilih" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @keydown.escape.window="pilih = null">
        <form method="POST" action="{{ route('opname.mulai') }}" class="card w-full max-w-md space-y-4 p-6" @click.outside="pilih = null">
            @csrf
            <input type="hidden" name="kode_rak" :value="pilih?.kode">
            <div>
                <p class="text-lg font-extrabold text-slate-900">{{ __('Count rack') }} <span class="font-mono text-brand-700" x-text="pilih?.rak"></span></p>
                <p class="text-sm text-slate-500"><span x-text="pilih?.jumlah"></span> {{ __('parts are listed in this rack.') }}</p>
            </div>
            <template x-if="pilih?.berjalan">
                <a :href="pilih.berjalan" class="btn-primary w-full">{{ __('Continue the count in progress') }}</a>
            </template>
            <template x-if="!pilih?.berjalan">
                <div class="space-y-4">
                    <div>
                        <label class="label" for="petugas">{{ __('Counted by') }}</label>
                        <input id="petugas" name="petugas" required maxlength="100" class="input uppercase" value="{{ old('petugas') }}" placeholder="{{ __('Name of the person counting') }}">
                    </div>
                    <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-3 text-sm">
                        <input type="hidden" name="hitung_buta" value="0">
                        <input type="checkbox" name="hitung_buta" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                        <span><b class="text-slate-800">{{ __('Blind count (recommended)') }}</b><br><span class="text-slate-500">{{ __('System stock stays hidden until a quantity is entered, so the count is honest.') }}</span></span>
                    </label>
                    <button class="btn-primary w-full py-3 text-base">{{ __('Start counting') }}</button>
                </div>
            </template>
            <a :href="@js(route('label.index', ['jenis' => 'laci'])) + '&rak=' + encodeURIComponent(pilih?.kode || '')" class="block text-center text-sm font-bold text-brand-600 hover:underline">{{ __('Print drawer labels for this rack') }}</a>
            <button type="button" class="btn-ghost w-full" @click="pilih = null">{{ __('Cancel') }}</button>
        </form>
    </div>
    @endcan
</x-shell>
