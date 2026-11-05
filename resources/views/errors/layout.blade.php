{{-- Halaman error bermerek. Variabel: $kode, $judul, $pesan --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $kode }} · {{ $judul }} · PT Detpak Indonesia</title>
    <link rel="icon" href="{{ asset('images/detpak.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative flex min-h-screen items-center justify-center overflow-hidden bg-brand-950 px-5 font-sans antialiased">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-brand-500/40 blur-3xl animate-melayang"></div>
        <div class="absolute -bottom-32 -right-10 h-96 w-96 rounded-full bg-accent-600/25 blur-3xl animate-melayang-lambat"></div>
        <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 22px 22px;"></div>
    </div>

    <div class="relative w-full max-w-lg rounded-3xl bg-white p-8 text-center shadow-2xl shadow-black/30 animate-muncul sm:p-10">
        <div class="mx-auto mb-6 flex h-14 w-max items-center rounded-2xl bg-white px-4 ring-1 ring-slate-200">
            <img src="{{ asset('images/detpak.png') }}" alt="PT Detpak Indonesia" class="h-8 w-auto">
        </div>
        <p class="font-mono text-6xl font-bold text-brand-600">{{ $kode }}</p>
        <h1 class="mt-3 text-2xl font-extrabold text-slate-900">{{ $judul }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $pesan }}</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ url('/') }}" class="btn-primary">{{ __('Go to home page') }}</a>
            <button onclick="history.back()" class="btn-ghost">{{ __('Back') }}</button>
        </div>
    </div>
</body>
</html>
