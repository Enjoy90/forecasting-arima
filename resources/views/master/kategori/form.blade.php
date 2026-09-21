{{--
    Isi form kategori, dipakai bersama oleh create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $kategori    instance Kategori (kosong saat tambah)
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
    <div>
        <x-input-label for="kode_kategori" value="Kode Kategori" />
        <x-text-input id="kode_kategori" name="kode_kategori" type="text" class="mt-1 block w-full sm:w-56"
                      maxlength="20" required autofocus
                      value="{{ old('kode_kategori', $kategori->kode_kategori ?? ($kodeUsulan ?? '')) }}" />
        <p class="mt-1 text-xs text-gray-500">Contoh: KTG-01. Kode harus unik dan otomatis disimpan huruf kapital.</p>
        <x-input-error class="mt-1" :messages="$errors->get('kode_kategori')" />
    </div>

    <div>
        <x-input-label for="nama_kategori" value="Nama Kategori" />
        <x-text-input id="nama_kategori" name="nama_kategori" type="text" class="mt-1 block w-full sm:w-96"
                      maxlength="100" required
                      value="{{ old('nama_kategori', $kategori->nama_kategori) }}" />
        <x-input-error class="mt-1" :messages="$errors->get('nama_kategori')" />
    </div>

    <div>
        <x-input-label for="keterangan" value="Keterangan" />
        <textarea id="keterangan" name="keterangan" rows="3" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Penjelasan singkat isi kategori (opsional)">{{ old('keterangan', $kategori->keterangan) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('keterangan')" />
    </div>
</div>
