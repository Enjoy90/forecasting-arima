{{--
    Isi form pengguna, dipakai bersama create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $pengguna     instance User (kosong saat tambah)
      $daftarRole   pilihan role beserta penjelasannya
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
            <x-input-label for="name" value="Nama" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          maxlength="255" required autofocus value="{{ old('name', $pengguna->name) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                          maxlength="255" required value="{{ old('email', $pengguna->email) }}" />
            <p class="mt-1 text-xs text-gray-500">Dipakai untuk masuk ke sistem.</p>
            <x-input-error class="mt-1" :messages="$errors->get('email')" />
        </div>
    </div>

    <div>
        <x-input-label for="role" value="Role" />
        <select id="role" name="role" required
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            @foreach ($daftarRole as $nilai => $penjelasan)
                <option value="{{ $nilai }}" @selected(old('role', $pengguna->role) === $nilai)>{{ $penjelasan }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-1" :messages="$errors->get('role')" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-1 border-t border-gray-100">
        <div class="sm:col-span-2 pt-4">
            <h3 class="text-sm font-semibold text-gray-900">Kata Sandi</h3>
            @isset($pengguna->id)
                <p class="mt-1 text-xs text-gray-500">
                    Kosongkan bila tidak ingin mengubah kata sandi pengguna ini.
                </p>
            @endisset
        </div>

        <div>
            <x-input-label for="password" value="Kata Sandi" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                          autocomplete="new-password" @required(! isset($pengguna->id)) />
            <x-input-error class="mt-1" :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Ulangi Kata Sandi" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                          class="mt-1 block w-full" autocomplete="new-password"
                          @required(! isset($pengguna->id)) />
        </div>
    </div>

    <div class="pt-1">
        <label for="is_aktif" class="inline-flex items-center">
            <input type="hidden" name="is_aktif" value="0">
            <input id="is_aktif" type="checkbox" name="is_aktif" value="1"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   @checked(old('is_aktif', $pengguna->is_aktif ?? true))>
            <span class="ms-2 text-sm text-gray-700">Akun aktif</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">
            Akun nonaktif ditolak masuk oleh sistem, tetapi seluruh jejak aktivitasnya tetap tersimpan.
        </p>
        <x-input-error class="mt-1" :messages="$errors->get('is_aktif')" />
    </div>
</div>
