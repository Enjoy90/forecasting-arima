<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $simulasi->kode_simulasi }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $simulasi->barang->nama_barang }} -- Rincian Bulanan ({{ $simulasi->periode_awal }} s.d. {{ $simulasi->periode_akhir }})
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('simulasi.perbandingan', $simulasi) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Lihat Perbandingan &rarr;</a>
                <a href="{{ route('simulasi.cetak', $simulasi) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Cetak PDF</a>
                <a href="{{ route('simulasi.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Riwayat</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl space-y-6">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        @foreach ([['Perusahaan (Kebijakan Lama)', $detailPerusahaan], ['Sistem (Rekomendasi ARIMA)', $detailSistem]] as [$judul, $baris])
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">{{ $judul }}</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-600">
                                <th class="px-3 py-2 border-b">Periode</th>
                                <th class="px-3 py-2 border-b text-right">Stok Awal</th>
                                <th class="px-3 py-2 border-b text-right">Permintaan</th>
                                <th class="px-3 py-2 border-b text-right">Rencana Produksi</th>
                                <th class="px-3 py-2 border-b text-right">Barang Masuk</th>
                                <th class="px-3 py-2 border-b text-right">Terpenuhi</th>
                                <th class="px-3 py-2 border-b text-right">Stockout</th>
                                <th class="px-3 py-2 border-b text-right">Stok Akhir</th>
                                <th class="px-3 py-2 border-b text-right">Overstock</th>
                                <th class="px-3 py-2 border-b text-right">Biaya</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach ($baris as $b)
                                <tr @class(['bg-red-50' => $b->is_stockout])>
                                    <td class="px-3 py-2 border-b">{{ $b->periode }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->stok_awal, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->permintaan_aktual, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->rencana_produksi, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->barang_masuk, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->terpenuhi, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right @if($b->stockout_unit > 0) text-red-600 font-semibold @endif">{{ number_format($b->stockout_unit, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->stok_akhir, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right @if($b->overstock_unit > 0) text-amber-600 font-semibold @endif">{{ number_format($b->overstock_unit, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ number_format($b->total_biaya, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
