<x-shell :title="__('Employees')" :subtitle="__('Technician accounts — each technician signs in with their registered name and a password you set')"
         xdata="{ edit: null, pinUntuk: null }">

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
        @foreach([
            [__('Total registered'), $statistik['total'], 'text-slate-800', 'semua'],
            [__('Ready to sign in'), $statistik['siap'], 'text-emerald-600', 'aktif'],
            [__('No password yet'), $statistik['tanpa_pin'], $statistik['tanpa_pin'] ? 'text-amber-600' : 'text-slate-800', 'tanpa_pin'],
            [__('Account locked'), $statistik['terkunci'], $statistik['terkunci'] ? 'text-accent-600' : 'text-slate-800', 'terkunci'],
            [__('Inactive'), $statistik['nonaktif'], 'text-slate-500', 'nonaktif'],
        ] as [$label, $nilai, $warna, $st])
            <a href="{{ route('karyawan.index', ['status' => $st]) }}" class="card p-4 transition hover:border-brand-300 {{ $status === $st ? 'ring-2 ring-brand-500' : '' }}">
                <p class="kpi-label">{{ $label }}</p>
                <p class="mt-1 text-3xl font-extrabold {{ $warna }}">{{ $nilai }}</p>
            </a>
        @endforeach
    </div>

    {{-- ============ PASSWORD BARU (tampil SEKALI untuk diserahkan ke teknisi) ============ --}}
    @if(session('pin_baru'))
        <section id="slip-pin" class="card overflow-hidden border-amber-300">
            <div class="flex flex-wrap items-center gap-3 border-b border-amber-200 bg-amber-50 px-5 py-4 print:hidden">
                <div class="flex-1 text-sm text-amber-900">
                    <p class="font-extrabold">{{ __('New passwords — shown only once') }}</p>
                    <p>{{ __('Print or write these down now and hand each slip to its owner privately. After leaving this page the passwords cannot be seen again (only reset).') }}</p>
                </div>
                <button type="button" onclick="window.print()" class="btn-primary">{{ __('Print login slips') }}</button>
            </div>
            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3">
                @foreach(session('pin_baru') as $p)
                    <div class="rounded-xl border-2 border-dashed border-slate-300 p-4 text-center" style="break-inside: avoid">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ __('Spare part login — keep it secret') }}</p>
                        <p class="mt-2 text-[11px] font-semibold text-slate-400">{{ __('Name') }}</p>
                        <p class="font-bold text-slate-800">{{ $p['nama'] }}</p>
                        <p class="mt-2 text-[11px] font-semibold text-slate-400">{{ __('Password') }}</p>
                        <p class="font-mono text-3xl font-bold tracking-[0.2em] text-brand-700">{{ $p['pin'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
        <style>@media print { body * { visibility: hidden; } #slip-pin, #slip-pin * { visibility: visible; } #slip-pin { position: absolute; inset: 0; border: 0; } }</style>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ============ DAFTAR ============ --}}
        <div class="space-y-4 lg:col-span-2">
            <form method="GET" action="{{ route('karyawan.index') }}" class="card flex gap-2 p-3">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="text" name="q" value="{{ $q }}" class="input" placeholder="{{ __('Search name, employee no, section...') }}">
                <button class="btn-primary">{{ __('Search') }}</button>
            </form>

            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="font-bold text-slate-800">{{ __('Employee list') }}</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($karyawan as $k)
                        <div class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $k->aktif ? '' : 'bg-slate-50 opacity-60' }}">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $k->aktif ? 'bg-brand-100 text-brand-700' : 'bg-slate-200 text-slate-500' }} font-extrabold">{{ mb_substr($k->nama, 0, 1) }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold text-slate-800">{{ $k->nama }}</p>
                                <p class="text-xs font-semibold text-slate-500">
                                    @if($k->nomor_kartu)<span class="font-mono text-slate-700">{{ __('No.') }} {{ $k->nomor_kartu }}</span> · @endif
                                    @if($k->bagian){{ $k->bagian }} · @endif
                                    @if($k->shift){{ $k->shift }}@unless($k->shift_minggu_ini) <span class="text-amber-700">({{ __('not confirmed this week') }})</span>@endunless · @endif
                                    {{ __(':n transactions', ['n' => $k->jumlah_transaksi]) }}
                                </p>
                                <p class="mt-1">
                                    @if($k->terkunci)
                                        <span class="badge bg-accent-50 text-accent-700">{{ __('Account locked (wrong password :n×)', ['n' => $k->pin_gagal]) }}</span>
                                    @elseif($k->punya_pin)
                                        <span class="badge bg-emerald-50 text-emerald-700">{{ __('Password set :tgl', ['tgl' => $k->pin_diatur_at?->translatedFormat('d M Y') ?? '']) }}</span>
                                    @else
                                        <span class="badge bg-amber-100 text-amber-800">{{ __('No password yet') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if($k->terkunci)
                                    <form method="POST" action="{{ route('karyawan.buka-kunci', $k) }}">
                                        @csrf
                                        <button class="btn-primary px-3 py-1.5 text-xs">{{ __('Unlock') }}</button>
                                    </form>
                                @endif
                                <button type="button" class="btn-ghost px-3 py-1.5 text-xs"
                                        @click="pinUntuk = @js(['nama' => $k->nama, 'ada' => $k->punya_pin, 'url' => route('karyawan.pin', $k)])">{{ $k->punya_pin ? __('Reset password') : __('Set password') }}</button>
                                <button type="button" class="btn-ghost px-3 py-1.5 text-xs"
                                        @click="edit = @js(['id' => $k->id, 'nomor_kartu' => $k->nomor_kartu, 'nama' => $k->nama, 'bagian' => $k->bagian, 'shift' => $k->shift, 'url' => route('karyawan.update', $k)])">{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('karyawan.toggle', $k) }}">
                                    @csrf
                                    <button class="btn-ghost px-3 py-1.5 text-xs {{ $k->aktif ? 'hover:text-accent-600' : '' }}">{{ $k->aktif ? __('Deactivate') : __('Activate') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="py-12 text-center font-semibold text-slate-400">{{ __('No employees yet.') }}</p>
                    @endforelse
                </div>
            </div>
            <div>{{ $karyawan->links() }}</div>
        </div>

        {{-- ============ TAMBAH / IMPORT ============ --}}
        <div class="space-y-6">
            <div class="card space-y-2 border-brand-200 bg-brand-50/40 p-5 text-sm text-slate-600">
                <p class="font-bold text-slate-800">{{ __('How technicians sign in') }}</p>
                <ol class="list-decimal space-y-1 pl-5">
                    <li>{{ __('On the login page, type the name exactly as registered here (upper or lower case does not matter).') }}</li>
                    <li>{{ __('Type the password you set (at least 4 characters).') }}</li>
                    <li>{{ __(':n wrong passwords in a row lock the account until you unlock it here.', ['n' => \App\Models\Karyawan::MAKS_PIN_SALAH]) }}</li>
                </ol>
            </div>

            <form method="POST" action="{{ route('karyawan.pin-massal') }}" class="card space-y-3 p-5">
                @csrf
                <p class="font-bold text-slate-800">{{ __('Create starting passwords') }}</p>
                <p class="text-xs text-slate-500">{{ __('Creates a random 6-digit password for every active employee who has none yet, then shows printable login slips once.') }}</p>
                <button class="btn-primary w-full" @disabled(!$statistik['tanpa_pin'])>{{ __('Create passwords for :n employees', ['n' => $statistik['tanpa_pin']]) }}</button>
            </form>

            <form method="POST" action="{{ route('karyawan.store') }}" class="card space-y-4 p-5">
                @csrf
                <p class="font-bold text-slate-800">{{ __('Register employee') }}</p>
                <div>
                    <label class="label" for="nama">{{ __('Name (used to sign in)') }}</label>
                    <input id="nama" name="nama" value="{{ old('nama') }}" required class="input uppercase" placeholder="{{ __('e.g. BUDI SANTOSO') }}">
                </div>
                <div>
                    <label class="label" for="nomor_kartu">{{ __('Employee no') }} <span class="normal-case font-semibold text-slate-400">({{ __('optional') }})</span></label>
                    <input id="nomor_kartu" name="nomor_kartu" value="{{ old('nomor_kartu') }}" class="input font-mono">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="bagian">{{ __('Position') }}</label>
                        <input id="bagian" name="bagian" list="daftar-jabatan" value="{{ old('bagian') }}" class="input" placeholder="{{ __('e.g. Mechanical Technician') }}">
                    </div>
                    <div>
                        <label class="label" for="shift">Shift</label>
                        <select id="shift" name="shift" class="input">
                            <option value="">-</option>
                            @foreach(['Shift 1', 'Shift 2', 'Shift 3', 'Non-shift'] as $s)<option @selected(old('shift') === $s)>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <button class="btn-primary w-full">{{ __('Register') }}</button>
            </form>

            <form method="POST" action="{{ route('karyawan.import') }}" enctype="multipart/form-data" class="card space-y-3 p-5" x-data="{ nama: '' }">
                @csrf
                <p class="font-bold text-slate-800">{{ __('Bulk import') }}</p>
                <p class="text-xs text-slate-500">{{ __('Excel/CSV file, first row is the header:') }} <span class="font-mono font-bold">Nama | No ID | Jabatan | Shift</span>. {{ __('"No ID" is optional. Existing names will be updated. The sheet that has a "Nama" column is used.') }}</p>
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 p-5 text-center transition hover:border-brand-400 hover:bg-brand-50/40">
                    <span class="text-sm font-bold text-brand-600" x-text="nama || @js(__('Choose .xlsx / .csv file'))"></span>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="hidden" required @change="nama = $event.target.files[0]?.name">
                </label>
                <label class="flex items-start gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="password_dari_id" value="1" checked class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600">
                    <span>{{ __('Use the No ID as the starting password for employees who have no password yet') }}</span>
                </label>
                <button class="btn-ghost w-full" :disabled="!nama">{{ __('Import') }}</button>
            </form>
        </div>
    </div>

    <datalist id="daftar-jabatan">@foreach(\App\Models\Karyawan::whereNotNull('bagian')->distinct()->orderBy('bagian')->pluck('bagian') as $j)<option value="{{ $j }}">@endforeach</datalist>

    {{-- ============ MODAL PASSWORD ============ --}}
    <div x-show="pinUntuk" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @keydown.escape.window="pinUntuk = null">
        <form method="POST" :action="pinUntuk?.url" class="card w-full max-w-md space-y-4 p-6" @click.outside="pinUntuk = null" x-data="{ lihat: false }">
            @csrf
            <div>
                <p class="text-lg font-extrabold text-slate-900"><span x-text="pinUntuk?.ada ? @js(__('Reset password')) : @js(__('Set password'))"></span> · <span x-text="pinUntuk?.nama"></span></p>
                <p class="text-xs text-slate-500">{{ __('Type a password of at least 4 characters, or leave it empty to create a random 6-digit password that is shown once.') }}</p>
            </div>
            <div class="relative">
                <input name="pin" :type="lihat ? 'text' : 'password'" minlength="4" maxlength="50" autocomplete="new-password"
                       class="input pr-16 font-mono text-lg" placeholder="••••••">
                <button type="button" @click="lihat = !lihat" class="absolute inset-y-0 right-3 text-xs font-bold text-slate-400" x-text="lihat ? @js(__('Hide')) : @js(__('Show'))"></button>
            </div>
            <p class="text-[11px] text-slate-400">{{ __('Saving a password also unlocks the account. Avoid easy passwords like 1234 or birth dates.') }}</p>
            <div class="flex gap-2">
                <button type="button" class="btn-ghost flex-1" @click="pinUntuk = null">{{ __('Cancel') }}</button>
                <button class="btn-primary flex-1">{{ __('Save password') }}</button>
            </div>
        </form>
    </div>

    {{-- ============ MODAL EDIT ============ --}}
    <div x-show="edit" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @keydown.escape.window="edit = null">
        <form method="POST" :action="edit?.url" class="card w-full max-w-md space-y-4 p-6" @click.outside="edit = null">
            @csrf
            @method('PUT')
            <p class="text-lg font-extrabold text-slate-900">{{ __('Edit employee') }}</p>
            <div>
                <label class="label">{{ __('Name (used to sign in)') }}</label>
                <input name="nama" :value="edit?.nama" required class="input uppercase">
            </div>
            <div>
                <label class="label">{{ __('Employee no') }} <span class="normal-case font-semibold text-slate-400">({{ __('optional') }})</span></label>
                <input name="nomor_kartu" :value="edit?.nomor_kartu" class="input font-mono">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">{{ __('Position') }}</label>
                    <input name="bagian" list="daftar-jabatan" :value="edit?.bagian" class="input">
                </div>
                <div>
                    <label class="label">Shift</label>
                    <select name="shift" class="input">
                        <option value="">-</option>
                        @foreach(['Shift 1', 'Shift 2', 'Shift 3', 'Non-shift'] as $s)<option :selected="edit?.shift === '{{ $s }}'">{{ $s }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" class="btn-ghost flex-1" @click="edit = null">{{ __('Cancel') }}</button>
                <button class="btn-primary flex-1">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</x-shell>
