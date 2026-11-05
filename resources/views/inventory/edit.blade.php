<x-shell :title="__('Complete Part')" :subtitle="$item->part_no . ' · ' . $item->deskripsi" :kembali="request('kembali', route('inventory.kelola'))">

    <form method="POST" action="{{ route('inventory.update', $item) }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')
        <input type="hidden" name="kembali" value="{{ request('kembali') }}">

        <div class="space-y-6 lg:col-span-2">
            <div class="grid gap-6 md:grid-cols-2">
                @foreach([
                    'foto_barang' => [__('Item photo'), __('A close, clear photo of the physical item. This is what technicians see when searching.'), $item->foto_barang_url],
                    'foto_lokasi' => [__('Rack / drawer photo'), __('Where the item sits on the rack or in the drawer, so it is found quickly.'), $item->foto_lokasi_url],
                ] as $kolom => [$judul, $bantuan, $urlSekarang])
                    <div class="card p-5">
                        <p class="font-bold text-slate-800">{{ $judul }}</p>
                        <p class="mb-4 mt-1 text-xs text-slate-500">{{ $bantuan }}</p>
                        <div class="mb-4 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                            <img id="preview-{{ $kolom }}" src="{{ $urlSekarang }}" alt="" class="h-full w-full object-contain {{ $urlSekarang ? '' : 'hidden' }}">
                            <span id="kosong-{{ $kolom }}" class="text-xs font-bold uppercase tracking-widest text-slate-300 {{ $urlSekarang ? 'hidden' : '' }}">{{ __('No photo yet') }}</span>
                        </div>
                        {{-- capture="environment" = langsung buka kamera belakang di HP --}}
                        <label class="btn-primary w-full cursor-pointer">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ __('Take / choose photo') }}
                            <input type="file" name="{{ $kolom }}" accept="image/*" capture="environment" class="hidden" onchange="pratinjau(this, '{{ $kolom }}')">
                        </label>
                        @if($urlSekarang)
                            <label class="mt-3 flex items-center gap-2 text-xs font-bold text-accent-600">
                                <input type="checkbox" name="hapus_{{ $kolom }}" value="1" class="rounded border-slate-300"> {{ __('Delete this photo') }}
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="card space-y-5 p-5">
                <div>
                    <label class="label" for="kategori">{{ __('Category') }}</label>
                    <input id="kategori" type="text" name="kategori" list="daftar-kategori" value="{{ old('kategori', $item->kategori) }}" class="input" placeholder="{{ __('e.g. Bearing, Relay, Belt') }}">
                    <datalist id="daftar-kategori">
                        @foreach($daftarKategori as $k)<option value="{{ $k }}">@endforeach
                    </datalist>
                </div>
                <div>
                    <label class="label" for="kata_kunci">{{ __('Keywords / nicknames') }}</label>
                    <textarea id="kata_kunci" name="kata_kunci" rows="3" maxlength="500" class="input" placeholder="{{ __('Shop-floor terms, comma separated. Example: small relay, omron 8-pin relay') }}">{{ old('kata_kunci', $item->kata_kunci) }}</textarea>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card p-5">
                <x-lokasi :item="$item" besar class="border-0 bg-transparent p-0" />
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
                    <span class="font-semibold text-slate-500">{{ __('Stock') }}</span>
                    <span class="font-mono text-lg font-bold">{{ number_format($item->stock) }}</span>
                </div>
            </div>

            <button class="btn-success w-full py-3.5 text-base">{{ __('Save') }}</button>
        </div>
    </form>


    <x-slot:scripts>
        <script>
            // Foto kamera HP biasanya 3-8 MB. Diperkecil dulu (sisi terpanjang 1600px, JPEG 80%)
            // supaya upload cepat & storage hemat. Kalau gagal, file asli tetap dikirim.
            async function pratinjau(input, kolom) {
                const file = input.files && input.files[0];
                if (!file) return;

                const img = document.getElementById('preview-' + kolom);
                img.src = URL.createObjectURL(file);
                img.classList.remove('hidden');
                document.getElementById('kosong-' + kolom).classList.add('hidden');

                try {
                    const bitmap = await createImageBitmap(file);
                    const skala = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                    if (skala === 1 && file.size < 800 * 1024) return;

                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(bitmap.width * skala);
                    canvas.height = Math.round(bitmap.height * skala);
                    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

                    const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.8));
                    if (!blob) return;

                    const dt = new DataTransfer();
                    dt.items.add(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
                    input.files = dt.files;
                } catch (e) {
                    console.warn('Kompresi foto dilewati:', e);
                }
            }
        </script>
    </x-slot:scripts>
</x-shell>
