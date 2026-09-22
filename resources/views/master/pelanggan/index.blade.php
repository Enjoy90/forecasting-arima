<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Pelanggan</h2>
                <p class="text-sm text-gray-500 mt-0.5">Pembeli sekop: toko, distributor, perorangan, dan instansi.</p>
            </div>
            <a href="{{ route('master.pelanggan.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Tambah Pelanggan
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('gagal') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('master.pelanggan.index') }}" class="flex flex-wrap items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-64 text-sm"
                                  placeholder="Cari kode, nama, kota, telepon" />

                    <select name="jenis"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($jenis === 'semua')>Semua jenis</option>
                        @foreach ($daftarJenis as $nilai => $label)
                            <option value="{{ $nilai }}" @selected($jenis === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="status"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($status === 'semua')>Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Hanya aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Hanya nonaktif</option>
                    </select>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $jenis !== 'semua' || $status !== 'semua')
                        <a href="{{ route('master.pelanggan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-12">No</th>
                            <th class="px-4 py-3 w-28">Kode</th>
                            <th class="px-4 py-3">Nama Pelanggan</th>
                            <th class="px-4 py-3 w-28">Jenis</th>
                            <th class="px-4 py-3">Kota / Kontak</th>
                            <th class="px-4 py-3 w-24 text-center">Transaksi</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                            <th class="px-4 py-3 w-36 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pelanggan as $index => $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500">{{ $pelanggan->firstItem() + $index }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->kode_pelanggan }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->nama_pelanggan }}</div>
                                    @if ($item->alamat)
                                        <div class="text-xs text-gray-500 mt-0.5">{{ Str::limit($item->alamat, 60) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                        {{ $daftarJenis[$item->jenis] ?? $item->jenis }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <div>{{ $item->kota ?: '-' }}</div>
                                    @if ($item->telepon)
                                        <div class="text-xs text-gray-500">{{ $item->telepon }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $item->penjualan_count > 0 ? $item->penjualan_count : '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800' => $item->is_aktif,
                                        'bg-gray-100 text-gray-600' => ! $item->is_aktif,
                                    ])>
                                        {{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('master.pelanggan.edit', $item) }}"
                                           class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                        <form method="POST" action="{{ route('master.pelanggan.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus pelanggan {{ $item->nama_pelanggan }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    @disabled($item->penjualan_count > 0)
                                                    @class([
                                                        'text-red-600 hover:text-red-800' => $item->penjualan_count === 0,
                                                        'text-gray-300 cursor-not-allowed' => $item->penjualan_count > 0,
                                                    ])
                                                    title="{{ $item->penjualan_count > 0 ? 'Masih punya transaksi penjualan — nonaktifkan saja lewat Ubah' : 'Hapus pelanggan' }}">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    {{ $cari !== '' || $jenis !== 'semua' || $status !== 'semua' ? 'Pelanggan tidak ditemukan.' : 'Belum ada data pelanggan.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pelanggan->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $pelanggan->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
