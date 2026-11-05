<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 18px; color: #004e92; margin-bottom: 2px; }
        p.sub { font-size: 10px; color: #777; margin-top: 0; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #004e92; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) { background: #f8fafc; }
        .badge { padding: 2px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #15803d; }
        .badge-gray { background: #f1f5f9; color: #64748b; }
    </style>
</head>
<body>
    <h1>{{ __('Spare Part Take-out History') }}</h1>
    <p class="sub">
        PT Detpak Indonesia — {{ __('Printed') }} {{ now()->format('d-m-Y H:i') }}
        @if($q) | {{ __('Search') }}: "{{ $q }}" @endif
        @if($filterMesin) | {{ __('Machine') }}: {{ $filterMesin }} @endif
        @if($filterOperator) | {{ __('Operator') }}: {{ $filterOperator }} @endif
        | {{ __('Total: :n records', ['n' => $histories->count()]) }}
    </p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Time') }}</th>
                <th>Shift</th>
                <th>Part Number</th>
                <th>Qty</th>
                <th>{{ __('Taken by') }}</th>
                <th>{{ __('Machine') }}</th>
                <th>{{ __('Operator') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($histories as $i => $h)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $h->created_at->format('d-m-Y') }}</td>
                    <td>{{ $h->created_at->format('H:i:s') }}</td>
                    <td>{{ $h->shift }}</td>
                    <td>{{ $h->part_no }}</td>
                    <td>{{ $h->jumlah }}</td>
                    <td>{{ $h->peminjam ?? '-' }}</td>
                    <td>{{ $h->machine ?? '-' }}</td>
                    <td>{{ $h->operator ?? '-' }}</td>
                    <td>
                        @if($h->status === 'diretur_semua')
                            <span class="badge badge-green">{{ mb_strtoupper(__('Fully returned')) }}</span>
                        @else
                            <span class="badge badge-gray">{{ mb_strtoupper($h->qty_returned > 0 ? __('Used') . ' (' . __(':n returned', ['n' => $h->qty_returned]) . ')' : __('Used')) }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center; padding: 20px;">{{ __('No data.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>