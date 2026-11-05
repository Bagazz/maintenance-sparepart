@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Approvals')" :subtitle="__('Large take-outs by technicians wait here for your approval before stock is reduced')">

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 space-y-6">

            {{-- ============ MENUNGGU ============ --}}
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-extrabold text-slate-900">{{ __('Waiting for approval') }}</h2>
                <span class="flex h-7 min-w-[1.75rem] items-center justify-center rounded-full px-2 text-sm font-extrabold {{ $menunggu->count() ? 'bg-accent-600 text-white' : 'bg-slate-100 text-slate-400' }}">{{ $menunggu->count() }}</span>
            </div>

            @forelse($menunggu as $p)
                <div class="card overflow-hidden border-amber-200" x-data="{ tolak: false }">
                    <div class="flex flex-wrap items-center gap-3 border-b border-amber-100 bg-amber-50/60 px-5 py-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-lg font-extrabold text-white">{{ mb_substr($p->peminjam, 0, 1) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-extrabold text-slate-900">{{ $p->peminjam }}</p>
                            <p class="text-xs font-semibold text-slate-500">
                                <span class="font-mono font-bold text-slate-700">{{ $p->kode }}</span> ·
                                {{ $p->created_at->translatedFormat('d M Y H:i') }} ({{ $p->created_at->diffForHumans() }})
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="kpi-label">{{ __('Total value') }}</p>
                            <p class="font-mono text-xl font-bold text-slate-900">{{ $rp($p->total_nilai) }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-5 py-2">{{ __('Item') }}</th>
                                    <th class="px-3 py-2">{{ __('Machine') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Qty') }}</th>
                                    <th class="px-5 py-2 text-right">{{ __('Value') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($p->items as $it)
                                    <tr>
                                        <td class="px-5 py-2.5">
                                            <p class="font-bold text-slate-800">{{ $it->deskripsi }}</p>
                                            <p class="font-mono text-[11px] text-slate-400">{{ $it->part_no }} · <b class="text-brand-600">{{ $it->lokasi }}</b></p>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span class="badge bg-brand-50 text-brand-700">{{ $it->mesin }}</span>
                                            @if($it->no_pm)<span class="badge bg-slate-100 font-mono text-slate-600">{{ $it->no_pm }}</span>@endif
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono font-bold">{{ $it->jumlah }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono">{{ $rp($it->nilai) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 p-4 sm:flex-row sm:items-start">
                        <form method="POST" action="{{ route('persetujuan.setujui', $p) }}" class="sm:order-2" x-data="{ kirim: false }" @submit="kirim = true">
                            @csrf
                            <button class="btn-success w-full px-6 py-3 sm:w-auto" :disabled="kirim">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Approve & reduce stock') }}
                            </button>
                        </form>
                        <div class="flex-1 sm:order-1">
                            <button type="button" x-show="!tolak" @click="tolak = true; $nextTick(() => $refs.alasan.focus())" class="btn-ghost w-full py-3 hover:text-accent-700 sm:w-auto">{{ __('Reject') }}</button>
                            <form x-show="tolak" x-cloak method="POST" action="{{ route('persetujuan.tolak', $p) }}" class="flex flex-col gap-2 sm:flex-row">
                                @csrf
                                <input x-ref="alasan" name="alasan" maxlength="255" class="input flex-1" placeholder="{{ __('Reason (optional), e.g. use the old stock first') }}">
                                <div class="flex gap-2">
                                    <button type="button" @click="tolak = false" class="btn-ghost">{{ __('Cancel') }}</button>
                                    <button class="btn-danger">{{ __('Reject request') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card flex items-center gap-3 border-emerald-200 bg-emerald-50/70 p-5 text-sm font-semibold text-emerald-800">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                    {{ __('No requests are waiting. Everything has been decided.') }}
                </div>
            @endforelse

            {{-- ============ RIWAYAT ============ --}}
            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3">
                    <p class="font-bold text-slate-800">{{ __('Decided in the last 30 days') }}</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($riwayat as $p)
                        <div class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                            <span class="badge {{ $p->status === 'disetujui' ? 'bg-emerald-50 text-emerald-700' : 'bg-accent-50 text-accent-700' }}">{{ $p->status === 'disetujui' ? __('Approved') : __('Rejected') }}</span>
                            <span class="font-mono text-xs font-bold text-slate-500">{{ $p->kode }}</span>
                            <span class="min-w-0 flex-1 truncate font-semibold text-slate-700">{{ $p->peminjam }} · {{ $p->items->map(fn ($i) => $i->deskripsi . ' x' . $i->jumlah)->implode(', ') }}</span>
                            <span class="font-mono text-slate-700">{{ $rp($p->total_nilai) }}</span>
                            <span class="w-full text-[11px] text-slate-400 sm:w-auto">{{ $p->diputus_oleh }} · {{ $p->diputus_at?->translatedFormat('d M H:i') }}@if($p->alasan_tolak) · “{{ $p->alasan_tolak }}”@endif</span>
                        </div>
                    @empty
                        <p class="py-8 text-center text-sm font-semibold text-slate-400">{{ __('Nothing yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ============ ATURAN ============ --}}
        <aside class="space-y-4 xl:sticky xl:top-28 xl:self-start">
            <form method="POST" action="{{ route('persetujuan.pengaturan') }}" class="card space-y-4 p-5" x-data="{ mode: @js($aturan['mode']) }">
                @csrf
                <div>
                    <p class="font-bold text-slate-800">{{ __('When is approval needed?') }}</p>
                    <p class="text-xs text-slate-500">{{ __('Applies to technicians only. Your own take-outs never need approval.') }}</p>
                </div>
                @foreach([
                    'batas' => [__('Above a limit (recommended)'), __('Only items whose value or quantity reaches the limit below.')],
                    'semua' => [__('Every take-out'), __('Every item a technician confirms waits for your approval.')],
                    'mati'  => [__('Never'), __('Technicians take items directly without approval.')],
                ] as $kode => [$judul, $isi])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition" :class="mode === '{{ $kode }}' ? 'border-brand-400 bg-brand-50/50' : 'border-slate-200'">
                        <input type="radio" name="mode" value="{{ $kode }}" x-model="mode" class="mt-1 h-4 w-4 text-brand-600">
                        <span><span class="block text-sm font-bold text-slate-800">{{ $judul }}</span><span class="text-xs text-slate-500">{{ $isi }}</span></span>
                    </label>
                @endforeach
                <div x-show="mode === 'batas'" class="space-y-3">
                    <div>
                        <label class="label" for="nilai">{{ __('Value per item ≥ (Rp)') }}</label>
                        <input id="nilai" name="nilai" type="number" min="0" step="1000" value="{{ (int) $aturan['nilai'] }}" class="input font-mono">
                    </div>
                    <div>
                        <label class="label" for="qty">{{ __('or quantity per item ≥ (pcs)') }}</label>
                        <input id="qty" name="qty" type="number" min="0" value="{{ $aturan['qty'] }}" class="input font-mono">
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('0 = not used.') }}</p>
                    </div>
                    <div>
                        <label class="label" for="total">{{ __('or total per confirmation ≥ (Rp)') }}</label>
                        <input id="total" name="total" type="number" min="0" step="100000" value="{{ (int) $aturan['total'] }}" class="input font-mono">
                        <p class="mt-1 text-[11px] text-slate-400">{{ __('When the whole cart reaches this total, every item in it waits for approval. 0 = not used.') }}</p>
                    </div>
                </div>
                <button class="btn-primary w-full">{{ __('Save rules') }}</button>
            </form>

            <div class="card space-y-2 p-5 text-sm text-slate-600">
                <p class="font-bold text-slate-800">{{ __('How it works') }}</p>
                <ol class="list-decimal space-y-1 pl-5">
                    <li>{{ __('The technician scans items and confirms the cart as usual.') }}</li>
                    <li>{{ __('Items under the limit are recorded immediately; items above it become a request here and their stock is reserved.') }}</li>
                    <li>{{ __('Approve: stock is reduced and recorded under the technician. Reject: the reservation is released.') }}</li>
                    <li>{{ __('The technician sees the result the next time they sign in.') }}</li>
                </ol>
            </div>
        </aside>
    </div>
</x-shell>
