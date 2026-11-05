import './bootstrap';

// Font dibundel lokal (tidak bergantung Google Fonts / internet).
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';
import '@fontsource/plus-jakarta-sans/800.css';
import '@fontsource/jetbrains-mono/500.css';
import '@fontsource/jetbrains-mono/700.css';

import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';
import JsBarcode from 'jsbarcode';

Chart.register(...registerables);

// Dipakai skrip di halaman (dashboard -> grafik, kartu karyawan -> barcode).
window.Chart = Chart;
window.JsBarcode = JsBarcode;
window.Alpine = Alpine;

Alpine.start();

// Beri tahu skrip halaman bahwa library sudah siap (modul Vite dimuat setelah HTML diparse).
document.dispatchEvent(new Event('aplikasi:siap'));
