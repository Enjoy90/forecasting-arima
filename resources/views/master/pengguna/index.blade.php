<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengguna</h2>
                <p class="text-sm text-gray-500 mt-0.5">Akun dan hak akses staf CV. Pande Sejahtera.</p>
            </div>
            <a href="{{ route('master.pengguna.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Tambah Pengguna
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif
        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif

        @if ($jumlahAdminAktif <= 1)
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Hanya ada <strong>{{ $jumlahAdminAktif }} admin aktif</strong>. Sebaiknya angkat satu admin
                cadangan, supaya pengelolaan sistem tidak berhenti bila akun ini bermasalah.
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('master.pengguna.index') }}" class="flex flex-wrap items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-56 text-sm"
                                  placeholder="Cari nama atau email" />

                    <select name="role" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($role === 'semua')>Semua role</option>
                        @foreach ($daftarRole as $nilai => $penjelasan)
                            <option value="{{ $nilai }}" @selected($role === $nilai)>{{ ucfirst($nilai) }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($status === 'semua')>Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Hanya aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Hanya nonaktif</option>
                    </select>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $role !== 'semua' || $status !== 'semua')
                        <a href="{{ route('master.pengguna.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3 w-32">Role</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                            <th class="px-4 py-3 w-32 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pengguna as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="text-gray-900">{{ $item->name }}</span>
                                    @if ($item->id === auth()->id())
                                        <span class="ms-1 text-xs text-gray-400">(Anda)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->email }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-purple-100 text-purple-800' => $item->role === 'admin',
                                        'bg-sky-100 text-sky-800' => $item->role === 'produksi',
                                        'bg-amber-100 text-amber-800' => $item->role === 'gudang',
                                        'bg-emerald-100 text-emerald-800' => $item->role === 'pimpinan',
                                    ])>{{ ucfirst($item->role) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800' => $item->is_aktif,
                                        'bg-gray-100 text-gray-600' => ! $item->is_aktif,
                                    ])>{{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('master.pengguna.edit', $item) }}" class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                        <form method="POST" action="{{ route('master.pengguna.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus pengguna {{ $item->name }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" @disabled($item->id === auth()->id())
                                                    @class([
                                                        'text-red-600 hover:text-red-800' => $item->id !== auth()->id(),
                                                        'text-gray-300 cursor-not-allowed' => $item->id === auth()->id(),
                                                    ])
                                                    title="{{ $item->id === auth()->id() ? 'Tidak dapat menghapus akun sendiri' : 'Hapus pengguna' }}">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Pengguna tidak ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pengguna->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">{{ $pengguna->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
