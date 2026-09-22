<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Selamat datang, {{ auth()->user()->name }}. Ringkasan keadaan pabrik per
                {{ now()->translatedFormat('d F Y') }}.
            </p>
        </div>
    </x-slot>

    <div class="w-full space-y-6">
        {{-- Kartu ringkasan --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Barang Aktif</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($jumlahBarangAktif, 0, ',', '.') }}</p>
                <a href="{{ route('master.barang.index') }}" class="mt-1 inline-block text-xs text-indigo-600 hover:text-indigo-800">Kelola barang</a>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Menipis</p>
                <p @class(['mt-1 text-2xl font-semibold', 'text-red-600' => $jumlahMenipis > 0, 'text-gray-900' => $jumlahMenipis === 0])>
                    {{ number_format($jumlahMenipis, 0, ',', '.') }}
                </p>
                <a href="{{ route('persediaan.stok', ['menipis' => 1]) }}" class="mt-1 inline-block text-xs text-indigo-600 hover:text-indigo-800">Lihat daftar</a>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nilai Persediaan</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    <span class="text-base font-normal text-gray-500">Rp</span>{{ number_format($nilaiPersediaan, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Memakai harga beli</p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Penjualan Bulan Ini</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ number_format($penjualanBulanIni['jumlah'], 0, ',', '.') }}
                    <span class="text-base font-normal text-gray-500">unit</span>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $penjualanBulanIni['faktur'] }} faktur &middot; Rp {{ number_format($penjualanBulanIni['nilai'], 0, ',', '.') }}
                </p>
            </div>
        </div>

        {{-- Perlu ditindaklanjuti --}}
        @if ($orderMenunggu > 0 || $produksiBerjalan > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if ($orderMenunggu > 0)
                    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex items-center justify-between gap-4">
                        <span><strong>{{ $orderMenunggu }} order pembelian</strong> menunggu penerimaan barang.</span>
                        <a href="{{ route('pembelian.order.index', ['status' => 'dipesan']) }}"
                           class="shrink-0 font-medium underline hover:no-underline">Buka</a>
                    </div>
                @endif

                @if ($produksiBerjalan > 0)
                    <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 flex items-center justify-between gap-4">
                        <span><strong>{{ $produksiBerjalan }} perintah produksi</strong> sedang berjalan.</span>
                        <a href="{{ route('produksi.bom.index') }}" class="shrink-0 font-medium underline hover:no-underline">Lihat produksi</a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Grafik tren penjualan --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex items-baseline justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Tren Penjualan 12 Bulan Terakhir</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Dalam unit. Inilah deret yang diramalkan ARIMA oleh Modul B.</p>
                </div>
            </div>

            @php
                $puncak = max(1, collect($trenPenjualan)->max('jumlah'));
                $lebarSvg = 1000;
                $tinggiSvg = 200;
                $margin = 12;
                $jumlahTitik = count($trenPenjualan);
                $langkah = $jumlahTitik > 1 ? ($lebarSvg - 2 * $margin) / ($jumlahTitik - 1) : 0;

                $titikSvg = collect($trenPenjualan)->values()->map(function ($titik, $i) use ($margin, $langkah, $tinggiSvg, $puncak) {
                    return [
                        'x' => round($margin + $i * $langkah, 2),
                        'y' => round($tinggiSvg - 16 - ($titik['jumlah'] / $puncak * ($tinggiSvg - 32)), 2),
                        'label' => $titik['label'],
                        'jumlah' => $titik['jumlah'],
                    ];
                });

                $garisPoin = $titikSvg->map(fn ($p) => "{$p['x']},{$p['y']}")->implode(' ');
                $areaPoin = "{$margin},{$tinggiSvg} {$garisPoin} " . ($lebarSvg - $margin) . ",{$tinggiSvg}";
            @endphp

            <div class="mt-5 h-56">
                <svg viewBox="0 0 {{ $lebarSvg }} {{ $tinggiSvg }}" preserveAspectRatio="none" class="h-full w-full overflow-visible">
                    <defs>
                        <linearGradient id="trenGradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgb(99 102 241)" stop-opacity="0.25" />
                            <stop offset="100%" stop-color="rgb(99 102 241)" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <polygon points="{{ $areaPoin }}" fill="url(#trenGradient)" />

                    <polyline points="{{ $garisPoin }}"
                              fill="none"
                              stroke="rgb(79 70 229)"
                              stroke-width="2.5"
                              stroke-linejoin="round"
                              stroke-linecap="round"
                              vector-effect="non-scaling-stroke" />

                    @foreach ($titikSvg as $p)
                        <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3.5"
                                fill="white" stroke="rgb(79 70 229)" stroke-width="2"
                                vector-effect="non-scaling-stroke">
                            <title>{{ $p['label'] }}: {{ number_format($p['jumlah'], 0, ',', '.') }} unit</title>
                        </circle>
                    @endforeach
                </svg>
            </div>

            <div class="mt-2 flex gap-2">
                @foreach ($trenPenjualan as $titik)
                    <div class="flex-1 text-center text-[10px] text-gray-500 truncate">{{ $titik['label'] }}</div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Stok menipis --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                    <h3 class="text-sm font-semibold text-gray-900">Perlu Segera Dipesan</h3>
                    <a href="{{ route('persediaan.stok', ['menipis' => 1]) }}" class="text-xs text-indigo-600 hover:text-indigo-800">Semua</a>
                </div>

                @if ($barangMenipis->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">
                        Seluruh stok masih di atas batas minimum.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($barangMenipis as $b)
                                <tr>
                                    <td class="px-6 py-3">
                                        <div class="text-gray-900">{{ $b->nama_barang }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ $b->kode_barang }} &middot; {{ $b->kategori?->nama_kategori }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-right w-32">
                                        <div class="font-semibold text-red-600">{{ number_format($b->stok_tersedia, 0, ',', '.') }}</div>
                                        <div class="text-xs text-gray-400">min {{ number_format($b->stok_minimum, 0, ',', '.') }} {{ $b->satuan }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Aktivitas terbaru --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">Aktivitas Terbaru</h3>
                </div>

                @if ($aktivitasTerbaru->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">Belum ada aktivitas tercatat.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($aktivitasTerbaru as $log)
                            <li class="px-6 py-3">
                                <p class="text-sm text-gray-900">{{ $log->aktivitas }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $log->modul }} &middot; {{ $log->user?->name ?? 'sistem' }} &middot;
                                    {{ $log->created_at?->diffForHumans() }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Mutasi stok terbaru --}}
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-gray-900">Pergerakan Stok Terakhir</h3>
                <a href="{{ route('persediaan.mutasi') }}" class="text-xs text-indigo-600 hover:text-indigo-800">Kartu stok lengkap</a>
            </div>

            @if ($mutasiTerbaru->isEmpty())
                <p class="px-6 py-8 text-center text-sm text-gray-500">Belum ada pergerakan stok.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($mutasiTerbaru as $m)
                            <tr>
                                <td class="px-6 py-3 text-gray-600 w-28">{{ $m->tanggal?->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 text-gray-900">{{ $m->barang?->nama_barang ?? '-' }}</td>
                                <td class="px-6 py-3 w-32 text-right font-medium">
                                    @php $nilai = (float) $m->jumlah; @endphp
                                    <span @class([
                                        'text-green-700' => $m->jenis_mutasi === 'masuk' || ($m->jenis_mutasi === 'penyesuaian' && $nilai > 0),
                                        'text-red-700' => $m->jenis_mutasi === 'keluar' || ($m->jenis_mutasi === 'penyesuaian' && $nilai < 0),
                                    ])>
                                        {{ $m->jenis_mutasi === 'keluar' ? '−' : ($nilai > 0 ? '+' : '') }}{{ number_format(abs($nilai), 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $m->barang?->satuan }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 text-xs">{{ $m->keterangan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
