@php
    // Pesan dari lang/{locale}/auth.php (salah password / terlalu banyak percobaan).
    $pesanError = $errors->any() ? $errors->first() : null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Sign in') }} · Spare Part Maintenance · PT Detpak Indonesia</title>
    <link rel="icon" href="{{ asset('images/detpak.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-950 font-sans text-slate-800 antialiased">

<div class="relative flex min-h-screen overflow-hidden">

    {{-- ================= LATAR BERGERAK (penuh di HP, panel kiri di desktop) ================= --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden lg:right-auto lg:w-[56%]" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-900 via-brand-800 to-brand-950"></div>

        {{-- Gumpalan cahaya melayang --}}
        <div class="absolute -left-24 -top-24 h-[28rem] w-[28rem] rounded-full bg-brand-500/40 blur-3xl animate-melayang"></div>
        <div class="absolute -bottom-32 right-0 h-[30rem] w-[30rem] rounded-full bg-accent-600/25 blur-3xl animate-melayang-lambat"></div>
        <div class="absolute left-1/3 top-1/2 h-72 w-72 rounded-full bg-sky-400/20 blur-3xl animate-melayang"></div>

        {{-- Pola titik khas Detpak --}}
        <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 22px 22px;"></div>

        {{-- Roda gigi berputar pelan --}}
        @foreach([
            ['kelas' => '-right-24 top-10 h-[26rem] w-[26rem] text-white/[0.07] animate-putar', 'gigi' => 12],
            ['kelas' => '-bottom-16 left-10 h-64 w-64 text-white/[0.08] animate-putar-balik', 'gigi' => 9],
        ] as $roda)
            <svg class="absolute {{ $roda['kelas'] }}" viewBox="0 0 100 100" fill="currentColor">
                @for($i = 0; $i < $roda['gigi']; $i++)
                    <rect x="44" y="4" width="12" height="16" rx="3" transform="rotate({{ 360 / $roda['gigi'] * $i }} 50 50)"/>
                @endfor
                <path fill-rule="evenodd" d="M50 14a36 36 0 110 72 36 36 0 010-72zm0 22a14 14 0 100 28 14 14 0 000-28z"/>
            </svg>
        @endforeach

        {{-- Garis merah aksen di tepi panel --}}
        <div class="absolute inset-y-0 right-0 hidden w-1 bg-gradient-to-b from-accent-600 via-accent-500 to-accent-600 lg:block"></div>
    </div>

    {{-- ================= PANEL KIRI: BRAND & CERITA PRODUK (desktop) ================= --}}
    <section class="relative z-10 hidden w-[56%] flex-col justify-between p-12 text-white lg:flex xl:p-16">
        <div class="flex items-center gap-4 animate-muncul">
            <div class="flex h-14 items-center rounded-2xl bg-white px-4 shadow-lg">
                <img src="{{ asset('images/detpak.png') }}" alt="PT Detpak Indonesia" class="h-8 w-auto">
            </div>
            <div class="leading-tight">
                <p class="text-[11px] font-bold uppercase tracking-[.25em] text-brand-200">Detmold Group</p>
                <p class="text-lg font-extrabold tracking-wide">PT Detpak Indonesia</p>
            </div>
        </div>

        <div class="max-w-xl">
            <p class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-brand-100 ring-1 ring-white/15 animate-muncul" style="animation-delay: .1s">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Maintenance Paper Division
            </p>
            <h1 class="text-4xl font-extrabold leading-[1.1] tracking-tight xl:text-5xl animate-muncul" style="animation-delay: .15s">
                {!! __('The right spare part,<br>machines keep <span class="text-accent-500">running.</span>') !!}
            </h1>
            <p class="mt-4 max-w-md text-base text-brand-100/90 animate-muncul" style="animation-delay: .25s">
                {{ __('Find any rack & drawer in seconds, record every take-out with a scan, and control spare part spending from one system.') }}
            </p>

            <div class="mt-8 grid max-w-lg grid-cols-[1fr_auto] items-center gap-6 animate-muncul" style="animation-delay: .35s">
                <ul class="space-y-3 text-sm font-semibold text-brand-50">
                    @foreach([
                        ['Search items + photos + drawer location', 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
                        ['Scan employee cards & drawer barcodes', 'M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8'],
                        ['Reconcile usage against ERP', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ] as [$teks, $ikon])
                        <li class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}"/></svg>
                            </span>
                            {{ __($teks) }}
                        </li>
                    @endforeach
                </ul>

                {{-- Ilustrasi kartu scan dengan garis laser bergerak --}}
                <div class="relative h-40 w-32 overflow-hidden rounded-2xl bg-white/95 p-3 shadow-2xl shadow-black/30 ring-1 ring-white/30">
                    <div class="h-3 w-12 rounded bg-brand-600"></div>
                    <div class="mt-2 h-2 w-20 rounded bg-slate-200"></div>
                    <div class="mt-1 h-2 w-14 rounded bg-slate-200"></div>
                    <div class="mt-4 flex h-14 items-end gap-[3px]">
                        @foreach([3,1,2,1,3,2,1,1,3,1,2,3,1,2,1,3] as $lebar)
                            <span class="h-full rounded-[1px] bg-slate-800" style="width: {{ $lebar }}px"></span>
                        @endforeach
                    </div>
                    <p class="mt-1 text-center font-mono text-[9px] font-bold text-slate-500">M03-D4D1</p>
                    <div class="absolute inset-x-2 top-0 h-full animate-pindai">
                        <div class="h-0.5 w-full rounded-full bg-accent-600 shadow-[0_0_12px_2px_rgba(228,0,43,.6)]"></div>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-xs font-medium text-brand-200/80">&copy; {{ date('Y') }} PT Detpak Indonesia &mdash; Detmold Group. {{ __('Internal use only.') }}</p>
    </section>

    {{-- ================= PANEL KANAN: FORM ================= --}}
    <main class="relative z-10 flex w-full items-center justify-center px-5 py-10 lg:w-[44%] lg:bg-slate-50">
        <div class="absolute right-4 top-4">
            <x-pilih-bahasa gelap class="lg:hidden" />
            <x-pilih-bahasa class="hidden lg:inline-flex" />
        </div>
        <div class="w-full max-w-md">

            {{-- Brand ringkas (HP) --}}
            <div class="mb-8 flex flex-col items-center text-center text-white lg:hidden animate-muncul">
                <div class="mb-3 flex h-14 items-center rounded-2xl bg-white px-4 shadow-lg">
                    <img src="{{ asset('images/detpak.png') }}" alt="PT Detpak Indonesia" class="h-8 w-auto">
                </div>
                <p class="text-[11px] font-bold uppercase tracking-[.25em] text-brand-200">Detmold Group</p>
                <p class="text-lg font-extrabold">Spare Part Maintenance</p>
            </div>

            <div class="rounded-3xl bg-white p-7 shadow-2xl shadow-brand-950/20 ring-1 ring-slate-200/70 sm:p-9 animate-muncul"
                 x-data="{ lihat: false, caps: false, kirim: false, jam: '', shift: '' }"
                 x-init="
                    const t = () => {
                        const d = new Date(), m = d.getHours() * 60 + d.getMinutes();
                        jam = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
                        shift = m >= 390 && m < 870 ? 'Shift 1' : (m >= 870 && m < 1350 ? 'Shift 2' : 'Shift 3');
                    }; t(); setInterval(t, 15000)">

                <div class="mb-7 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('Sign in') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Technicians: sign in with your registered name. Admin & management: use your email.') }}</p>
                    </div>
                    <div class="shrink-0 rounded-xl bg-brand-50 px-3 py-2 text-right ring-1 ring-brand-100">
                        <p class="font-mono text-sm font-bold text-brand-700" x-text="jam">--:--</p>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-500" x-text="shift"></p>
                    </div>
                </div>

                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
                @endif

                @if ($pesanError)
                    <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-accent-100 bg-accent-50 p-3 text-sm font-semibold text-accent-700 animate-goyang" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $pesanError }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5" @submit="kirim = true">
                    @csrf

                    <div>
                        <label for="email" class="label">{{ __('Employee name or email') }}</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                            <input id="email" type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" autocapitalize="characters" spellcheck="false"
                                   placeholder="{{ __('e.g. Budi Santoso, or name@gmail.com') }}" class="input py-3 pl-12 text-base">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="label">Password</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <input id="password" :type="lihat ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password"
                                   placeholder="••••••••" class="input py-3 pl-12 pr-12 text-base"
                                   @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   @keydown="caps = $event.getModifierState && $event.getModifierState('CapsLock')">
                            <button type="button" @click="lihat = !lihat" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-brand-600"
                                    :aria-label="lihat ? @js(__('Hide password')) : @js(__('Show password'))">
                                <svg x-show="!lihat" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="lihat" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                        <p x-show="caps" x-cloak class="mt-1.5 flex items-center gap-1 text-xs font-bold text-amber-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
                            {{ __('Caps Lock is on') }}
                        </p>
                    </div>

                    <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-600">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-200">
                        {{ __('Remember me on this device') }}
                    </label>

                    <button type="submit" class="btn-primary w-full py-3.5 text-base" :disabled="kirim">
                        <svg x-show="kirim" x-cloak class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="kirim ? @js(__('Checking...')) : @js(__('Sign in'))">{{ __('Sign in') }}</span>
                    </button>
                </form>

                <div class="mt-7 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 text-xs text-slate-500 ring-1 ring-slate-200/70">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p><b class="text-slate-700">{{ __('Maintenance technicians:') }}</b> {{ __('type your name exactly as registered (upper or lower case is fine) and the password from the spare part head. Forgot the password or account locked? Contact the spare part head.') }}</p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs font-medium text-brand-200 lg:text-slate-400">&copy; {{ date('Y') }} PT Detpak Indonesia &mdash; Detmold Group</p>
        </div>
    </main>
</div>
</body>
</html>
