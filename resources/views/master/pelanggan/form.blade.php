{{--
    Isi form pelanggan, dipakai bersama oleh create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $pelanggan    instance Pelanggan (kosong saat tambah)
      $daftarJenis  pilihan enum jenis pelanggan
      $kodeUsulan   nilai awal kode saat tambah (opsional)
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
            <x-input-label for="kode_pelanggan" value="Kode Pelanggan" />
            <x-text-input id="kode_pelanggan" name="kode_pelanggan" type="text" class="mt-1 block w-full"
                          maxlength="20" required autofocus
                          value="{{ old('kode_pelanggan', $pelanggan->kode_pelanggan ?? ($kodeUsulan ?? '')) }}" />
            <p class="mt-1 text-xs text-gray-500">Contoh: PLG-01. Harus unik, otomatis disimpan huruf kapital.</p>
            <x-input-error class="mt-1" :messages="$errors->get('kode_pelanggan')" />
        </div>

        <div>
            <x-input-label for="nama_pelanggan" value="Nama Pelanggan" />
            <x-text-input id="nama_pelanggan" name="nama_pelanggan" type="text" class="mt-1 block w-full"
                          maxlength="150" required
                          value="{{ old('nama_pelanggan', $pelanggan->nama_pelanggan) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('nama_pelanggan')" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="jenis" value="Jenis Pelanggan" />
            <select id="jenis" name="jenis" required
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @foreach ($daftarJenis as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(old('jenis', $pelanggan->jenis ?? 'toko') === $nilai)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-1" :messages="$errors->get('jenis')" />
        </div>

        <div>
            <x-input-label for="kota" value="Kota" />
            <x-text-input id="kota" name="kota" type="text" class="mt-1 block w-full"
                          maxlength="100" placeholder="Mojokerto"
                          value="{{ old('kota', $pelanggan->kota) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('kota')" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="telepon" value="Telepon" />
            <x-text-input id="telepon" name="telepon" type="text" class="mt-1 block w-full"
                          maxlength="25" placeholder="0321-445566"
                          value="{{ old('telepon', $pelanggan->telepon) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('telepon')" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                          maxlength="100" placeholder="opsional"
                          value="{{ old('email', $pelanggan->email) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('email')" />
        </div>
    </div>

    <div>
        <x-input-label for="alamat" value="Alamat" />
        <textarea id="alamat" name="alamat" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Alamat lengkap pelanggan (opsional)">{{ old('alamat', $pelanggan->alamat) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('alamat')" />
    </div>

    <div class="pt-1">
        <label for="is_aktif" class="inline-flex items-center">
            <input type="hidden" name="is_aktif" value="0">
            <input id="is_aktif" type="checkbox" name="is_aktif" value="1"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   @checked(old('is_aktif', $pelanggan->is_aktif ?? true))>
            <span class="ms-2 text-sm text-gray-700">Pelanggan aktif</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">
            Hapus centang untuk pelanggan yang sudah tidak berlangganan, tanpa menghapus riwayat penjualannya.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('is_aktif')" />
    </div>
</div>
