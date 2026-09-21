<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Supplier</h2>
                <p class="text-sm text-gray-500 mt-0.5">Pemasok bahan baku beserta lead time pengirimannya.</p>
            </div>
            <a href="{{ route('master.supplier.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Tambah Supplier
            </a>
        </div>
    </x-slot>

    <div class="max-w-6xl">
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
                <form method="GET" action="{{ route('master.supplier.index') }}" class="flex flex-wrap items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-72 text-sm"
                                  placeholder="Cari kode, nama, atau telepon" />

                    <select name="status"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($status === 'semua')>Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Hanya aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Hanya nonaktif</option>
                    </select>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $status !== 'semua')
                        <a href="{{ route('master.supplier.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-12">No</th>
                            <th class="px-4 py-3 w-28">Kode</th>
                            <th class="px-4 py-3">Nama Supplier</th>
                            <th class="px-4 py-3">Kontak</th>
                            <th class="px-4 py-3 w-24 text-center">Lead Time</th>
                            <th class="px-4 py-3 w-28 text-center">Dipakai</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                            <th class="px-4 py-3 w-36 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($supplier as $index => $item)
                            @php $terpakai = $item->barang_count + $item->pembelian_count; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500">{{ $supplier->firstItem() + $index }}</td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->kode_supplier }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->nama_supplier }}</div>
                                    @if ($item->alamat)
                                        <div class="text-xs text-gray-500 mt-0.5">{{ Str::limit($item->alamat, 60) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <div>{{ $item->telepon ?: '-' }}</div>
                                    @if ($item->email)
                                        <div class="text-xs text-gray-500">{{ $item->email }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->lead_time_default }} hari</td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    @if ($terpakai === 0)
                                        <span class="text-gray-400">-</span>
                                    @else
                                        <span title="{{ $item->barang_count }} barang, {{ $item->pembelian_count }} pembelian">
                                            {{ $item->barang_count }} brg / {{ $item->pembelian_count }} beli
                                        </span>
                                    @endif
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
                                        <a href="{{ route('master.supplier.edit', $item) }}"
                                           class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                        <form method="POST" action="{{ route('master.supplier.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus supplier {{ $item->nama_supplier }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    @disabled($terpakai > 0)
                                                    @class([
                                                        'text-red-600 hover:text-red-800' => $terpakai === 0,
                                                        'text-gray-300 cursor-not-allowed' => $terpakai > 0,
                                                    ])
                                                    title="{{ $terpakai > 0 ? 'Masih dipakai barang / pembelian — nonaktifkan saja lewat Ubah' : 'Hapus supplier' }}">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    {{ $cari !== '' || $status !== 'semua' ? 'Supplier tidak ditemukan.' : 'Belum ada data supplier.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($supplier->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $supplier->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
