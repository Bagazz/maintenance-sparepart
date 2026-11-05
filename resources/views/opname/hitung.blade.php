<x-shell :title="__('Count rack :rak', ['rak' => \Illuminate\Support\Str::after($opname->kode_rak, '-')])"
         :subtitle="$opname->kode . ' · ' . $opname->petugas . ($opname->hitung_buta ? ' · ' . __('blind count') : '')"
         :kembali="route('opname.index')">

    <div x-data="hitungOpname(@js($items), @js($ringkasan), @js(route('opname.simpan', [$opname, '__ID__'])), @js(request('cari', '')))" class="space-y-4">

        {{-- ============ PROGRES + CARI (menempel di atas saat scroll) ============ --}}
        <div class="sticky top-16 z-20 -mx-4 space-y-3 bg-slate-100/95 px-4 pb-3 pt-1 backdrop-blur sm:mx-0 sm:px-0 lg:top-20">
            <div class="card p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-slate-700"><span class="text-2xl font-extrabold text-slate-900" x-text="ringkasan.dihitung"></span> / <span x-text="ringkasan.total"></span> {{ __('counted') }}</p>
                    <div class="flex gap-1.5 text-[11px] font-bold">
                        <span class="badge bg-emerald-50 text-emerald-700" x-text="ringkasan.cocok + ' ' + @js(__('match'))"></span>
                        <span class="badge bg-accent-50 text-accent-700" x-show="ringkasan.kurang_n" x-text="'−' + ringkasan.kurang_n"></span>
                        <span class="badge bg-amber-100 text-amber-800" x-show="ringkasan.lebih_n" x-text="'+' + ringkasan.lebih_n"></span>
                    </div>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600 transition-all" :style="'width:' + ringkasan.persen + '%'"></div></div>
            </div>

            <div class="card flex items-center gap-2 p-2 focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-100">
                <svg class="ml-2 h-5 w-5 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2M7 8v8M10 8v8M13 8v8M17 8v8"/></svg>
                <input type="search" x-model="cari" @keydown.enter.prevent="enter()" autocomplete="off"
                       class="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-base font-bold focus:outline-none focus:ring-0"
                       placeholder="{{ __('Scan drawer / part, or type a name...') }}">
                <button type="button" x-show="cari" @click="cari = ''" class="px-2 text-xl text-slate-400" aria-label="{{ __('Clear') }}">&times;</button>
            </div>

            <div class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 ring-1 ring-slate-200">
                @foreach(['belum' => __('To count'), 'sudah' => __('Counted'), 'selisih' => __('Variance'), 'semua' => __('All')] as $kode => $label)
                    <button type="button" @click="tab = '{{ $kode }}'" :class="tab === '{{ $kode }}' ? 'bg-brand-600 text-white' : 'text-slate-600'"
                            class="flex-1 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-bold transition">
                        {{ $label }} <span class="opacity-75" x-text="'(' + jumlahTab('{{ $kode }}') + ')'"></span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ============ DAFTAR PART ============ --}}
        <div class="space-y-2">
            <template x-for="(it, i) in tampil" :key="it.id">
                <div>
                    <p x-show="i === 0 || tampil[i - 1].laci !== it.laci" class="px-1 pb-1 pt-3 text-xs font-extrabold uppercase tracking-wider text-slate-500"
                       x-text="it.tambahan ? @js(__('Added during count')) : (it.laci ? @js(__('Drawer')) + ' ' + it.laci : @js(__('Rack (no drawer)')))"></p>
                    <div class="card flex flex-col gap-3 p-3 sm:flex-row sm:items-center"
                         :class="it.fisik === null ? '' : (selisih(it) === null || selisih(it) === 0 ? 'border-emerald-200 bg-emerald-50/40' : 'border-accent-200 bg-accent-50/40')">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                <template x-if="it.foto"><img :src="it.foto" alt="" loading="lazy" class="h-full w-full object-cover"></template>
                            </div>
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-bold leading-snug text-slate-800" x-text="it.deskripsi"></p>
                                <p class="font-mono text-[11px] text-slate-400"><span x-text="it.part_no"></span> · <span class="font-bold text-brand-600" x-text="it.bin"></span></p>
                            </div>
                        </div>

                        {{-- Belum dihitung / sedang diubah --}}
                        <form x-show="it.fisik === null || it.ubah" @submit.prevent="simpan(it)" class="flex items-center gap-2 sm:w-64">
                            <input type="number" inputmode="numeric" min="0" step="1" required :id="'qty-' + it.id" x-model="it.input"
                                   class="input w-full py-3 text-center font-mono text-xl font-bold" placeholder="{{ __('Qty') }}" aria-label="{{ __('Physical quantity') }}">
                            <button class="btn-primary px-5 py-3" :disabled="it.simpan || it.input === '' || it.input === null">
                                <span x-show="!it.simpan">{{ __('Save') }}</span><span x-show="it.simpan" x-cloak>…</span>
                            </button>
                        </form>

                        {{-- Sudah dihitung --}}
                        <div x-show="it.fisik !== null && !it.ubah" class="flex items-center justify-between gap-3 sm:w-64">
                            <div class="text-sm">
                                <p class="font-bold text-slate-800">{{ __('Physical') }} <span class="font-mono text-lg" x-text="it.fisik"></span></p>
                                <p class="text-xs font-semibold" :class="selisih(it) ? 'text-accent-600' : 'text-emerald-700'">
                                    {{ __('System') }} <span class="font-mono" x-text="it.sistem"></span> ·
                                    <span x-text="selisih(it) === 0 ? @js(__('match')) : (selisih(it) > 0 ? '+' : '') + selisih(it)"></span>
                                </p>
                            </div>
                            <button type="button" @click="ubahLagi(it)" class="btn-ghost px-3 py-2 text-xs" x-text="selisih(it) ? @js(__('Recount')) : @js(__('Edit'))"></button>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="tampil.length === 0" class="card p-8 text-center">
                <p class="font-semibold text-slate-500" x-text="cari ? @js(__('No part in this rack matches the search.')) : (tab === 'belum' ? @js(__('All parts in this rack are counted. Review and post the result.')) : @js(__('Nothing here yet.')))"></p>
                <form x-show="cari" method="POST" action="{{ route('opname.tambah', $opname) }}" class="mx-auto mt-4 max-w-sm space-y-2">
                    @csrf
                    <input type="hidden" name="part_no" :value="cari.trim()">
                    <p class="text-xs text-slate-500">{{ __('Found a part here that is not on the list? If you scanned or typed its exact part number, add it to this count.') }}</p>
                    <button class="btn-ghost w-full">{{ __('Add to this count:') }} <span class="font-mono" x-text="cari.trim()"></span></button>
                </form>
            </div>
        </div>

        <p x-show="pesan" x-cloak x-text="pesan" class="rounded-xl bg-accent-50 p-3 text-sm font-bold text-accent-700 ring-1 ring-accent-200"></p>

        {{-- ============ AKSI BAWAH ============ --}}
        <div class="h-20" aria-hidden="true"></div>
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 p-3 backdrop-blur lg:left-72">
            <div class="mx-auto flex max-w-5xl items-center gap-3">
                <p class="hidden flex-1 text-sm font-semibold text-slate-500 sm:block" x-text="ringkasan.belum ? @js(__(':n parts not counted yet', ['n' => '__N__'])).replace('__N__', ringkasan.belum) : @js(__('All parts counted'))"></p>
                <a href="{{ route('opname.tinjau', $opname) }}" class="btn-success flex-1 py-3 text-base sm:flex-none sm:px-8">{{ __('Review & post') }}</a>
            </div>
        </div>
    </div>

    <x-slot:scripts>
        <script>
            function hitungOpname(items, ringkasan, urlSimpan, cariAwal) {
                return {
                    items: items.map(it => ({ ...it, input: it.fisik ?? '', ubah: false, simpan: false })),
                    ringkasan, cari: cariAwal, tab: cariAwal ? 'semua' : 'belum', pesan: '',

                    selisih(it) { return it.fisik === null || it.sistem === null ? null : it.fisik - it.sistem; },
                    cocokTab(it, tab) {
                        if (tab === 'belum') return it.fisik === null || it.ubah;
                        if (tab === 'sudah') return it.fisik !== null;
                        if (tab === 'selisih') return !!this.selisih(it);
                        return true;
                    },
                    cocokCari(it) {
                        const q = this.cari.trim().toLowerCase();
                        if (!q) return true;
                        // Scan barcode laci (kode bin persis) -> hanya isi laci itu (bukan D10..D19 untuk D1).
                        if (this.binPersis(q)) return (it.bin || '').toLowerCase() === q;
                        return q.split(/\s+/).every(k => [it.part_no, it.deskripsi, it.kunci, it.bin].some(v => (v || '').toLowerCase().includes(k)));
                    },
                    binPersis(q) { return this.items.some(it => (it.bin || '').toLowerCase() === q); },
                    get tampil() { return this.items.filter(it => this.cocokTab(it, this.tab) && this.cocokCari(it)); },
                    jumlahTab(tab) { return this.items.filter(it => this.cocokTab(it, tab)).length; },

                    // Enter / scanner: part no persis -> langsung ke kotak qty-nya.
                    enter() {
                        const q = this.cari.trim().toLowerCase();
                        const persis = this.items.find(it => it.part_no.toLowerCase() === q);
                        if (persis) { this.tab = 'semua'; this.$nextTick(() => this.fokus(persis)); return; }
                        const laci = this.items.some(it => (it.bin || '').toLowerCase() === q);
                        if (laci) this.tab = 'semua';
                    },
                    fokus(it) {
                        if (it.fisik !== null) it.ubah = true;
                        this.$nextTick(() => { const el = document.getElementById('qty-' + it.id); el?.scrollIntoView({ block: 'center' }); el?.focus(); });
                    },
                    ubahLagi(it) { it.input = it.fisik; this.fokus(it); },

                    async simpan(it) {
                        it.simpan = true; this.pesan = '';
                        try {
                            const r = await fetch(urlSimpan.replace('__ID__', it.id), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: JSON.stringify({ qty_fisik: parseInt(it.input, 10) }),
                            });
                            const d = await r.json();
                            if (!r.ok) throw new Error(d.message || d.pesan || r.statusText);
                            Object.assign(it, { fisik: d.fisik, sistem: d.sistem, ubah: false });
                            this.ringkasan = d.ringkasan;
                            // Lanjut ke part berikutnya yang belum dihitung (alur cepat tanpa scroll).
                            const berikut = this.tampil.find(x => x.fisik === null);
                            if (berikut && !this.cari) this.fokus(berikut);
                        } catch (e) {
                            this.pesan = @js(__('Not saved:')) + ' ' + e.message;
                        } finally {
                            it.simpan = false;
                        }
                    },
                };
            }
        </script>
    </x-slot:scripts>
</x-shell>
