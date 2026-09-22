<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Historis</h2>
            <p class="text-sm text-gray-500 mt-0.5">Agregasi penjualan bulanan menjadi deret waktu (Zt) untuk peramalan ARIMA.</p>
        </div>
    </x-slot>

    <div class="max-w-7xl space-y-4">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
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
            <form method="GET" action="{{ route('peramalan.historis.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="barang" value="Barang Jadi" />
                    <select id="barang" name="barang" onchange="this.form.submit()"
                            class="mt-1 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        @forelse ($daftarBarang as $b)
                            <option value="{{ $b->id }}" @selected($barang && $barang->id === $b->id)>
                                {{ $b->kode_barang }} - {{ $b->nama_barang }}
                            </option>
                        @empty
                            <option value="">Belum ada barang jadi yang ditandai "diramalkan"</option>
                        @endforelse
                    </select>
                </div>
            </form>

            @if ($barang && auth()->user()?->role === 'pimpinan')
                <form method="POST" action="{{ route('peramalan.historis.agregasi') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="barang_id" value="{{ $barang->id }}">
                    <x-primary-button>Bangun / Perbarui Data Historis</x-primary-button>
                    <p class="mt-1 text-xs text-gray-500">
                        Mengagregasi ulang seluruh transaksi penjualan {{ $barang->nama_barang }} menjadi deret bulanan.
                    </p>
                </form>
            @endif
        </div>

        @if ($barang)
            <div id="hasil-deret" class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Deret Zt: {{ $barang->nama_barang }}</h3>
                    <span class="text-xs text-gray-500">{{ $deret->count() }} periode</span>
                </div>

                @if ($deret->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">
                        Belum ada data historis. Klik "Bangun / Perbarui Data Historis" di atas untuk membentuknya
                        dari transaksi penjualan.
                    </p>
                @else
                    <div class="mt-4 h-80">
                        <canvas id="grafikDeret"></canvas>
                    </div>

                    <div class="mt-6 overflow-x-auto">
                        <table class="min-w-full text-sm border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-semibold text-gray-600">
                                    <th class="px-3 py-2 border-b border-gray-200">t</th>
                                    <th class="px-3 py-2 border-b border-gray-200">Periode</th>
                                    <th class="px-3 py-2 border-b border-gray-200 text-right">Nilai Zt</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                @foreach ($deret as $titik)
                                    <tr>
                                        <td class="px-3 py-2 border-b border-gray-100">{{ $titik->urutan_t }}</td>
                                        <td class="px-3 py-2 border-b border-gray-100">{{ $titik->nama_periode }}</td>
                                        <td class="px-3 py-2 border-b border-gray-100 text-right">{{ number_format($titik->nilai_zt, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($barang && $deret->isNotEmpty())
        @push('skrip')
            <script>
                new Chart(document.getElementById('grafikDeret'), {
                    type: 'line',
                    data: {
                        labels: @json($deret->pluck('nama_periode')),
                        datasets: [{
                            label: 'Penjualan (Zt)',
                            data: @json($deret->pluck('nilai_zt')),
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.2,
                            fill: true,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } },
                    },
                });
            </script>
        @endpush
    @endif
</x-app-layout>
