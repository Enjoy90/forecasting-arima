{{--
    Isi form barang, dipakai bersama oleh create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $barang          instance Barang (kosong saat tambah)
      $daftarJenis     pilihan enum jenis_barang
      $daftarKategori  koleksi Kategori
      $daftarSupplier  koleksi Supplier
      $kodeUsulan      nilai awal kode saat tambah (opsional)
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

<div class="space-y-6">
    {{-- ---------------------------------------------------------------- --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Identitas Barang</h3>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <x-input-label for="kode_barang" value="Kode Barang" />
                <x-text-input id="kode_barang" name="kode_barang" type="text" class="mt-1 block w-full"
                              maxlength="30" required autofocus
                              value="{{ old('kode_barang', $barang->kode_barang ?? ($kodeUsulan ?? '')) }}" />
                <p class="mt-1 text-xs text-gray-500">Pola: BB- bahan baku, SJ- setengah jadi, BJ- barang jadi.</p>
                <x-input-error class="mt-1" :messages="$errors->get('kode_barang')" />
            </div>

            <div class="sm:col-span-2">
                <x-input-label for="nama_barang" value="Nama Barang" />
                <x-text-input id="nama_barang" name="nama_barang" type="text" class="mt-1 block w-full"
                              maxlength="150" required
                              value="{{ old('nama_barang', $barang->nama_barang) }}" />
                <x-input-error class="mt-1" :messages="$errors->get('nama_barang')" />
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <x-input-label for="jenis_barang" value="Jenis Barang" />
                <select id="jenis_barang" name="jenis_barang" required
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    @foreach ($daftarJenis as $nilai => $label)
                        <option value="{{ $nilai }}"
                            @selected(old('jenis_barang', $barang->jenis_barang ?? 'bahan_baku') === $nilai)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-1" :messages="$errors->get('jenis_barang')" />
            </div>

            <div>
                <x-input-label for="kategori_id" value="Kategori" />
                <select id="kategori_id" name="kategori_id" required
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                    <option value="">-- pilih kategori --</option>
                    @foreach ($daftarKategori as $kat)
                        <option value="{{ $kat->id }}"
                            @selected((string) old('kategori_id', $barang->kategori_id) === (string) $kat->id)>
                            {{ $kat->kode_kategori }} &mdash; {{ $kat->nama_kategori }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-1" :messages="$errors->get('kategori_id')" />
            </div>

            <div>
                <x-input-label for="satuan" value="Satuan" />
                <x-text-input id="satuan" name="satuan" type="text" class="mt-1 block w-full"
                              maxlength="20" required placeholder="Pcs / Lembar / Kg"
                              value="{{ old('satuan', $barang->satuan ?? 'Pcs') }}" />
                <x-input-error class="mt-1" :messages="$errors->get('satuan')" />
            </div>
        </div>

        <div class="mt-5">
            <x-input-label for="supplier_id" value="Supplier" />
            <select id="supplier_id" name="supplier_id"
                    class="mt-1 block w-full sm:w-2/3 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">-- tanpa supplier --</option>
                @foreach ($daftarSupplier as $sup)
                    <option value="{{ $sup->id }}"
                        @selected((string) old('supplier_id', $barang->supplier_id) === (string) $sup->id)>
                        {{ $sup->kode_supplier }} &mdash; {{ $sup->nama_supplier }}{{ $sup->is_aktif ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">
                Wajib diisi untuk <strong>bahan baku</strong>. Barang setengah jadi dan barang jadi dibuat sendiri
                di pabrik, jadi boleh dikosongkan.
            </p>
            <x-input-error class="mt-1" :messages="$errors->get('supplier_id')" />
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    <div class="pt-5 border-t border-gray-200">
        <h3 class="text-sm font-semibold text-gray-900">Harga</h3>

        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <x-input-label for="harga_beli" value="Harga Beli (Rp)" />
                <x-text-input id="harga_beli" name="harga_beli" type="number" class="mt-1 block w-full"
                              min="0" step="0.01" required
                              value="{{ old('harga_beli', $barang->harga_beli ?? 0) }}" />
                <p class="mt-1 text-xs text-gray-500">Relevan untuk bahan baku.</p>
                <x-input-error class="mt-1" :messages="$errors->get('harga_beli')" />
            </div>

            <div>
                <x-input-label for="harga_jual" value="Harga Jual (Rp)" />
                <x-text-input id="harga_jual" name="harga_jual" type="number" class="mt-1 block w-full"
                              min="0" step="0.01" required
                              value="{{ old('harga_jual', $barang->harga_jual ?? 0) }}" />
                <p class="mt-1 text-xs text-gray-500">Relevan untuk barang jadi.</p>
                <x-input-error class="mt-1" :messages="$errors->get('harga_jual')" />
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    <div class="pt-5 border-t border-gray-200">
        <h3 class="text-sm font-semibold text-gray-900">Persediaan &amp; Perencanaan Stok</h3>
        <p class="mt-1 text-xs text-gray-500">Angka di bagian ini dipakai menghitung safety stock dan waktu tunggu.</p>

        @isset($barang->id)
            <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="flex items-baseline justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Tersedia Saat Ini</p>
                        <p class="mt-0.5 text-2xl font-semibold text-gray-900">
                            {{ number_format($barang->stok_tersedia, 0, ',', '.') }}
                            <span class="text-base font-normal text-gray-500">{{ $barang->satuan }}</span>
                        </p>
                    </div>
                    <p class="text-xs text-gray-500 text-right max-w-xs">
                        Tidak dapat diubah dari sini. Stok hanya berubah lewat
                        <strong>mutasi stok</strong> agar setiap perubahan punya jejak asal-usul.
                    </p>
                </div>
            </div>
        @endisset

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <x-input-label for="stok_minimum" value="Stok Minimum" />
                <x-text-input id="stok_minimum" name="stok_minimum" type="number" class="mt-1 block w-full"
                              min="0" required
                              value="{{ old('stok_minimum', $barang->stok_minimum ?? 0) }}" />
                <p class="mt-1 text-xs text-gray-500">Batas peringatan stok menipis.</p>
                <x-input-error class="mt-1" :messages="$errors->get('stok_minimum')" />
            </div>

            <div>
                <x-input-label for="lead_time_hari" value="Lead Time (hari)" />
                <x-text-input id="lead_time_hari" name="lead_time_hari" type="number" class="mt-1 block w-full"
                              min="0" max="365" required
                              value="{{ old('lead_time_hari', $barang->lead_time_hari ?? 7) }}" />
                <p class="mt-1 text-xs text-gray-500">Lama bahan tiba setelah dipesan (L<sub>beli</sub>).</p>
                <x-input-error class="mt-1" :messages="$errors->get('lead_time_hari')" />
            </div>

            <div>
                <x-input-label for="service_level" value="Service Level (%)" />
                <x-text-input id="service_level" name="service_level" type="number" class="mt-1 block w-full"
                              min="50" max="99.99" step="0.01" required
                              value="{{ old('service_level', $barang->service_level ?? 95) }}" />
                <p class="mt-1 text-xs text-gray-500">Menentukan nilai Z pada safety stock. Umumnya 95%.</p>
                <x-input-error class="mt-1" :messages="$errors->get('service_level')" />
            </div>
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    <div class="pt-5 border-t border-gray-200 space-y-3">
        <h3 class="text-sm font-semibold text-gray-900">Penanda</h3>

        <div>
            <label for="is_diramalkan" class="inline-flex items-center">
                <input type="hidden" name="is_diramalkan" value="0">
                <input id="is_diramalkan" type="checkbox" name="is_diramalkan" value="1"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                       @checked(old('is_diramalkan', $barang->is_diramalkan ?? false))>
                <span class="ms-2 text-sm text-gray-700">Penjualannya diramalkan dengan ARIMA</span>
            </label>
            <p class="mt-1 text-xs text-gray-500">
                Hanya untuk <strong>barang jadi</strong>. Bahan baku dan barang setengah jadi tidak dijual,
                sehingga tidak punya deret penjualan untuk diramalkan.
            </p>
            <x-input-error class="mt-1" :messages="$errors->get('is_diramalkan')" />
        </div>

        <div>
            <label for="is_aktif" class="inline-flex items-center">
                <input type="hidden" name="is_aktif" value="0">
                <input id="is_aktif" type="checkbox" name="is_aktif" value="1"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                       @checked(old('is_aktif', $barang->is_aktif ?? true))>
                <span class="ms-2 text-sm text-gray-700">Barang aktif</span>
            </label>
            <p class="mt-1 text-xs text-gray-500">
                Hapus centang untuk barang yang sudah tidak dipakai, tanpa menghapus riwayat transaksinya.
            </p>
            <x-input-error class="mt-1" :messages="$errors->get('is_aktif')" />
        </div>
    </div>
</div>
