<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Simulasi &amp; Pengujian</h2>
                <p class="text-sm text-gray-500 mt-0.5">Uji rencana stok dengan data masa lalu (backtesting) -- tolak ukur utama keberhasilan sistem.</p>
            </div>
            @if (auth()->user()?->role === 'pimpinan')
                <a href="{{ route('simulasi.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Jalankan Simulasi Baru
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-7xl space-y-4">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            @if ($riwayat->isEmpty())
                <p class="p-6 text-sm text-gray-500">Belum ada simulasi yang dijalankan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-600">
                                <th class="px-4 py-2 border-b border-gray-200">Kode</th>
                                <th class="px-4 py-2 border-b border-gray-200">Barang</th>
                                <th class="px-4 py-2 border-b border-gray-200">Periode</th>
                                <th class="px-4 py-2 border-b border-gray-200">Metode Pembanding</th>
                                <th class="px-4 py-2 border-b border-gray-200 text-right">Stockout PB &rarr; Sistem</th>
                                <th class="px-4 py-2 border-b border-gray-200 text-right">Overstock PB &rarr; Sistem</th>
                                <th class="px-4 py-2 border-b border-gray-200">Kesimpulan</th>
                                <th class="px-4 py-2 border-b border-gray-200"></th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach ($riwayat as $s)
                                <tr>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $s->kode_simulasi }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $s->barang->nama_barang }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $s->periode_awal }} s.d. {{ $s->periode_akhir }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ str_replace('_', ' ', $s->metode_pembanding) }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right">{{ number_format($s->pb_total_stockout_unit, 0) }} &rarr; {{ number_format($s->sis_total_stockout_unit, 0) }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right">{{ number_format($s->pb_total_overstock_unit, 0) }} &rarr; {{ number_format($s->sis_total_overstock_unit, 0) }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">
                                        <span @class(['text-xs font-semibold uppercase', 'text-green-600' => $s->is_sistem_lebih_baik, 'text-red-600' => ! $s->is_sistem_lebih_baik])>
                                            {{ $s->is_sistem_lebih_baik ? 'Sistem lebih baik' : 'Belum lebih baik' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right whitespace-nowrap">
                                        <a href="{{ route('simulasi.perbandingan', $s) }}" class="text-indigo-600 hover:text-indigo-800">Perbandingan</a>
                                        <span class="text-gray-300">|</span>
                                        <a href="{{ route('simulasi.show', $s) }}" class="text-indigo-600 hover:text-indigo-800">Rincian</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $riwayat->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
