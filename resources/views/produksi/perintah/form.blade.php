{{--
    Isi form rencana perintah produksi (dipakai create & edit).

    Variabel yang diharapkan:
      $tahapan    TahapanProduksi yang sedang dibuka
      $produksi   instance Produksi (kosong saat tambah)
      $daftarBom  resep aktif milik tahapan ini
--}}

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Periksa kembali isian berikut:
        <ul class="list-disc list-inside mt-1">
            @foreach ($errors->all() as $pesan)
                <li>{{ $pesan }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($daftarBom->isEmpty())
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Belum ada resep BOM aktif untuk tahapan <strong>{{ $tahapan->nama_tahapan }}</strong>.
        Buat resepnya lebih dulu di <a href="{{ route('produksi.bom.create') }}" class="font-medium underline">BOM / Komposisi</a>,
        karena rencana pemakaian bahan disalin dari sana.
    </div>
@endif

<div class="space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="tanggal_produksi" value="Tanggal Produksi" />
            <x-text-input id="tanggal_produksi" name="tanggal_produksi" type="date" class="mt-1 block w-full" required
                          value="{{ old('tanggal_produksi', optional($produksi->tanggal_produksi)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('tanggal_produksi')" />
        </div>

        <div>
            <x-input-label for="jumlah_target" value="Jumlah Target" />
            <x-text-input id="jumlah_target" name="jumlah_target" type="number" class="mt-1 block w-full"
                          min="0.01" step="0.01" required
                          value="{{ old('jumlah_target', $produksi->jumlah_target ?? 1) }}" />
            <p class="mt-1 text-xs text-gray-500">Berapa unit barang hasil yang hendak diproduksi.</p>
            <x-input-error class="mt-1" :messages="$errors->get('jumlah_target')" />
        </div>
    </div>

    <div>
        <x-input-label for="bom_id" value="Resep BOM" />
        <select id="bom_id" name="bom_id" required
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">-- pilih resep --</option>
            @foreach ($daftarBom as $b)
                <option value="{{ $b->id }}" @selected((string) old('bom_id', $produksi->bom_id) === (string) $b->id)>
                    {{ $b->kode_bom }} &mdash; {{ $b->nama_bom }}
                    (menghasilkan {{ $b->barang?->nama_barang }}, {{ $b->detail_count }} bahan)
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">
            Resep menentukan barang hasil sekaligus rencana pemakaian bahannya. Hanya resep milik
            tahapan {{ $tahapan->nama_tahapan }} yang ditampilkan.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('bom_id')" />
    </div>

    <div>
        <x-input-label for="keterangan" value="Keterangan" />
        <textarea id="keterangan" name="keterangan" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Catatan perintah kerja (opsional)">{{ old('keterangan', $produksi->keterangan) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('keterangan')" />
    </div>
</div>
