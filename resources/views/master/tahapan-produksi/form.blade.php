{{--
    Isi form tahapan produksi, dipakai bersama oleh create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $tahapan       instance TahapanProduksi (kosong saat tambah)
      $kodeUsulan    nilai awal kode saat tambah (opsional)
      $urutanUsulan  nilai awal urutan saat tambah (opsional)
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

<div class="mb-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    Angka pada halaman ini dipakai menghitung <strong>waktu tunggu produksi</strong> dan
    ikut menentukan hasil rencana stok. Pastikan sudah dikonfirmasi ke CV. Pande Sejahtera
    sebelum dipakai di laporan akhir.
</div>

<div class="space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <x-input-label for="kode_tahapan" value="Kode Tahapan" />
            <x-text-input id="kode_tahapan" name="kode_tahapan" type="text" class="mt-1 block w-full"
                          maxlength="20" required autofocus
                          value="{{ old('kode_tahapan', $tahapan->kode_tahapan ?? ($kodeUsulan ?? '')) }}" />
            <p class="mt-1 text-xs text-gray-500">Contoh: TP-01</p>
            <x-input-error class="mt-1" :messages="$errors->get('kode_tahapan')" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="nama_tahapan" value="Nama Tahapan" />
            <x-text-input id="nama_tahapan" name="nama_tahapan" type="text" class="mt-1 block w-full"
                          maxlength="100" required placeholder="Produksi Kepala"
                          value="{{ old('nama_tahapan', $tahapan->nama_tahapan) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('nama_tahapan')" />
        </div>
    </div>

    <div>
        <x-input-label for="urutan" value="Urutan dalam Rantai Produksi" />
        <x-text-input id="urutan" name="urutan" type="number" class="mt-1 block w-full sm:w-32"
                      min="1" max="255" required
                      value="{{ old('urutan', $tahapan->urutan ?? ($urutanUsulan ?? 1)) }}" />
        <p class="mt-1 text-xs text-gray-500">
            Menentukan susunan tahapan: 1 dikerjakan lebih dulu. Tidak boleh sama dengan tahapan lain.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('urutan')" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="waktu_proses_hari" value="Waktu Proses (hari)" />
            <x-text-input id="waktu_proses_hari" name="waktu_proses_hari" type="number" class="mt-1 block w-full sm:w-40"
                          min="0" max="365" step="0.01" required
                          value="{{ old('waktu_proses_hari', $tahapan->waktu_proses_hari ?? 1) }}" />
            <p class="mt-1 text-xs text-gray-500">
                Lama pengerjaan satu angkatan pada tahapan ini. Boleh pecahan, misalnya 0,5 hari.
            </p>
            <x-input-error class="mt-1" :messages="$errors->get('waktu_proses_hari')" />
        </div>

        <div>
            <x-input-label for="kapasitas_per_hari" value="Kapasitas per Hari (unit)" />
            <x-text-input id="kapasitas_per_hari" name="kapasitas_per_hari" type="number" class="mt-1 block w-full sm:w-40"
                          min="0" step="0.01" required
                          value="{{ old('kapasitas_per_hari', $tahapan->kapasitas_per_hari ?? 0) }}" />
            <p class="mt-1 text-xs text-gray-500">
                Isi <strong>0</strong> bila kapasitas tidak dibatasi. Bila diisi, target yang melebihi
                kapasitas akan menambah hari pengerjaan.
            </p>
            <x-input-error class="mt-1" :messages="$errors->get('kapasitas_per_hari')" />
        </div>
    </div>

    <div>
        <x-input-label for="deskripsi" value="Deskripsi" />
        <textarea id="deskripsi" name="deskripsi" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Ringkasan pekerjaan pada tahapan ini (opsional)">{{ old('deskripsi', $tahapan->deskripsi) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('deskripsi')" />
    </div>

    <div class="pt-1">
        <label for="is_aktif" class="inline-flex items-center">
            <input type="hidden" name="is_aktif" value="0">
            <input id="is_aktif" type="checkbox" name="is_aktif" value="1"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   @checked(old('is_aktif', $tahapan->is_aktif ?? true))>
            <span class="ms-2 text-sm text-gray-700">Tahapan aktif</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">
            Tahapan nonaktif tidak ikut dihitung dalam total waktu produksi.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('is_aktif')" />
    </div>
</div>
