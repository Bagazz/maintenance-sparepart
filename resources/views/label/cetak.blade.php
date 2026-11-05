@php
    [$w, $h] = $mm;
    // Ukuran huruf & barcode per ukuran label (mm).
    $gaya = [
        'kecil'  => ['pad' => 1.5, 'judul' => 3.8, 'sub' => 2.0, 'bc' => 8,  'bcTinggi' => 40, 'isiBaris' => 0],
        'sedang' => ['pad' => 2.2, 'judul' => 5.0, 'sub' => 2.4, 'bc' => 12, 'bcTinggi' => 40, 'isiBaris' => 1],
        'besar'  => ['pad' => 3.2, 'judul' => 7.0, 'sub' => 3.0, 'bc' => 18, 'bcTinggi' => 50, 'isiBaris' => 2],
    ][$ukuran];
    $printer = $mode === 'printer';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $jenis === 'laci' ? __('Drawer labels') : __('Part labels') }} · PT Detpak Indonesia</title>
    @vite(['resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #e2e8f0; color: #0f172a; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 24px; background: #fff; border-bottom: 1px solid #cbd5e1; }
        .bar p { margin: 0; font-size: 13px; color: #475569; }
        .bar strong { color: #0f172a; font-size: 15px; }
        .bar button:disabled { opacity: .45; cursor: not-allowed; }
        .bar button { background: #004e92; color: #fff; border: 0; border-radius: 12px; padding: 10px 20px; font: inherit; font-weight: 700; cursor: pointer; }
        .lembar { display: grid; grid-template-columns: repeat(auto-fill, {{ $w }}mm); justify-content: center; gap: 0; padding: 20px; }
        .label {
            width: {{ $w }}mm; height: {{ $h }}mm; padding: {{ $gaya['pad'] }}mm;
            background: #fff; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
            border: 0.2mm dashed #94a3b8; break-inside: avoid;
        }
        .atas { display: flex; align-items: baseline; justify-content: space-between; gap: 1mm; }
        .judul { font-size: {{ $gaya['judul'] }}mm; font-weight: 800; line-height: 1.05; white-space: nowrap; }
        .judul-part { font-size: {{ $gaya['sub'] * 1.25 }}mm; font-weight: 800; line-height: 1.15; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .sub { font-size: {{ $gaya['sub'] }}mm; font-weight: 700; color: #475569; white-space: nowrap; }
        .isi { font-size: {{ $gaya['sub'] }}mm; color: #334155; line-height: 1.2; overflow: hidden; display: -webkit-box; -webkit-line-clamp: {{ max(1, $gaya['isiBaris']) }}; -webkit-box-orient: vertical; }
        .bin { font-family: 'JetBrains Mono', monospace; font-size: {{ $gaya['sub'] * 1.2 }}mm; font-weight: 800; color: #004e92; }
        /* Batang barcode direntang selebar label (semua batang ikut skala yang sama -> tetap valid),
           sisakan zona kosong kiri-kanan supaya scanner mudah membaca. */
        svg.bc { display: block; width: calc(100% - 4mm); margin: 0 auto; height: {{ $gaya['bc'] }}mm; }
        .kode { text-align: center; font-family: 'JetBrains Mono', monospace; font-size: {{ $gaya['sub'] * 1.1 }}mm; font-weight: 800; letter-spacing: .2mm; line-height: 1; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            @if($printer)
                @page { size: {{ $w }}mm {{ $h }}mm; margin: 0; }
                .lembar { display: block; padding: 0; }
                .label { border: 0; page-break-after: always; break-after: page; }
                .label:last-child { page-break-after: auto; break-after: auto; }
            @else
                @page { size: A4; margin: 8mm; }
                .lembar { padding: 0; justify-content: start; }
            @endif
        }
    </style>
</head>
<body>
    <div class="bar">
        <div>
            <strong>{{ __(':n labels', ['n' => $label->count()]) }}</strong>
            <p>{{ __('size.' . $ukuran) }} {{ $w }} × {{ $h }} mm · {{ $printer ? __('Label printer (one label per page)') : __('A4 sticker sheet (cut by hand)') }}</p>
            <p>{{ __('In the print dialog, set scale to 100% (Actual size) and margins to None so the barcode is not shrunk.') }}</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            {{-- Setelah label keluar dari printer & ditempel: tandai, supaya hilang dari daftar "perlu label". --}}
            <form method="POST" action="{{ route('label.tandai') }}" style="margin:0">
                @csrf
                <input type="hidden" name="jenis" value="{{ $jenis }}">
                <input type="hidden" name="daftar" value="{{ $tandai->implode(',') }}">
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#475569;margin-right:6px">
                    <input type="checkbox" id="sudah-tempel" onchange="document.getElementById('tombol-tandai').disabled = !this.checked"> {{ __('Printed & attached') }}
                </label>
                <button type="submit" id="tombol-tandai" disabled title="{{ __('Tick the box first to confirm the labels have been printed and attached.') }}" style="background:#059669">{{ __('Mark as attached (:n)', ['n' => number_format($tandai->count(), 0, ',', '.')]) }}</button>
            </form>
            <button onclick="window.print()">{{ __('Print') }}</button>
        </div>
    </div>

    <div class="lembar">
        @foreach($label as $l)
            <div class="label">
                @if($jenis === 'laci')
                    <div class="atas">
                        <span class="judul">{{ $l['judul'] }}</span>
                        <span class="sub">{{ $l['gudang'] }} · {{ __(':n parts', ['n' => $l['jumlah']]) }}</span>
                    </div>
                    <div><svg class="bc" data-kode="{{ $l['kode'] }}" preserveAspectRatio="none"></svg><div class="kode">{{ $l['kode'] }}</div></div>
                    @if($namaPart && $gaya['isiBaris'])
                        <div class="isi">{{ implode(' · ', $l['isi']) }}</div>
                    @endif
                @else
                    <div class="judul-part">{{ $l['judul'] }}</div>
                    <div><svg class="bc" data-kode="{{ $l['kode'] }}" preserveAspectRatio="none"></svg><div class="kode">{{ $l['kode'] }}</div></div>
                    <div class="atas"><span class="bin">{{ $l['bin'] }}</span><span class="sub">PT Detpak · M03</span></div>
                @endif
            </div>
        @endforeach
    </div>

    <script>
        // JsBarcode dimuat oleh app.js (modul Vite) -> jalankan setelah DOM & modul siap.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('svg.bc').forEach(el => {
                try {
                    window.JsBarcode(el, el.dataset.kode, {
                        format: 'CODE128', height: {{ $gaya['bcTinggi'] }}, width: 2, margin: 0, displayValue: false,
                    });
                    el.setAttribute('preserveAspectRatio', 'none');
                } catch (e) {
                    // Kode yang tidak bisa jadi CODE128: tampilkan teksnya saja (aman, tanpa HTML).
                    const p = document.createElement('p');
                    p.style.cssText = 'color:#e4002b;font-size:3mm;margin:0;font-family:monospace';
                    p.textContent = el.dataset.kode;
                    el.replaceWith(p);
                }
            });
        });
    </script>
</body>
</html>
