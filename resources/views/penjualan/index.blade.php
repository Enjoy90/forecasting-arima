<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transaksi Penjualan</h2>
                <p class="text-sm text-gray-500 mt-0.5">Sumber data deret waktu yang diramalkan ARIMA.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('penjualan.import.form') }}"
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Import Excel
                </a>
                <a href="{{ route('penjualan.faktur.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Faktur Baru
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

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('penjualan.faktur.index') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="cari" class="block text-xs font-medium text-gray-600 mb-1">No. Faktur</label>
                        <x-text-input id="cari" type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-40 text-sm" placeholder="FJ-202609-0001" />
                    </div>

                    <div>
                        <label for="pelanggan" class="block text-xs font-medium text-gray-600 mb-1">Pelanggan</label>
                        <select id="pelanggan" name="pelanggan" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($pelangganId === 'semua')>Semua</option>
                            @foreach ($daftarPelanggan as $p)
                                <option value="{{ $p->id }}" @selected((string) $pelangganId === (string) $p->id)>{{ $p->nama_pelanggan }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sumber" class="block text-xs font-medium text-gray-600 mb-1">Sumber</label>
                        <select id="sumber" name="sumber" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($sumber === 'semua')>Semua</option>
                            <option value="manual" @selected($sumber === 'manual')>Manual</option>
                            <option value="import" @selected($sumber === 'import')>Import</option>
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

                    @if ($cari !== '' || $pelangganId !== 'semua' || $sumber !== 'semua' || $dari || $sampai)
                        <a href="{{ route('penjualan.faktur.index') }}" class="text-sm text-gray-500 hover:text-gray-700 pb-2">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-40">No. Faktur</th>
                            <th class="px-4 py-3 w-28">Tanggal</th>
                            <th class="px-4 py-3">Pembeli</th>
                            <th class="px-4 py-3 w-20 text-center">Baris</th>
                            <th class="px-4 py-3 w-36 text-right">Total</th>
                            <th class="px-4 py-3 w-24 text-center">Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($penjualan as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('penjualan.faktur.show', $item) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-800">{{ $item->no_faktur }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->tanggal_penjualan?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ $item->nama_pembeli }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->detail_count }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-gray-100 text-gray-700' => $item->sumber_data === 'manual',
                                        'bg-sky-100 text-sky-800' => $item->sumber_data === 'import',
                                    ])>{{ ucfirst($item->sumber_data) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada transaksi penjualan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($penjualan->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">{{ $penjualan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
