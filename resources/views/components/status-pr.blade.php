@props(['pr'])
@php
    $warna = match (true) {
        $pr->terlambat              => 'bg-accent-600 text-white',
        $pr->status === 'diajukan'  => 'bg-amber-100 text-amber-800',
        $pr->status === 'po'        => 'bg-brand-50 text-brand-700',
        $pr->status === 'sebagian'  => 'bg-sky-50 text-sky-700',
        $pr->status === 'selesai'   => 'bg-emerald-50 text-emerald-700',
        default                     => 'bg-slate-100 text-slate-500',
    };
@endphp
<span {{ $attributes->merge(['class' => "badge $warna"]) }}>{{ $pr->terlambat ? __('Late') . ' · ' : '' }}{{ $pr->label_status }}</span>
