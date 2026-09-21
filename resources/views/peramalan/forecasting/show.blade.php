<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $peramalan->kode_peramalan }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $peramalan->barang->nama_barang }}, {{ $peramalan->nama_model }}, {{ $peramalan->periode_awal }} s.d. {{ $peramalan->periode_akhir }}
                </p>
            </div>
            <a href="{{ route('peramalan.forecasting.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Riwayat peramalan</a>
        </div>
    </x-slot>

    <div class="max-w-7xl space-y-6">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        {{-- Ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Model Terpilih</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ $peramalan->nama_model }}</p>
                <p class="mt-1 text-xs text-gray-500">AIC {{ number_format($peramalan->aic, 3) }} &middot; BIC {{ number_format($peramalan->bic, 3) }}</p>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">MAPE</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($peramalan->mape, 2) }}%</p>
                <p class="mt-1 text-xs text-gray-500">{{ $peramalan->kategori_akurasi }}</p>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">RMSE / MAE</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($peramalan->rmse, 1) }} / {{ number_format($peramalan->mae, 1) }}</p>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Diagnostic Checking</p>
                @php $terpilih = $peramalan->kandidatModel->firstWhere('is_terpilih', true); @endphp
                <p @class(['mt-1 text-xl font-semibold', 'text-green-600' => $terpilih?->lolos_ljung_box, 'text-red-600' => ! $terpilih?->lolos_ljung_box])>
                    {{ $terpilih?->lolos_ljung_box ? 'Lolos' : 'Tidak Lolos' }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Uji Ljung-Box (residual white noise)</p>
            </div>
        </div>

        {{-- Tahap 1a: Uji Stasioneritas --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Tahap 1: Uji Stasioneritas (ADF)</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-3 py-2 border-b">Differencing ke-</th>
                            <th class="px-3 py-2 border-b text-right">Statistik ADF</th>
                            <th class="px-3 py-2 border-b text-right">Kritis 5%</th>
                            <th class="px-3 py-2 border-b text-right">p-value</th>
                            <th class="px-3 py-2 border-b">Kesimpulan</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        @foreach ($peramalan->ujiStasioneritas as $u)
                            <tr>
                                <td class="px-3 py-2 border-b">{{ $u->differencing_ke }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($u->adf_statistic, 4) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($u->nilai_kritis_5, 4) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($u->p_value, 4) }}</td>
                                <td class="px-3 py-2 border-b">{{ $u->kesimpulan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tahap 1b: Correlogram ACF & PACF --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Tahap 1: Correlogram ACF &amp; PACF (deret stasioner)</h3>
            <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-1">ACF</p>
                    <div class="h-64"><canvas id="grafikAcf"></canvas></div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-1">PACF</p>
                    <div class="h-64"><canvas id="grafikPacf"></canvas></div>
                </div>
            </div>
        </div>

        {{-- Tahap 2a: Kandidat Model --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Tahap 2: Kandidat Model (Grid Search)</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-3 py-2 border-b">Model</th>
                            <th class="px-3 py-2 border-b text-right">AIC</th>
                            <th class="px-3 py-2 border-b text-right">BIC</th>
                            <th class="px-3 py-2 border-b">Semua Signifikan?</th>
                            <th class="px-3 py-2 border-b"></th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        @foreach ($peramalan->kandidatModel as $k)
                            <tr @class(['bg-indigo-50' => $k->is_terpilih])>
                                <td class="px-3 py-2 border-b">{{ $k->nama_model }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($k->aic, 3) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($k->bic, 3) }}</td>
                                <td class="px-3 py-2 border-b">{{ $k->semua_signifikan ? 'Ya' : 'Tidak' }}</td>
                                <td class="px-3 py-2 border-b text-right">
                                    @if ($k->is_terpilih)
                                        <span class="text-xs font-semibold text-indigo-700 uppercase">Terpilih</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tahap 2b: Parameter Model Terpilih --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Tahap 2: Parameter Model Terpilih</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-3 py-2 border-b">Parameter</th>
                            <th class="px-3 py-2 border-b text-right">Koefisien</th>
                            <th class="px-3 py-2 border-b text-right">Std. Error</th>
                            <th class="px-3 py-2 border-b text-right">t-hitung</th>
                            <th class="px-3 py-2 border-b text-right">t-tabel</th>
                            <th class="px-3 py-2 border-b text-right">p-value</th>
                            <th class="px-3 py-2 border-b">Signifikan?</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        @foreach ($peramalan->parameterModel as $p)
                            <tr>
                                <td class="px-3 py-2 border-b">{{ $p->nama_parameter }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($p->koefisien, 4) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($p->standard_error, 4) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($p->t_hitung, 3) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($p->t_tabel, 3) }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($p->p_value, 4) }}</td>
                                <td class="px-3 py-2 border-b">{{ $p->is_signifikan ? 'Ya' : 'Tidak' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tahap 4: Hasil Peramalan --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Tahap 4: Aktual vs Prediksi &amp; Forecast</h3>
            <div class="mt-3 h-80">
                <canvas id="grafikForecast"></canvas>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-3 py-2 border-b">Periode</th>
                            <th class="px-3 py-2 border-b text-right">Prediksi</th>
                            <th class="px-3 py-2 border-b text-right">Batas Bawah (95%)</th>
                            <th class="px-3 py-2 border-b text-right">Batas Atas (95%)</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        @foreach ($hasilForecast as $h)
                            <tr>
                                <td class="px-3 py-2 border-b">{{ $h->periode }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($h->nilai_prediksi, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($h->batas_bawah, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b text-right">{{ number_format($h->batas_atas, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @push('skrip')
        <script>
            function grafikCorrelogram(id, data) {
                new Chart(document.getElementById(id), {
                    data: {
                        labels: data.map(d => d.lag),
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Nilai',
                                data: data.map(d => d.nilai),
                                backgroundColor: data.map(d => d.signifikan ? '#4f46e5' : '#c7d2fe'),
                            },
                            {
                                type: 'line',
                                label: 'Batas Atas',
                                data: data.map(d => d.batasAtas),
                                borderColor: '#ef4444',
                                borderDash: [4, 4],
                                pointRadius: 0,
                                borderWidth: 1,
                            },
                            {
                                type: 'line',
                                label: 'Batas Bawah',
                                data: data.map(d => d.batasBawah),
                                borderColor: '#ef4444',
                                borderDash: [4, 4],
                                pointRadius: 0,
                                borderWidth: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { x: { title: { display: true, text: 'Lag' } } },
                    },
                });
            }

            grafikCorrelogram('grafikAcf', @json($dataAcf));
            grafikCorrelogram('grafikPacf', @json($dataPacf));

            const dataForecast = @json($dataForecastChart);

            new Chart(document.getElementById('grafikForecast'), {
                type: 'line',
                data: {
                    labels: dataForecast.labels,
                    datasets: [
                        { label: 'Aktual', data: dataForecast.aktual, borderColor: '#111827', pointRadius: 2 },
                        { label: 'Prediksi (in-sample) & Forecast', data: dataForecast.prediksi, borderColor: '#4f46e5', borderDash: [5, 3], pointRadius: 1 },
                        { label: 'Batas Atas', data: dataForecast.batasAtas, borderColor: 'rgba(79, 70, 229, 0.3)', pointRadius: 0, fill: '+1' },
                        { label: 'Batas Bawah', data: dataForecast.batasBawah, borderColor: 'rgba(79, 70, 229, 0.3)', pointRadius: 0 },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        </script>
    @endpush
</x-app-layout>
