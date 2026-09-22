<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stok Opname</h2>
                <p class="text-sm text-gray-500 mt-0.5">Riwayat penyesuaian stok terhadap hasil hitung fisik gudang.</p>
            </div>
            <a href="{{ route('persediaan.opname.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Opname Baru
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        @if (session('info'))
            <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                {{ session('info') }}
            </div>
        @endif

        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('gagal') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-28">Tanggal</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3 w-28 text-right">Catatan</th>
                            <th class="px-4 py-3 w-28 text-right">Fisik</th>
                            <th class="px-4 py-3 w-28 text-right">Selisih</th>
                            <th class="px-4 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($opname as $item)
                            @php $selisih = (float) $item->jumlah; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $item->tanggal?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->barang?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->barang?->kode_barang }}</div>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">
                                    {{ number_format((float) $item->stok_awal, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900">
                                    {{ number_format((float) $item->stok_akhir, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                    <span @class(['text-green-700' => $selisih > 0, 'text-red-700' => $selisih < 0])>
                                        {{ $selisih > 0 ? '+' : '' }}{{ number_format($selisih, 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $item->barang?->satuan }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <div>{{ $item->keterangan ?: '-' }}</div>
                                    @if ($item->user)
                                        <div class="text-xs text-gray-400 mt-0.5">oleh {{ $item->user->name }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Belum pernah ada stok opname.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($opname->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $opname->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
