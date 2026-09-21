<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Perbandingan Skenario -- {{ $simulasi->kode_simulasi }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $simulasi->barang->nama_barang }} ({{ $simulasi->periode_awal }} s.d. {{ $simulasi->periode_akhir }})</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('simulasi.show', $simulasi) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Rincian Bulanan &rarr;</a>
                <a href="{{ route('simulasi.cetak', $simulasi) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Cetak PDF</a>
                <a href="{{ route('simulasi.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Riwayat</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl space-y-6">
        <div @class([
            'rounded-md border px-4 py-4 text-sm',
            'border-green-200 bg-green-50 text-green-900' => $simulasi->is_sistem_lebih_baik,
            'border-amber-200 bg-amber-50 text-amber-900' => ! $simulasi->is_sistem_lebih_baik,
        ])>
            <p class="font-semibold uppercase text-xs tracking-wide mb-1">
                {{ $simulasi->is_sistem_lebih_baik ? 'Kesimpulan: Sistem Lebih Baik' : 'Kesimpulan: Sistem Belum Lebih Baik' }}
            </p>
            <p>{{ $simulasi->kesimpulan }}</p>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Ringkasan Berdampingan</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-3 py-2 border-b">Metrik</th>
                            <th class="px-3 py-2 border-b text-right">Perusahaan ({{ str_replace('_', ' ', $simulasi->metode_pembanding) }})</th>
                            <th class="px-3 py-2 border-b text-right">Sistem</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr>
                            <td class="px-3 py-2 border-b">Total Stockout (unit)</td>
                            <td class="px-3 py-2 border-b text-right">{{ number_format($simulasi->pb_total_stockout_unit, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">{{ number_format($simulasi->sis_total_stockout_unit, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 border-b">Bulan Terjadi Stockout</td>
                            <td class="px-3 py-2 border-b text-right">{{ $simulasi->pb_bulan_stockout }}</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">{{ $simulasi->sis_bulan_stockout }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 border-b">Total Overstock (unit)</td>
                            <td class="px-3 py-2 border-b text-right">{{ number_format($simulasi->pb_total_overstock_unit, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">{{ number_format($simulasi->sis_total_overstock_unit, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 border-b">Rata-rata Stok Akhir</td>
                            <td class="px-3 py-2 border-b text-right">{{ number_format($simulasi->pb_rata_stok_akhir, 1) }}</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">{{ number_format($simulasi->sis_rata_stok_akhir, 1) }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 border-b">Service Level Tercapai</td>
                            <td class="px-3 py-2 border-b text-right">{{ number_format($simulasi->pb_service_level_tercapai, 2) }}%</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">{{ number_format($simulasi->sis_service_level_tercapai, 2) }}%</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 border-b">Perputaran Persediaan</td>
                            <td class="px-3 py-2 border-b text-right">{{ number_format($simulasi->pb_perputaran_persediaan, 2) }}</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">{{ number_format($simulasi->sis_perputaran_persediaan, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 border-b">Total Biaya</td>
                            <td class="px-3 py-2 border-b text-right">Rp {{ number_format($simulasi->pb_total_biaya, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 border-b text-right font-semibold">Rp {{ number_format($simulasi->sis_total_biaya, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penurunan Overstock</p>
                <p @class(['mt-1 text-2xl font-semibold', 'text-green-600' => $simulasi->penurunan_overstock_persen >= 0, 'text-red-600' => $simulasi->penurunan_overstock_persen < 0])>
                    {{ number_format($simulasi->penurunan_overstock_persen, 1) }}%
                </p>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penurunan Stockout</p>
                <p @class(['mt-1 text-2xl font-semibold', 'text-green-600' => $simulasi->penurunan_stockout_persen >= 0, 'text-red-600' => $simulasi->penurunan_stockout_persen < 0])>
                    {{ number_format($simulasi->penurunan_stockout_persen, 1) }}%
                </p>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penghematan Biaya</p>
                <p @class(['mt-1 text-2xl font-semibold', 'text-green-600' => $simulasi->penghematan_biaya >= 0, 'text-red-600' => $simulasi->penghematan_biaya < 0])>
                    Rp {{ number_format($simulasi->penghematan_biaya, 0, ',', '.') }}
                </p>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Parameter Simulasi</h3>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500">Stok Awal Simulasi</p>
                    <p class="text-gray-900">{{ number_format($simulasi->stok_awal_simulasi, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Waktu Tunggu Total</p>
                    <p class="text-gray-900">{{ number_format($simulasi->lead_time_total_hari, 1) }} hari</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Nilai Z / Service Level</p>
                    <p class="text-gray-900">{{ number_format($simulasi->nilai_z, 4) }} ({{ number_format($simulasi->service_level, 1) }}%)</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Biaya Simpan / Stockout per Unit</p>
                    <p class="text-gray-900">Rp {{ number_format($simulasi->biaya_simpan_per_unit, 0, ',', '.') }} / Rp {{ number_format($simulasi->biaya_stockout_per_unit, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
