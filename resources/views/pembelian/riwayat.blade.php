<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Riwayat Pembelian Bahan</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Dilihat per baris barang, bukan per nota — untuk menelusuri harga beli dan waktu tunggu tiap bahan.
            </p>
        </div>
    </x-slot>

    <div class="w-full">
        <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            Hanya menampilkan order yang <strong>sudah diterima</strong>. Order yang masih dipesan atau dibatalkan
            belum mewujud menjadi pengadaan bahan.
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('pembelian.riwayat') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="barang" class="block text-xs font-medium text-gray-600 mb-1">Barang</label>
                        <select id="barang" name="barang" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($barangId === 'semua')>Semua barang</option>
                            @foreach ($daftarBarang as $b)
                                <option value="{{ $b->id }}" @selected((string) $barangId === (string) $b->id)>
                                    {{ $b->kode_barang }} &mdash; {{ $b->nama_barang }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="supplier" class="block text-xs font-medium text-gray-600 mb-1">Supplier</label>
                        <select id="supplier" name="supplier" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($supplierId === 'semua')>Semua supplier</option>
                            @foreach ($daftarSupplier as $s)
                                <option value="{{ $s->id }}" @selected((string) $supplierId === (string) $s->id)>{{ $s->nama_supplier }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="dari" class="block text-xs font-medium text-gray-600 mb-1">Diterima dari</label>
                        <x-text-input id="dari" type="date" name="dari" value="{{ $dari }}" class="text-sm" />
                    </div>

                    <div>
                        <label for="sampai" class="block text-xs font-medium text-gray-600 mb-1">Sampai</label>
                        <x-text-input id="sampai" type="date" name="sampai" value="{{ $sampai }}" class="text-sm" />
                    </div>

                    <x-primary-button>Saring</x-primary-button>

                    @if ($barangId !== 'semua' || $supplierId !== 'semua' || $dari || $sampai)
                        <a href="{{ route('pembelian.riwayat') }}" class="text-sm text-gray-500 hover:text-gray-700 pb-2">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-28">Diterima</th>
                            <th class="px-4 py-3 w-36">No. Pembelian</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3 w-24 text-right">Jumlah</th>
                            <th class="px-4 py-3 w-32 text-right">Harga Satuan</th>
                            <th class="px-4 py-3 w-32 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($baris as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $item->pembelian?->tanggal_terima?->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('pembelian.order.show', $item->pembelian_id) }}"
                                       class="text-indigo-600 hover:text-indigo-800">{{ $item->pembelian?->no_pembelian }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->barang?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->barang?->kode_barang }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->pembelian?->supplier?->nama_supplier ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">
                                    {{ number_format($item->jumlah, 0, ',', '.') }}
                                    <span class="text-xs text-gray-400">{{ $item->barang?->satuan }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">Rp {{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada pembelian bahan yang diterima.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($baris->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">{{ $baris->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
