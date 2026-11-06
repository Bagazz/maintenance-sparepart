{{-- Gaya kop & kaki halaman PDF bersama (dipakai bersama _kop.blade.php). --}}
.ekspor-kop { width: 100%; border-collapse: collapse; border-bottom: 1.4pt solid #004e92; margin-bottom: 9pt; }
.ekspor-kop td { padding: 0 0 6pt 0; }
.ekspor-perusahaan { margin: 0; font-size: 7pt; font-weight: bold; letter-spacing: .8pt; color: #64748b; }
.ekspor-judul { margin: 1pt 0 0; font-size: 16pt; color: #004e92; }
.ekspor-subjudul { margin: 1pt 0 0; font-size: 10.5pt; font-weight: bold; color: #0f172a; }
.ekspor-ket { margin: 1pt 0 0; font-size: 7.6pt; color: #64748b; }
.ekspor-kaki { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #94a3b8; border-top: 0.6pt solid #e2e8f0; padding-top: 2pt; }
.ekspor-kaki table { width: 100%; border-collapse: collapse; }
.ekspor-hal:after { content: counter(page); }
.ekspor-total:after { content: counter(pages); }
