<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Target Produksi</h2>
            <p class="text-sm text-gray-500 mt-0.5">Terjemahkan hasil peramalan menjadi rencana produksi + kebutuhan bahan.</p>
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

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Hitung Target Produksi Baru</h3>

            <form method="GET" action="{{ route('peramalan.target.index') }}" class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="barang" value="Barang Jadi" />
                    <select id="barang" name="barang" onchange="this.form.submit()"
                            class="mt-1 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">- pilih -</option>
                        @foreach ($daftarBarang as $b)
                            <option value="{{ $b->id }}" @selected((string) $barangId === (string) $b->id)>
                                {{ $b->kode_barang }} - {{ $b->nama_barang }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if ($barangId)
                @if ($periodeForecast->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">Belum ada hasil forecast untuk barang ini. Jalankan Proses Forecasting dulu.</p>
                @else
                    <form method="POST" action="{{ route('peramalan.target.hitung') }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="barang_id" value="{{ $barangId }}">
                        <div>
                            <x-input-label for="periode" value="Periode Forecast" />
                            <select id="periode" name="periode" required
                                    class="mt-1 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($periodeForecast as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button>Hitung Target Produksi</x-primary-button>
                    </form>
                @endif
            @endif
        </div>

        @if ($detail)
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $detail->barang->nama_barang }} - {{ $detail->periode }}</h3>
                    @if ($detail->status_approval === 'menunggu' && $detail->jumlah_target_produksi > 0)
                        <form method="POST" action="{{ route('peramalan.target.setujui', $detail) }}">
                            @csrf
                            @method('PATCH')
                            <x-primary-button>Setujui</x-primary-button>
                        </form>
                    @else
                        <span class="text-xs font-semibold uppercase text-gray-500">{{ ucfirst($detail->status_approval) }}</span>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Prediksi Penjualan</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($detail->prediksi_penjualan, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Safety Stock</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($detail->safety_stock, 1) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reorder Point</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($detail->reorder_point, 1) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Target Produksi</p>
                        <p class="mt-1 text-lg font-semibold text-indigo-700">{{ number_format($detail->jumlah_target_produksi, 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Barang Jadi</p>
                        <p class="mt-1 text-sm text-gray-900">{{ number_format($detail->stok_barang_jadi, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Setengah Jadi</p>
                        <p class="mt-1 text-sm text-gray-900">{{ number_format($detail->stok_setengah_jadi, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Waktu Tunggu Total</p>
                        <p class="mt-1 text-sm text-gray-900">{{ number_format($detail->lead_time_total_hari, 1) }} hari</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</p>
                        <p @class([
                            'mt-1 text-sm font-semibold',
                            'text-green-600' => $detail->status_stok === 'aman',
                            'text-amber-600' => $detail->status_stok === 'segera_produksi',
                            'text-red-600' => $detail->status_stok === 'kritis',
                        ])>{{ str_replace('_', ' ', ucfirst($detail->status_stok)) }}</p>
                    </div>
                </div>

                @if ($detail->tanggal_mulai_produksi)
                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal Mulai Produksi</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $detail->tanggal_mulai_produksi->format('Y-m-d') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal Pesan Bahan</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $detail->tanggal_pesan_bahan->format('Y-m-d') }}</p>
                        </div>
                    </div>
                @endif

                @if ($detail->kebutuhanBahan->isNotEmpty())
                    <div class="mt-6 overflow-x-auto">
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Kebutuhan Bahan Baku</h4>
                        <table class="min-w-full text-sm border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-semibold text-gray-600">
                                    <th class="px-3 py-2 border-b">Bahan</th>
                                    <th class="px-3 py-2 border-b text-right">Kebutuhan</th>
                                    <th class="px-3 py-2 border-b text-right">Stok Tersedia</th>
                                    <th class="px-3 py-2 border-b text-right">Kekurangan</th>
                                    <th class="px-3 py-2 border-b text-right">Rekomendasi Beli</th>
                                    <th class="px-3 py-2 border-b">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                @foreach ($detail->kebutuhanBahan as $k)
                                    <tr>
                                        <td class="px-3 py-2 border-b">{{ $k->barang->kode_barang }} - {{ $k->barang->nama_barang }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ number_format($k->jumlah_kebutuhan, 2) }} {{ $k->satuan }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ number_format($k->stok_tersedia, 2) }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ number_format($k->kekurangan, 2) }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ number_format($k->qty_rekomendasi_beli, 2) }}</td>
                                        <td class="px-3 py-2 border-b">
                                            <span @class([
                                                'text-xs font-semibold uppercase',
                                                'text-green-600' => $k->status === 'cukup',
                                                'text-amber-600' => $k->status === 'perlu_beli',
                                                'text-red-600' => $k->status === 'mendesak',
                                            ])>{{ str_replace('_', ' ', $k->status) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Riwayat Target Produksi</h3>
            </div>

            @if ($riwayat->isEmpty())
                <p class="p-6 text-sm text-gray-500">Belum ada target produksi yang dihitung.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-600">
                                <th class="px-4 py-2 border-b border-gray-200">Barang</th>
                                <th class="px-4 py-2 border-b border-gray-200">Periode</th>
                                <th class="px-4 py-2 border-b border-gray-200 text-right">Target Produksi</th>
                                <th class="px-4 py-2 border-b border-gray-200">Status Stok</th>
                                <th class="px-4 py-2 border-b border-gray-200">Persetujuan</th>
                                <th class="px-4 py-2 border-b border-gray-200"></th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach ($riwayat as $r)
                                <tr>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $r->barang->nama_barang }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ $r->periode }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right">{{ number_format($r->jumlah_target_produksi, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ str_replace('_', ' ', ucfirst($r->status_stok)) }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100">{{ ucfirst($r->status_approval) }}</td>
                                    <td class="px-4 py-2 border-b border-gray-100 text-right">
                                        <a href="{{ route('peramalan.target.index', ['barang' => $r->barang_id, 'target' => $r->id]) }}" class="text-indigo-600 hover:text-indigo-800">Lihat</a>
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
