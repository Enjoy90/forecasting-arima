<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transaksi Pembelian</h2>
                <p class="text-sm text-gray-500 mt-0.5">Order bahan ke supplier beserta status penerimaannya.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('pembelian.import.form') }}"
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Import Excel
                </a>
                <a href="{{ route('pembelian.order.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Order Baru
                </a>
            </div>
        </div>
    </x-slot>

    <div class="w-full">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif
        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif

        @if ($jumlahDipesan > 0 && $status !== 'dipesan')
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex items-center justify-between gap-4">
                <span><strong>{{ $jumlahDipesan }} order</strong> masih menunggu penerimaan barang.</span>
                <a href="{{ route('pembelian.order.index', ['status' => 'dipesan']) }}"
                   class="shrink-0 font-medium underline hover:no-underline">Lihat</a>
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('pembelian.order.index') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="cari" class="block text-xs font-medium text-gray-600 mb-1">No. Pembelian</label>
                        <x-text-input id="cari" type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-44 text-sm" placeholder="PB-202609-0001" />
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select id="status" name="status" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($status === 'semua')>Semua</option>
                            @foreach ($daftarStatus as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="supplier" class="block text-xs font-medium text-gray-600 mb-1">Supplier</label>
                        <select id="supplier" name="supplier" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($supplierId === 'semua')>Semua</option>
                            @foreach ($daftarSupplier as $sup)
                                <option value="{{ $sup->id }}" @selected((string) $supplierId === (string) $sup->id)>{{ $sup->nama_supplier }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="dari" class="block text-xs font-medium text-gray-600 mb-1">Dari</label>
                        <x-text-input id="dari" type="date" name="dari" value="{{ $dari }}" class="text-sm" />
                    </div>

                    <div>
                        <label for="sampai" class="block text-xs font-medium text-gray-600 mb-1">Sampai</label>
                        <x-text-input id="sampai" type="date" name="sampai" value="{{ $sampai }}" class="text-sm" />
                    </div>

                    <x-primary-button>Saring</x-primary-button>

                    @if ($cari !== '' || $status !== 'semua' || $supplierId !== 'semua' || $dari || $sampai)
                        <a href="{{ route('pembelian.order.index') }}" class="text-sm text-gray-500 hover:text-gray-700 pb-2">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-40">No. Pembelian</th>
                            <th class="px-4 py-3 w-28">Tanggal</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3 w-20 text-center">Baris</th>
                            <th class="px-4 py-3 w-36 text-right">Total</th>
                            <th class="px-4 py-3 w-28 text-center">Status</th>
                            <th class="px-4 py-3 w-28 text-center">Diterima</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($pembelian as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('pembelian.order.show', $item) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-800">{{ $item->no_pembelian }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->tanggal_pembelian?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ $item->supplier?->nama_supplier ?? '-' }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->detail_count }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-amber-100 text-amber-800' => $item->status === 'dipesan',
                                        'bg-green-100 text-green-800' => $item->status === 'diterima',
                                        'bg-gray-100 text-gray-600' => $item->status === 'batal',
                                    ])>{{ $daftarStatus[$item->status] ?? $item->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $item->tanggal_terima?->format('d/m/Y') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada order pembelian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pembelian->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">{{ $pembelian->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
