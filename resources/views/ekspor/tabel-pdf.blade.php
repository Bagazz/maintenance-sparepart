{{--
    PDF tabel umum dengan kop Detpak.
    $judulDok, $subjudul, $keterangan[], $header[] (teks), $baris[][] (nilai siap tampil), $kanan[] (indeks kolom rata kanan),
    $ringkasan[] (pasangan label => nilai, tampil di atas tabel), $total[] (opsional: baris total, indeks => teks), $catatan (opsional).
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $judulDok }}</title>
    <style>
        @page { margin: 13mm 11mm 16mm 11mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.6pt; color: #0f172a; line-height: 1.3; }
        p { margin: 0; }
        @include('ekspor._gaya')
        .ringkas { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
        .ringkas td { border: 0.7pt solid #cbd5e1; padding: 4pt 6pt; }
        .ringkas .label { font-size: 6.4pt; font-weight: bold; text-transform: uppercase; letter-spacing: .3pt; color: #64748b; }
        .ringkas .nilai { font-size: 10.5pt; font-weight: bold; }
        .data { width: 100%; border-collapse: collapse; }
        .data thead { display: table-header-group; }
        .data th { background: #004e92; color: #fff; font-size: 6.8pt; text-align: left; padding: 4pt 4pt; }
        .data td { padding: 3pt 4pt; border-bottom: 0.5pt solid #e2e8f0; vertical-align: top; }
        .data tr.genap td { background: #f8fafc; }
        .data .kanan { text-align: right; white-space: nowrap; }
        .data tr.total td { border-top: 1pt solid #004e92; font-weight: bold; background: #eef4fb; }
        .data tr { page-break-inside: avoid; }
        .catatan { margin-top: 7pt; font-size: 7pt; color: #64748b; }
        .kosong { text-align: center; padding: 18pt; color: #94a3b8; }
    </style>
</head>
<body>
    @include('ekspor._kop')

    @if(!empty($ringkasan))
        <table class="ringkas"><tr>
            @foreach($ringkasan as $label => $nilai)
                <td><p class="label">{{ $label }}</p><p class="nilai">{{ $nilai }}</p></td>
            @endforeach
        </tr></table>
    @endif

    <table class="data">
        <thead><tr>
            @foreach($header as $i => $h)<th @class(['kanan' => in_array($i, $kanan ?? [], true)])>{{ $h }}</th>@endforeach
        </tr></thead>
        <tbody>
            @forelse($baris as $n => $r)
                <tr @class(['genap' => $n % 2 === 1])>
                    @foreach(array_values($r) as $i => $v)<td @class(['kanan' => in_array($i, $kanan ?? [], true)])>{{ $v }}</td>@endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($header) }}" class="kosong">{{ __('No data.') }}</td></tr>
            @endforelse
            @if(!empty($total) && count($baris))
                <tr class="total">
                    @foreach($header as $i => $h)<td @class(['kanan' => in_array($i, $kanan ?? [], true)])>{{ $total[$i] ?? '' }}</td>@endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($catatan))<p class="catatan">{{ $catatan }}</p>@endif
</body>
</html>
