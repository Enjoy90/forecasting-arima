<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Proses Forecasting</h2>
            <p class="text-sm text-gray-500 mt-0.5">Jalankan pipeline Box-Jenkins (identifikasi, estimasi, diagnostic checking, peramalan).</p>
        </div>
    </x-slot>

    <div class="max-w-7xl space-y-4">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        @if (session('gagal'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('gagal') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if (auth()->user()?->role === 'pimpinan')
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">Jalankan Peramalan Baru</h3>

                @if ($daftarBarang->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">Belum ada barang jadi yang ditandai "diramalkan".</p>
                @else
                    <form method="POST" action="{{ route('peramalan.forecasting.proses') }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <x-input-label for="barang_id" value="Barang Jadi" />
                            <select id="barang_id" name="barang_id" required
                                    class="mt-1 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($daftarBarang as $b)
                                    <option value="{{ $b->id }}" @selected(old('barang_id') == $b->id)>
                                        {{ $b->kode_barang }} - {{ $b->nama_barang }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="horizon" value="Horizon (bulan)" />
                            <x-text-input id="horizon" name="horizon" type="number" min="1" max="12"
                                          value="{{ old('horizon', 6) }}" class="mt-1 w-24 text-sm" required />
                        </div>

                        <x-primary-button>Jalankan Peramalan</x-primary-button>
                    </form>
                    <p class="mt-2 text-xs text-gray-500">
                        Data historis barang harus sudah dibangun lebih dulu di menu Data Historis, minimal 24 periode.
                    </p>
                @endif
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Riwayat Peramalan</h3>
            </div>

            @if ($riwayat->isEmpty())
                <p class="p-6 text-sm text-gray-500">Belum ada peramalan yang dijalankan.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-600">
                                <th class="px-4 py-2 border-b border-gray-200">Kode</th>
                                <th class="px-4 py-2 border-b border-gray-200">Barang</th>
                                <th class="px-4 py-2 border-b border-gray-200">Model</th>
                                <th class="px-4 py-2 border-b border-gray-200 text-right">MAPE</th>
                                <th class="px-4 py-2 border-b border-gray-200">Kategori</th>
                                <th class="px-4 py-2 border-b border-gray-200">Status</th>
                                <th class="px-4 py-2 border-b border-gray-200"></th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach ($riwayat as $p)
                                <tr>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $p->kode_peramalan }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $p->barang->nama_barang }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $p->nama_model }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right">{{ number_format($p->mape, 2) }}%</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $p->kategori_akurasi }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ ucfirst($p->status) }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right">
                                        <a href="{{ route('peramalan.forecasting.show', $p) }}" class="text-indigo-600 hover:text-indigo-800">Lihat</a>
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
