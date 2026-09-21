<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Kategori</h2>
                <p class="text-sm text-gray-500 mt-0.5">Pengelompokan barang: bahan baku, setengah jadi, dan barang jadi.</p>
            </div>
            <a href="{{ route('master.kategori.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Tambah Kategori
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl">
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
                <form method="GET" action="{{ route('master.kategori.index') }}" class="flex items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-72 text-sm"
                                  placeholder="Cari kode atau nama kategori" />
                    <x-primary-button>Cari</x-primary-button>
                    @if ($cari !== '')
                        <a href="{{ route('master.kategori.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-12">No</th>
                            <th class="px-4 py-3 w-32">Kode</th>
                            <th class="px-4 py-3">Nama Kategori</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3 w-28 text-center">Jumlah Barang</th>
                            <th class="px-4 py-3 w-40 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($kategori as $index => $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500">{{ $kategori->firstItem() + $index }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->kode_kategori }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ $item->nama_kategori }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->keterangan ?: '-' }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->barang_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('master.kategori.edit', $item) }}"
                                           class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                        <form method="POST" action="{{ route('master.kategori.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus kategori {{ $item->nama_kategori }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    @disabled($item->barang_count > 0)
                                                    @class([
                                                        'text-red-600 hover:text-red-800' => $item->barang_count === 0,
                                                        'text-gray-300 cursor-not-allowed' => $item->barang_count > 0,
                                                    ])
                                                    title="{{ $item->barang_count > 0 ? 'Masih dipakai data barang' : 'Hapus kategori' }}">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    {{ $cari !== '' ? 'Kategori tidak ditemukan.' : 'Belum ada data kategori.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($kategori->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $kategori->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
