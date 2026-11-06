{{--
    Kop & kaki halaman PDF yang sama untuk semua ekspor (dompdf).
    Parameter: $judulDok (judul dokumen), $subjudul (opsional, baris kedua tebal), $keterangan (array baris kecil), $kaki (teks kiri kaki halaman).
    Kaki halaman ditaruh lebih dulu (position: fixed) supaya muncul di setiap halaman.
--}}
<div class="ekspor-kaki">
    <table><tr>
        <td>PT Detpak Indonesia · {{ $kaki ?? $judulDok }} · {{ __('Generated :tgl', ['tgl' => now()->translatedFormat('d M Y H:i')]) }}</td>
        <td style="text-align: right; white-space: nowrap">{{ __('Page') }} <span class="ekspor-hal"></span> / <span class="ekspor-total"></span></td>
    </tr></table>
</div>
<table class="ekspor-kop">
    <tr>
        <td style="vertical-align: middle">
            <p class="ekspor-perusahaan">PT DETPAK INDONESIA · {{ mb_strtoupper(\App\Support\Ekspor::DIVISI) }}</p>
            <h1 class="ekspor-judul">{{ $judulDok }}</h1>
            @if(!empty($subjudul))<p class="ekspor-subjudul">{{ $subjudul }}</p>@endif
            @foreach(array_filter($keterangan ?? []) as $k)<p class="ekspor-ket">{{ $k }}</p>@endforeach
        </td>
        <td style="width: 34mm; text-align: right; vertical-align: top">
            <img src="{{ \App\Support\Ekspor::logo() }}" style="width: 32mm" alt="Detpak">
        </td>
    </tr>
</table>
