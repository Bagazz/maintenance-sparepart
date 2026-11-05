@props([
    'title',
    'subtitle' => null,
    'xdata' => '{}',
    // URL induk untuk tombol Kembali saat halaman dibuka langsung (tanpa riwayat). false = sembunyikan.
    'kembali' => null,
])
@php
    $user = auth()->user();
    $isAdmin = $user?->role === 'admin';
    $isViewer = $user?->role === 'viewer';   // Presdir / manager: hanya melihat
    $karyawanSesi = session('peminjam_nama');

    // Menu disusun per kelompok; 'aktif' = pola nama route yang menandai menu sedang dibuka.
    $menu = [
        __('Operations') => [
            ['label' => $isAdmin ? __('Dashboard') : __('Home'), 'route' => 'dashboard', 'aktif' => ['admin.dashboard', 'user.dashboard'], 'ikon' => 'home'],
            ['label' => __('Take Items'), 'route' => 'inventory.index', 'aktif' => ['inventory.index', 'inventory.pilih-peminjam'], 'ikon' => 'scan'],
            ['label' => __('Photo Catalog'), 'route' => 'inventory.katalog', 'aktif' => ['inventory.katalog'], 'ikon' => 'photo'],
            ['label' => __('Returns'), 'route' => 'pengembalian.index', 'aktif' => ['pengembalian.*'], 'ikon' => 'return'],
        ],
    ];
    if ($isViewer) {
        // Viewer: laporan & analitik saja — tanpa ambil barang, retur, atau master data.
        $menu = [
            __('Overview') => [
                ['label' => __('Dashboard'), 'route' => 'dashboard', 'aktif' => ['admin.dashboard'], 'ikon' => 'home'],
                ['label' => __('Find Items'), 'route' => 'inventory.katalog', 'aktif' => ['inventory.katalog', 'inventory.index'], 'ikon' => 'photo'],
                ['label' => __('Stock Count'), 'route' => 'opname.index', 'aktif' => ['opname.*'], 'ikon' => 'clipboard'],
            ],
            __('Analytics') => [
                ['label' => __('Cost Analytics'), 'route' => 'analitik.index', 'aktif' => ['analitik.*'], 'ikon' => 'chart'],
                ['label' => __('Machine Costs'), 'route' => 'mesin.biaya', 'aktif' => ['mesin.biaya', 'mesin.detail'], 'ikon' => 'gear'],
                ['label' => __('Restock Needed'), 'route' => 'restock.index', 'aktif' => ['restock.*'], 'ikon' => 'alert'],
                ['label' => __('History'), 'route' => 'history.index', 'aktif' => ['history.*'], 'ikon' => 'clock'],
                ['label' => __('ERP Reconciliation'), 'route' => 'rekonsiliasi.index', 'aktif' => ['rekonsiliasi.*'], 'ikon' => 'check'],
            ],
        ];
        if (Gate::allows('impor')) {
            $menu[__('Master Data')] = [['label' => __('ERP Import'), 'route' => 'import.index', 'aktif' => ['import.*'], 'ikon' => 'upload']];
        }
    }
    if ($isAdmin) {
        // Permintaan teknisi yang menunggu keputusan Kepala Spare Part.
        $menu[__('Operations')][] = ['label' => __('Approvals'), 'route' => 'persetujuan.index', 'aktif' => ['persetujuan.*'], 'ikon' => 'check',
            'badge' => \App\Models\PermintaanAmbil::menunggu()->count(), 'badge_merah' => true];
        $menu[__('Operations')][] = ['label' => __('Goods Receipt'), 'route' => 'penerimaan.index', 'aktif' => ['penerimaan.*'], 'ikon' => 'inbox'];
        // PR yang perkiraan datangnya sudah lewat ditandai merah.
        $menu[__('Operations')][] = ['label' => __('Purchase Requests'), 'route' => 'pr.index', 'aktif' => ['pr.*'], 'ikon' => 'doc',
            'badge' => \App\Models\PurchaseRequest::terlambat()->count(), 'badge_merah' => true];
        $menu[__('Operations')][] = ['label' => __('Stock Count'), 'route' => 'opname.index', 'aktif' => ['opname.*'], 'ikon' => 'clipboard'];
        $menu[__('Analytics')] = [
            ['label' => __('Cost Analytics'), 'route' => 'analitik.index', 'aktif' => ['analitik.*'], 'ikon' => 'chart'],
            ['label' => __('Machine Costs'), 'route' => 'mesin.biaya', 'aktif' => ['mesin.biaya', 'mesin.detail'], 'ikon' => 'gear'],
            ['label' => __('History'), 'route' => 'history.index', 'aktif' => ['history.*'], 'ikon' => 'clock'],
            ['label' => __('ERP Reconciliation'), 'route' => 'rekonsiliasi.index', 'aktif' => ['rekonsiliasi.*'], 'ikon' => 'check'],
            ['label' => __('Restock Needed'), 'route' => 'restock.index', 'aktif' => ['restock.*'], 'ikon' => 'alert'],
        ];
        $menu[__('Master Data')] = [
            ['label' => __('ERP Import'), 'route' => 'import.index', 'aktif' => ['import.*'], 'ikon' => 'upload'],
            ['label' => __('Manage Parts'), 'route' => 'inventory.kelola', 'aktif' => ['inventory.kelola', 'inventory.edit'], 'ikon' => 'edit'],
            ['label' => __('Print Labels'), 'route' => 'label.index', 'aktif' => ['label.*'], 'ikon' => 'tag',
                // Jumlah part baru / pindah laci yang labelnya belum ditempel.
                'badge' => \App\Models\Inventory::perluLabel()->count() + \App\Models\Inventory::laciPerluLabel()->count()],
            ['label' => __('Employees'), 'route' => 'karyawan.index', 'aktif' => ['karyawan.*'], 'ikon' => 'users'],
            ['label' => __('Machines & PM Jobs'), 'route' => 'mesin.index', 'aktif' => ['mesin.index'], 'ikon' => 'link'],
            ['label' => __('Backup'), 'route' => 'backup.index', 'aktif' => ['backup.*'], 'ikon' => 'database'],
        ];
    }

    // Tombol Kembali: tidak tampil di halaman awal; tujuan cadangan = halaman induk / beranda.
    $halamanAwal = request()->routeIs('admin.dashboard', 'user.dashboard', 'inventory.pilih-peminjam');
    $tampilKembali = $kembali !== false && !$halamanAwal;
    $urlKembali = is_string($kembali) ? $kembali : match (true) {
        request()->routeIs('inventory.edit')            => route('inventory.kelola'),
        request()->routeIs('history.mesin', 'history.operator') => route('history.index'),
        request()->routeIs('opname.*')                => route('opname.index'),
        request()->routeIs('mesin.detail')            => route('mesin.biaya'),
        default                                         => route('dashboard'),
    };

    $ikon = [
        'doc'    => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'home'   => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'scan'   => 'M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8',
        'photo'  => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'return' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
        'clock'  => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'alert'  => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        'edit'   => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'check'  => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
        'gear'   => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'chart'  => 'M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'upload' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
        'tag'    => 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
        'link'   => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        'database' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
        'inbox'  => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'users'  => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Spare Part Maintenance · PT Detpak Indonesia</title>
    <link rel="icon" href="{{ asset('images/detpak.png') }}">

    {{-- Tailwind, Alpine, Chart.js, JsBarcode & font dibundel lokal oleh Vite (npm run build) — tetap jalan tanpa internet. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased" x-data="{{ $xdata }}">
<div x-data="{ navOpen: false }" class="min-h-screen lg:flex">

    {{-- ============ SIDEBAR (desktop tetap, HP jadi drawer) ============ --}}
    <div x-show="navOpen" x-cloak x-transition.opacity @click="navOpen = false" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"></div>

    <aside :class="navOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-50 w-72 transform print:hidden bg-brand-900 text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:shrink-0 flex flex-col">
        <div class="absolute inset-y-0 right-0 w-[3px] bg-accent-600"></div>

        <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
            <div class="h-11 w-11 shrink-0 rounded-xl bg-white p-1.5 shadow">
                <img src="{{ asset('images/detpak.png') }}" alt="Detpak" class="h-full w-full object-contain">
            </div>
            <div class="leading-tight min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-brand-200">Detmold Group</p>
                <p class="text-sm font-extrabold tracking-wide truncate">Spare Part Maintenance</p>
            </div>
            <button @click="navOpen = false" class="ml-auto rounded-lg p-1.5 text-brand-200 hover:bg-white/10 lg:hidden" aria-label="{{ __('Close menu') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto scroll-thin px-3 py-4 space-y-6">
            @foreach($menu as $grup => $items)
                <div>
                    <p class="px-3 mb-2 text-[10px] font-bold uppercase tracking-[.18em] text-brand-300/70">{{ $grup }}</p>
                    <div class="space-y-1">
                        @foreach($items as $m)
                            @php $aktif = request()->routeIs(...$m['aktif']); @endphp
                            <a href="{{ route($m['route']) }}" class="nav-link {{ $aktif ? 'nav-active' : '' }}" @if($aktif) aria-current="page" @endif>
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon[$m['ikon']] }}"/></svg>
                                <span class="flex-1">{{ $m['label'] }}</span>
                                @if(!empty($m['badge']))<span class="rounded-full px-1.5 text-[10px] font-extrabold leading-5 {{ !empty($m['badge_merah']) ? 'animate-pulse bg-accent-600 text-white' : 'bg-amber-400 text-amber-950' }}">{{ $m['badge'] > 999 ? '999+' : $m['badge'] }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="space-y-3 border-t border-white/10 p-4">
            <div class="flex items-center justify-between px-1">
                <span class="text-[10px] font-bold uppercase tracking-[.18em] text-brand-300/70">{{ __('Language') }}</span>
                <x-pilih-bahasa gelap />
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-sm font-extrabold">
                    {{ mb_strtoupper(mb_substr($user?->name ?? '?', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-bold">{{ $user?->name }}</p>
                    <p class="text-[11px] font-semibold text-brand-200">{{ $isAdmin ? __('Admin / Spare Part Head') : ($isViewer ? __('Viewer (read-only)') : __('Maintenance Kiosk')) }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg p-2 text-brand-200 hover:bg-accent-600 hover:text-white transition" title="{{ __('Log out') }}" aria-label="{{ __('Log out') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ============ AREA KONTEN ============ --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 print:static print:border-0 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8 lg:py-4">
                <button @click="navOpen = true" class="-ml-1 rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="{{ __('Open menu') }}">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                @if($tampilKembali)
                    {{-- Kembali ke halaman sebelumnya; bila dibuka langsung (tanpa riwayat) ke halaman induk. --}}
                    <a href="{{ $urlKembali }}"
                       @click.prevent="(document.referrer && new URL(document.referrer).origin === location.origin && document.referrer !== location.href && history.length > 1) ? history.back() : (window.location.href = @js($urlKembali))"
                       class="group inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-sm font-bold text-slate-600 transition hover:border-brand-300 hover:text-brand-700 sm:px-3"
                       title="{{ __('Back') }}" aria-label="{{ __('Back') }}">
                        <svg class="h-4 w-4 transition group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        <span class="hidden sm:inline">{{ __('Back') }}</span>
                    </a>
                @endif

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-extrabold text-slate-900 sm:text-xl">{{ $title }}</h1>
                    @if($subtitle)
                        <p class="hidden truncate text-xs font-semibold text-slate-500 sm:block">{{ $subtitle }}</p>
                    @endif
                </div>

                @if($isViewer)
                    <span class="hidden items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-600 md:inline-flex" title="{{ __('This account can view reports but cannot change data.') }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        {{ __('View only') }}
                    </span>
                @endif

                @if($karyawanSesi)
                    <div class="hidden items-center gap-2 rounded-xl border border-brand-100 bg-brand-50 px-3 py-1.5 md:flex">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span class="text-xs font-semibold text-slate-500">{{ __('Technician') }}:</span>
                        <span class="text-sm font-extrabold text-brand-700">{{ $karyawanSesi }}</span>
                        <a href="{{ route('inventory.ganti-peminjam') }}" class="ml-1 text-[11px] font-bold uppercase text-accent-600 hover:underline">{{ __('Finish') }}</a>
                    </div>
                @endif

                {{ $actions ?? '' }}

                <x-pilih-bahasa class="hidden lg:inline-flex" />

                <div class="hidden items-center gap-1.5 text-slate-500 sm:flex" x-data="{ jam: '' }"
                     x-init="const f = () => jam = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':'); f(); setInterval(f, 15000)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="font-mono text-sm font-bold" x-text="jam"></span>
                </div>
            </div>
            <div class="h-[3px] bg-gradient-to-r from-brand-600 via-brand-500 to-accent-600"></div>
        </header>

        <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8 lg:py-8">
            <div class="mx-auto w-full max-w-[1440px] space-y-6">
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <p class="flex-1">{{ session('success') }}</p>
                        <button @click="show = false" class="text-emerald-600 hover:text-emerald-800" aria-label="{{ __('Close') }}">&times;</button>
                    </div>
                @endif
                @if(session('error') || $errors->any())
                    <div class="flex items-start gap-3 rounded-2xl border border-accent-100 bg-accent-50 p-4 text-sm font-semibold text-accent-700">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="flex-1 space-y-0.5">
                            @if(session('error'))<p>{{ session('error') }}</p>@endif
                            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>

        <footer class="px-4 pb-6 text-center text-[11px] font-medium text-slate-400 sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} PT Detpak Indonesia &mdash; Detmold Group &middot; Maintenance Paper Division
        </footer>
    </div>
</div>

{{ $scripts ?? '' }}
</body>
</html>
