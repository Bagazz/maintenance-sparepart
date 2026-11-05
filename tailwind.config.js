import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Design token Spare Part Maintenance — PT Detpak Indonesia.
 * Biru Detpak #004e92 = brand-600, merah Detpak #e4002b = accent-600.
 * Dibangun lokal lewat Vite (npm run build) supaya tampilan tetap utuh tanpa internet.
 */
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                brand: {
                    50: '#eef5fc', 100: '#d6e6f7', 200: '#adcdef', 300: '#7aaee3', 400: '#3f86d0',
                    500: '#1567b3', 600: '#004e92', 700: '#003f78', 800: '#003262', 900: '#00264b', 950: '#001a35',
                },
                accent: { 50: '#fff1f3', 100: '#ffdfe4', 200: '#ffc2cc', 500: '#f0193f', 600: '#e4002b', 700: '#bf0024' },
            },
            boxShadow: {
                card: '0 1px 2px rgba(15, 23, 42, .04), 0 4px 16px rgba(15, 23, 42, .04)',
                lift: '0 10px 30px rgba(0, 38, 75, .12)',
            },
            keyframes: {
                melayang: {
                    '0%, 100%': { transform: 'translate3d(0, 0, 0) scale(1)' },
                    '33%': { transform: 'translate3d(6%, -8%, 0) scale(1.08)' },
                    '66%': { transform: 'translate3d(-5%, 6%, 0) scale(.95)' },
                },
                putar: { to: { transform: 'rotate(360deg)' } },
                pindai: {
                    '0%': { transform: 'translateY(-100%)', opacity: 0 },
                    '10%, 90%': { opacity: 1 },
                    '100%': { transform: 'translateY(100%)', opacity: 0 },
                },
                muncul: {
                    from: { opacity: 0, transform: 'translateY(12px)' },
                    to: { opacity: 1, transform: 'translateY(0)' },
                },
                goyang: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '20%, 60%': { transform: 'translateX(-6px)' },
                    '40%, 80%': { transform: 'translateX(6px)' },
                },
            },
            animation: {
                melayang: 'melayang 18s ease-in-out infinite',
                'melayang-lambat': 'melayang 26s ease-in-out infinite reverse',
                putar: 'putar 40s linear infinite',
                'putar-balik': 'putar 28s linear infinite reverse',
                pindai: 'pindai 3.2s ease-in-out infinite',
                muncul: 'muncul .6s cubic-bezier(.2, .8, .2, 1) both',
                goyang: 'goyang .45s ease-in-out',
            },
        },
    },

    plugins: [],
};
