{{--
    Isi form supplier, dipakai bersama oleh create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $supplier    instance Supplier (kosong saat tambah)
      $kodeUsulan  nilai awal kode saat tambah (opsional)
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

<div class="space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="kode_supplier" value="Kode Supplier" />
            <x-text-input id="kode_supplier" name="kode_supplier" type="text" class="mt-1 block w-full"
                          maxlength="20" required autofocus
                          value="{{ old('kode_supplier', $supplier->kode_supplier ?? ($kodeUsulan ?? '')) }}" />
            <p class="mt-1 text-xs text-gray-500">Contoh: SUP-01. Harus unik, otomatis disimpan huruf kapital.</p>
            <x-input-error class="mt-1" :messages="$errors->get('kode_supplier')" />
        </div>

        <div>
            <x-input-label for="nama_supplier" value="Nama Supplier" />
            <x-text-input id="nama_supplier" name="nama_supplier" type="text" class="mt-1 block w-full"
                          maxlength="150" required
                          value="{{ old('nama_supplier', $supplier->nama_supplier) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('nama_supplier')" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="telepon" value="Telepon" />
            <x-text-input id="telepon" name="telepon" type="text" class="mt-1 block w-full"
                          maxlength="25" placeholder="031-8812340"
                          value="{{ old('telepon', $supplier->telepon) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('telepon')" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                          maxlength="100" placeholder="opsional"
                          value="{{ old('email', $supplier->email) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('email')" />
        </div>
    </div>

    <div>
        <x-input-label for="alamat" value="Alamat" />
        <textarea id="alamat" name="alamat" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Alamat lengkap supplier (opsional)">{{ old('alamat', $supplier->alamat) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('alamat')" />
    </div>

    <div>
        <x-input-label for="lead_time_default" value="Lead Time Default (hari)" />
        <x-text-input id="lead_time_default" name="lead_time_default" type="number" class="mt-1 block w-full sm:w-40"
                      min="0" max="365" required
                      value="{{ old('lead_time_default', $supplier->lead_time_default ?? 7) }}" />
        <p class="mt-1 text-xs text-gray-500">
            Perkiraan lama bahan tiba setelah dipesan. Dipakai sebagai nilai cadangan bila barang belum punya lead time sendiri.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('lead_time_default')" />
    </div>

    <div class="pt-1">
        <label for="is_aktif" class="inline-flex items-center">
            <input type="hidden" name="is_aktif" value="0">
            <input id="is_aktif" type="checkbox" name="is_aktif" value="1"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   @checked(old('is_aktif', $supplier->is_aktif ?? true))>
            <span class="ms-2 text-sm text-gray-700">Supplier aktif</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">
            Hapus centang untuk menonaktifkan supplier yang sudah tidak dipakai, tanpa menghapus riwayat pembeliannya.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('is_aktif')" />
    </div>
</div>
