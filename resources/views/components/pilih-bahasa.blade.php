@props(['gelap' => false])
{{-- Pemilih bahasa: English (default) / Bahasa Indonesia --}}
@php $aktif = app()->getLocale(); @endphp
<div {{ $attributes->merge(['class' => 'inline-flex items-center rounded-xl p-0.5 text-xs font-bold ' . ($gelap ? 'bg-white/10 ring-1 ring-white/15' : 'bg-slate-100 ring-1 ring-slate-200')]) }}
     role="group" aria-label="{{ __('Language') }}">
    @foreach(['en' => 'EN', 'id' => 'ID'] as $kode => $label)
        <a href="{{ route('bahasa', $kode) }}" hreflang="{{ $kode }}" lang="{{ $kode }}"
           title="{{ $kode === 'en' ? 'English' : 'Bahasa Indonesia' }}"
           @if($aktif === $kode) aria-current="true" @endif
           class="rounded-lg px-2.5 py-1 transition {{ $aktif === $kode
               ? ($gelap ? 'bg-white text-brand-800 shadow-sm' : 'bg-white text-brand-700 shadow-sm')
               : ($gelap ? 'text-brand-100 hover:text-white' : 'text-slate-500 hover:text-brand-700') }}">{{ $label }}</a>
    @endforeach
</div>
