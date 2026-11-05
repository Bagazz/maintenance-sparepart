<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} | PT Detpak Indonesia</title>

        <!-- CSS, JS & font dibundel lokal oleh Vite -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            .font-mono-num { font-family: 'JetBrains Mono', monospace; }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4 bg-[#F4F6F8] dark:bg-[#07111f] relative overflow-hidden">

            <!-- Dekorasi dot-grid khas Detpak, dibungkus terpisah agar tidak mengganggu layout -->
            <div class="absolute inset-0 pointer-events-none opacity-[0.05] dark:opacity-[0.08]" style="background-image: radial-gradient(circle, #00338D 1.5px, transparent 1.5px); background-size: 24px 24px;"></div>

            <!-- Watermark obeng dekoratif, versi filled yang lebih halus -->
            <div class="absolute top-8 right-8 sm:top-12 sm:right-16 opacity-[0.06] dark:opacity-[0.10] pointer-events-none hidden sm:block">
                <svg class="w-40 h-40 text-[#00338D] dark:text-blue-200" viewBox="0 0 24 24">
                    <g transform="rotate(45 12 12)">
                        <rect x="8" y="1.5" width="8" height="6.5" rx="3" fill="currentColor"/>
                        <rect x="10.5" y="7.5" width="3" height="1.8" rx="0.4" fill="currentColor"/>
                        <rect x="11" y="9" width="2" height="10" rx="1" fill="currentColor"/>
                        <path d="M10.6 19 L13.4 19 L12.6 21.5 L11.4 21.5 Z" fill="currentColor"/>
                    </g>
                </svg>
            </div>

            <!-- Header brand -->
            <div class="relative z-10 flex flex-col items-center gap-3 mb-2">
                <div class="h-16 px-4 rounded-2xl bg-white dark:bg-[#111c2e] border border-[#E2E8F0] dark:border-[#263852] flex items-center justify-center shadow-lg">
                    <img src="{{ asset('images/detpak.png') }}" alt="PT Detpak Indonesia" class="h-9 w-auto object-contain">
                </div>

                <div class="text-center">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.25em] text-slate-400 dark:text-slate-500">Detmold Group</p>
                    <h1 class="text-lg font-black text-[#00338D] dark:text-blue-200 tracking-tight mt-0.5">PT Detpak Indonesia</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Maintenance &amp; Spare Part Management System</p>
                </div>
            </div>

            <!-- Card form -->
            <div class="relative z-10 w-full sm:max-w-md mt-6 bg-white dark:bg-[#111c2e] border border-[#E2E8F0] dark:border-[#263852] shadow-xl shadow-slate-900/5 dark:shadow-black/30 overflow-hidden rounded-2xl">
                <!-- Garis aksen merah khas Detpak -->
                <div class="h-[4px] w-full bg-gradient-to-r from-[#E4002B] via-[#FF3B5C] to-[#E4002B]"></div>

                <div class="px-6 py-6 sm:px-8 sm:py-7">
                    {{ $slot }}
                </div>
            </div>

            <p class="relative z-10 mt-8 text-[11px] text-slate-400 dark:text-slate-500 font-medium tracking-wide">
                &copy; {{ date('Y') }} PT Detpak Indonesia &mdash; Detmold Group. All rights reserved.
            </p>
        </div>
    </body>
</html>