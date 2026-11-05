@php $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp
<x-shell :title="__('Goods Receipt')" :subtitle="__('Record incoming goods — stock increases immediately, without waiting for the next ERP import')">

    {{-- ============ FORMULIR ============ --}}
    <form method="POST" action="{{ route('penerimaan.simpan') }}" class="space-y-4"
          x-data="formPenerimaan(@js(route('penerimaan.cari')), @js(old('baris', [])), @js(url('/pr')), @js(old('purchase_request_id', $prDipilih)))" @submit="if (!baris.length) { $event.preventDefault(); return; } kirim = true">
        @csrf

        {{-- Barang datang untuk Purchase Request yang dilacak: baris terisi otomatis dari sisa PR --}}
        @if($prTerbuka->isNotEmpty())
            <div class="card flex flex-col gap-3 border-sky-200 bg-sky-50/50 p-4 md:flex-row md:items-center">
                <div class="flex-1">
                    <p class="font-bold text-slate-800">{{ __('Receiving goods for a Purchase Request?') }}</p>
                    <p class="text-xs text-slate-500">{{ __('Pick the PR: its outstanding items, PO number and supplier are filled in. Adjust the quantities to what actually arrived.') }}</p>
                </div>
                <input type="hidden" name="purchase_request_id" :value="prId || ''">
                <select class="input md:w-80" x-model="prId" @change="muatPr()">
                    <option value="">{{ __('— Without PR —') }}</option>
                    @foreach($prTerbuka as $pr)
                        <option value="{{ $pr->id }}">{{ $pr->kode }}{{ $pr->no_po ? ' · PO ' . $pr->no_po : '' }}{{ $pr->pemasok ? ' · ' . $pr->pemasok : '' }} ({{ $pr->label_status }})</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="card grid gap-4 p-5 md:grid-cols-4">
            <div>
                <label class="label" for="tanggal">{{ __('Receipt date') }}</label>
                <input id="tanggal" type="date" name="tanggal" value="{{ old('tanggal', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input">
            </div>
            <div>
                <label class="label" for="pemasok">{{ __('Supplier') }}</label>
                <input id="pemasok" name="pemasok" list="daftar-pemasok" value="{{ old('pemasok') }}" maxlength="150" class="input" placeholder="{{ __('Supplier name') }}">
                <datalist id="daftar-pemasok">@foreach($pemasok as $p)<option value="{{ $p }}">@endforeach</datalist>
            </div>
            <div>
                <label class="label" for="no_dokumen">{{ __('PO / delivery note no.') }}</label>
                <input id="no_dokumen" name="no_dokumen" value="{{ old('no_dokumen') }}" maxlength="100" class="input font-mono" placeholder="{{ __('Optional') }}">
            </div>
            <div>
                <label class="label" for="catatan">{{ __('Notes') }}</label>
                <input id="catatan" name="catatan" value="{{ old('catatan') }}" maxlength="500" class="input" placeholder="{{ __('Optional') }}">
            </div>
        </div>

        {{-- Cari / pindai barang --}}
        <div class="relative z-20">
            <div class="card flex items-center gap-2 p-2 focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-100">
                <svg class="ml-2 h-6 w-6 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8"/></svg>
                <input type="text" x-model="q" x-ref="cari" autocomplete="off" @input.debounce.250ms="saran()" @keydown.enter.prevent="enter()" @keydown.escape="hasil = []"
                       class="min-w-0 flex-1 border-0 bg-transparent py-3 text-base font-bold focus:outline-none focus:ring-0"
                       placeholder="{{ __('Scan the part barcode, or type a part number / name...') }}">
                <button type="button" @click="tambahBaru()" class="btn-ghost whitespace-nowrap px-3 py-2 text-xs">{{ __('+ New item (not yet registered)') }}</button>
            </div>
            <div x-show="hasil.length" x-cloak @click.outside="hasil = []" class="card absolute left-0 right-0 top-full mt-2 max-h-80 overflow-y-auto p-1 shadow-lift">
                <template x-for="h in hasil" :key="h.part_no">
                    <button type="button" @click="pilih(h)" class="flex w-full items-center gap-3 rounded-xl p-2.5 text-left hover:bg-brand-50">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100"><template x-if="h.foto"><img :src="h.foto" alt="" class="h-full w-full object-cover"></template></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-800" x-text="h.deskripsi"></p>
                            <p class="font-mono text-[11px] text-slate-400"><span x-text="h.part_no"></span> · <span class="text-brand-600" x-text="h.lokasi"></span></p>
                        </div>
                        <span class="badge bg-slate-100 text-slate-600" x-text="@js(__('Stock')) + ' ' + h.stok"></span>
                    </button>
                </template>
            </div>
            <p x-show="tidakAda" x-cloak class="mt-2 text-sm font-semibold text-amber-700">
                {{ __('Not found.') }} <button type="button" class="underline" @click="tambahBaru(q)">{{ __('Add it as a new item') }}</button>
            </p>
        </div>

        {{-- Baris barang --}}
        <div class="card overflow-hidden">
            <div class="overflow-x-auto scroll-thin">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('Item') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Current stock') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Quantity received') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Unit price (Rp)') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Subtotal') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(b, i) in baris" :key="b.kunci">
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="hidden" :name="'baris[' + i + '][baru]'" :value="b.baru ? 1 : 0">
                                    <template x-if="!b.baru">
                                        <div>
                                            <input type="hidden" :name="'baris[' + i + '][part_no]'" :value="b.part_no">
                                            <p class="font-bold text-slate-800" x-text="b.deskripsi"></p>
                                            <p class="font-mono text-[11px] text-slate-400"><span x-text="b.part_no"></span> · <span class="text-brand-600" x-text="b.lokasi"></span></p>
                                        </div>
                                    </template>
                                    <template x-if="b.baru">
                                        <div class="grid min-w-[22rem] gap-2 sm:grid-cols-[1fr_2fr_1fr]">
                                            <span class="badge col-span-full w-max bg-amber-100 text-amber-800">{{ __('New item') }}</span>
                                            <input :name="'baris[' + i + '][part_no]'" x-model="b.part_no" required maxlength="100" class="input py-2 font-mono text-xs" placeholder="{{ __('Part number') }}">
                                            <input :name="'baris[' + i + '][deskripsi]'" x-model="b.deskripsi" required maxlength="255" class="input py-2 text-xs uppercase" placeholder="{{ __('Description') }}">
                                            <input :name="'baris[' + i + '][lokasi_rak]'" x-model="b.lokasi" maxlength="50" class="input py-2 font-mono text-xs uppercase" placeholder="{{ __('Bin, e.g. M03-B5D1') }}">
                                        </div>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-right font-mono" x-text="b.baru ? '–' : b.stok"></td>
                                <td class="px-4 py-3 text-right">
                                    <input type="number" :name="'baris[' + i + '][qty]'" x-model.number="b.qty" min="1" step="1" required class="input w-24 py-2 text-right font-mono font-bold" :id="'qty-' + b.kunci">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <input type="number" :name="'baris[' + i + '][harga]'" x-model.number="b.harga" min="0" step="0.01" class="input w-36 py-2 text-right font-mono">
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-slate-800" x-text="rupiah((b.qty || 0) * (b.harga || 0))"></td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" @click="baris.splice(i, 1)" class="rounded-lg p-1.5 text-slate-400 hover:bg-accent-50 hover:text-accent-600" aria-label="{{ __('Remove') }}">&times;</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!baris.length">
                            <td colspan="6" class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No items yet. Scan or search for the items that arrived.') }}</td>
                        </tr>
                    </tbody>
                    <tfoot x-show="baris.length" class="bg-slate-50 font-bold">
                        <tr>
                            <td class="px-4 py-3" colspan="2" x-text="baris.length + ' ' + @js(__('items'))"></td>
                            <td class="px-4 py-3 text-right font-mono" x-text="baris.reduce((t, b) => t + (b.qty || 0), 0)"></td>
                            <td></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right" x-text="rupiah(baris.reduce((t, b) => t + (b.qty || 0) * (b.harga || 0), 0))"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <p class="flex-1 text-xs text-slate-500">{{ __('Stock increases as soon as you save. The same receipt must still be posted in ERP; the next ERP import aligns stock with ERP and its purchase values are counted only once.') }}</p>
            <button class="btn-success px-8 py-3 text-base" :disabled="!baris.length || kirim">
                <span x-show="!kirim">{{ __('Save receipt & add to stock') }}</span>
                <span x-show="kirim" x-cloak>{{ __('Saving...') }}</span>
            </button>
        </div>
    </form>

    {{-- ============ RIWAYAT ============ --}}
    <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <p class="font-extrabold text-slate-900">{{ __('Receipt history') }}</p>
            <span class="text-xs font-semibold text-slate-500">{{ __(':n receipts this month', ['n' => $bulanIni]) }}</span>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($riwayat as $d)
                <a href="{{ route('penerimaan.show', $d) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-brand-50/40 {{ $d->status === 'batal' ? 'opacity-60' : '' }}">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800">{{ $d->kode }} · {{ $d->tanggal->translatedFormat('d M Y') }}
                            @if($d->status === 'batal')<span class="badge ml-1 bg-slate-100 text-slate-500">{{ __('Cancelled') }}</span>@endif
                        </p>
                        <p class="text-xs text-slate-500">{{ $d->pemasok ?: '-' }}@if($d->no_dokumen) · <span class="font-mono">{{ $d->no_dokumen }}</span>@endif · {{ $d->user?->name }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-extrabold text-slate-800">{{ $d->status === 'batal' ? '–' : $rp($d->total_rp ?? 0) }}</p>
                        <p class="text-[11px] text-slate-400">{{ __(':n lines · :q pcs', ['n' => $d->baris_count, 'q' => (int) ($d->total_qty ?? 0)]) }}</p>
                    </div>
                </a>
            @empty
                <p class="py-10 text-center text-sm font-semibold text-slate-400">{{ __('No goods receipts yet.') }}</p>
            @endforelse
        </div>
    </section>
    <div>{{ $riwayat->links() }}</div>

    <x-slot:scripts>
        <script>
            function formPenerimaan(urlCari, lama, urlPr, prAwal) {
                let urut = 0;
                return {
                    q: '', hasil: [], tidakAda: false, kirim: false, prId: prAwal ? String(prAwal) : '',
                    init() { if (this.prId && !this.baris.length) this.muatPr(); },
                    // Isi baris dari sisa barang PR yang dipilih (baris PR sebelumnya diganti).
                    async muatPr() {
                        this.baris = this.baris.filter(b => !b.dariPr);
                        if (!this.prId) return;
                        const r = await fetch(urlPr + '/' + this.prId + '/sisa', { headers: { Accept: 'application/json' } });
                        if (!r.ok) return;
                        const d = await r.json();
                        d.baris.forEach(h => this.baris.push({ kunci: ++urut, part_no: h.part_no, deskripsi: h.deskripsi, lokasi: h.lokasi, stok: h.stok, qty: h.qty, harga: h.harga, baru: false, dariPr: true }));
                        const pemasok = document.getElementById('pemasok'), dok = document.getElementById('no_dokumen');
                        if (d.pemasok && !pemasok.value) pemasok.value = d.pemasok;
                        if (d.no_po && !dok.value) dok.value = d.no_po;
                    },
                    // Isian lama dipulihkan bila validasi gagal.
                    baris: (Array.isArray(lama) ? lama : Object.values(lama || {})).map(b => ({
                        kunci: ++urut, part_no: b.part_no || '', deskripsi: b.deskripsi || b.part_no || '', lokasi: b.lokasi_rak || '',
                        stok: '…', qty: parseInt(b.qty) || 1, harga: parseFloat(b.harga) || 0, baru: b.baru == 1,
                    })),
                    rupiah(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n)); },
                    async ambil() {
                        const r = await fetch(urlCari + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                        return r.ok ? r.json() : [];
                    },
                    async saran() {
                        this.tidakAda = false;
                        if (this.q.trim().length < 2) { this.hasil = []; return; }
                        this.hasil = await this.ambil();
                    },
                    // Pemindai barcode mengetik part number lalu Enter -> langsung masuk daftar.
                    async enter() {
                        if (!this.q.trim()) return;
                        const h = await this.ambil();
                        const persis = h.find(x => x.persis);
                        if (persis) return this.pilih(persis);
                        if (h.length === 1) return this.pilih(h[0]);
                        this.hasil = h;
                        this.tidakAda = h.length === 0;
                    },
                    pilih(h) {
                        const ada = this.baris.find(b => !b.baru && b.part_no === h.part_no);
                        if (ada) { ada.qty++; this.fokus(ada); }
                        else {
                            const b = { kunci: ++urut, part_no: h.part_no, deskripsi: h.deskripsi, lokasi: h.lokasi, stok: h.stok, qty: 1, harga: h.harga, baru: false };
                            this.baris.push(b);
                            this.fokus(b);
                        }
                        this.q = ''; this.hasil = []; this.tidakAda = false;
                    },
                    tambahBaru(partNo = '') {
                        this.baris.push({ kunci: ++urut, part_no: partNo.trim(), deskripsi: '', lokasi: '', stok: 0, qty: 1, harga: 0, baru: true });
                        this.q = ''; this.hasil = []; this.tidakAda = false;
                    },
                    fokus(b) { this.$nextTick(() => document.getElementById('qty-' + b.kunci)?.select()); },
                };
            }
        </script>
    </x-slot:scripts>
</x-shell>
