@php
    $t = $status['terakhir'];
    $ok = $status['terakhir_ok'];
    $kb = fn ($b) => number_format($b / 1024, 0, ',', '.') . ' KB';
    $gagalTerakhir = ($t['ok'] ?? true) === false;
@endphp
<x-shell :title="__('Backup')" :subtitle="__('Daily automatic backup of the database and item photos')">

    {{-- ============ STATUS ============ --}}
    <div class="grid gap-4 md:grid-cols-3">
        <div class="card p-5 {{ $status['perlu_cek'] ? 'border-accent-200 bg-accent-50' : 'border-emerald-200 bg-emerald-50' }}">
            <p class="kpi-label {{ $status['perlu_cek'] ? 'text-accent-700' : 'text-emerald-700' }}">{{ __('Latest backup') }}</p>
            @if($ok)
                <p class="mt-1 text-xl font-extrabold {{ $status['perlu_cek'] ? 'text-accent-700' : 'text-emerald-800' }}">{{ $ok->waktu->translatedFormat('d M Y H:i') }}</p>
                <p class="text-xs font-semibold text-slate-600">{{ $ok->waktu->diffForHumans() }} · {{ $kb($ok->ukuran) }}</p>
            @else
                <p class="mt-1 text-xl font-extrabold text-accent-700">{{ __('No backup yet') }}</p>
            @endif
            @if($gagalTerakhir)
                <p class="mt-2 text-xs font-bold text-accent-700">{{ __('Last attempt failed (:tgl):', ['tgl' => \Illuminate\Support\Carbon::parse($t['waktu'])->translatedFormat('d M Y H:i')]) }} {{ $t['pesan'] ?? '' }}</p>
            @elseif($status['perlu_cek'])
                <p class="mt-2 text-xs font-bold text-accent-700">{{ __('Older than :n hours — check that the PC is on at backup time.', ['n' => config('backup.peringatan_jam')]) }}</p>
            @endif
        </div>

        <div class="card p-5">
            <p class="kpi-label">{{ __('Daily schedule') }}</p>
            @if($jadwal)
                <p class="mt-1 text-xl font-extrabold text-slate-900">{{ __('Every day at :jam', ['jam' => config('backup.jam')]) }}</p>
                <p class="text-xs font-semibold text-slate-500">{{ __('Next run: :tgl', ['tgl' => $jadwal['berikut']?->translatedFormat('d M Y H:i') ?? '–']) }}</p>
                <p class="text-[11px] text-slate-400">{{ __('If the PC is off at that time, the backup runs as soon as it is turned on.') }}</p>
            @else
                <p class="mt-1 text-xl font-extrabold text-amber-700">{{ __('Not scheduled') }}</p>
                <p class="text-xs text-slate-500">{{ __('Run once on the PC that hosts the application:') }}</p>
                <code class="mt-1 block rounded-lg bg-slate-900 px-3 py-2 font-mono text-xs text-white">php artisan backup:jadwalkan</code>
            @endif
        </div>

        <div class="card p-5">
            <p class="kpi-label">{{ __('Storage') }}</p>
            <p class="mt-1 break-all font-mono text-xs font-bold text-slate-700">{{ $folder }}</p>
            @if(config('backup.salin_ke'))
                <p class="mt-2 text-xs font-semibold text-emerald-700">{{ __('Second copy:') }} <span class="break-all font-mono">{{ config('backup.salin_ke') }}</span></p>
            @else
                <p class="mt-2 text-xs font-semibold text-amber-700">{{ __('No second copy yet. Set BACKUP_COPY_TO in .env to another drive or PC so a disk failure does not lose the backups too.') }}</p>
            @endif
            <p class="mt-2 text-[11px] text-slate-400">{{ __('Backups older than :n days are deleted automatically (the latest 3 are always kept).', ['n' => config('backup.simpan_hari')]) }}</p>
        </div>
    </div>

    {{-- ============ BACKUP SEKARANG ============ --}}
    <form method="POST" action="{{ route('backup.jalankan') }}" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" x-data="{ kirim: false }" @submit="kirim = true">
        @csrf
        <div class="flex-1 text-sm text-slate-600">
            <p class="font-bold text-slate-800">{{ __('Back up now') }}</p>
            <p>{{ __('Recommended before importing ERP data or posting a large stock count.') }}</p>
        </div>
        <button class="btn-primary" :disabled="kirim">
            <span x-show="!kirim">{{ __('Back up now') }}</span>
            <span x-show="kirim" x-cloak>{{ __('Backing up...') }}</span>
        </button>
    </form>

    {{-- ============ DAFTAR ============ --}}
    <div class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4">
            <p class="font-extrabold text-slate-900">{{ __('Backup files') }}</p>
            <p class="text-xs text-slate-500">{{ __('Each file contains the full database (database.sql), item & rack photos, and a summary (manifest.json).') }}</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($daftar as $i => $f)
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $i === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-mono text-sm font-bold text-slate-800">{{ $f->nama }}</p>
                        <p class="text-xs text-slate-500">{{ $f->waktu->translatedFormat('d M Y H:i') }} · {{ $kb($f->ukuran) }}</p>
                    </div>
                    <a href="{{ route('backup.unduh', $f->nama) }}" class="btn-ghost px-3 py-1.5 text-xs">{{ __('Download') }}</a>
                </div>
            @empty
                <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No backup yet') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ============ PEMULIHAN ============ --}}
    <details class="card p-5 text-sm text-slate-600">
        <summary class="cursor-pointer font-bold text-slate-800">{{ __('How to restore from a backup') }}</summary>
        <ol class="mt-3 list-decimal space-y-2 pl-5">
            <li>{{ __('Restoring replaces ALL current data. It is done from the command line by the admin, not from this page.') }}</li>
            <li>{{ __('Open a terminal in the application folder and run (use the backup file name from the list above):') }}
                <code class="mt-1 block rounded-lg bg-slate-900 px-3 py-2 font-mono text-xs text-white">php artisan backup:pulihkan backup-YYYYMMDD-HHMMSS.zip</code></li>
            <li>{{ __('The system first makes a safety backup of the current data, then restores the database and photos.') }}</li>
            <li>{{ __('To check a backup without touching live data, restore it into another database:') }}
                <code class="mt-1 block rounded-lg bg-slate-900 px-3 py-2 font-mono text-xs text-white">php artisan backup:pulihkan backup-YYYYMMDD-HHMMSS.zip --database=coba_pulih</code></li>
        </ol>
    </details>
</x-shell>
