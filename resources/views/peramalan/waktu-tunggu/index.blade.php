<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Waktu Tunggu Operasional</h2>
            <p class="text-sm text-gray-500 mt-0.5">Hitung waktu beli bahan + waktu produksi untuk pesanan yang dikerjakan bersamaan.</p>
        </div>
    </x-slot>

    @php
        $angka = fn ($x, $d = 0) => number_format((float) $x, $d, ',', '.');
        // Barang tidak bisa dibuat sebagian, jadi jumlah dibulatkan ke atas.
        // round() dulu supaya sisa pembagian 24,0000001 tidak menjadi 25.
        $bulatAtas = fn ($x) => number_format(ceil(round((float) $x, 6)), 0, ',', '.');
    @endphp

    <div class="max-w-5xl space-y-4">
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
                @if ($daftarBarang->isEmpty())
                    <p class="text-sm text-gray-500">Belum ada barang jadi yang ditandai "diramalkan".</p>
                @else
                    <form method="POST" action="{{ route('peramalan.waktu-tunggu.hitung') }}" class="space-y-4">
                        @csrf
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Jumlah yang akan diproduksi</p>
                            <p class="text-xs text-gray-500">Isi produk yang dikerjakan bersamaan. Kosongkan produk yang tidak diproduksi. Kapasitas tiap tahap dipakai bersama.</p>
                            <div class="mt-2 grid sm:grid-cols-2 gap-3">
                                @foreach ($daftarBarang as $b)
                                    <div class="flex items-center gap-3">
                                        <label for="jumlah_{{ $b->id }}" class="flex-1 text-sm text-gray-700">{{ $b->kode_barang }} - {{ $b->nama_barang }}</label>
                                        <x-text-input id="jumlah_{{ $b->id }}" name="jumlah[{{ $b->id }}]" type="number" step="1" min="1" max="100000"
                                                      value="{{ old('jumlah.'.$b->id) }}" class="w-28 text-sm" />
                                        <span class="w-8 text-sm text-gray-500">{{ $b->satuan }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <x-input-label for="awal_periode" value="Awal Periode Penjualan" />
                                <x-text-input id="awal_periode" name="awal_periode" type="date"
                                              value="{{ old('awal_periode') }}" class="mt-1 text-sm" required />
                            </div>
                            <div>
                                <x-input-label for="periksa_stok" value="Waktu Beli Bahan" />
                                <select id="periksa_stok" name="periksa_stok"
                                        class="mt-1 text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="1" @selected(old('periksa_stok', '1') === '1')>Hanya bila stok bahan kurang</option>
                                    <option value="0" @selected(old('periksa_stok') === '0')>Selalu beli bahan dulu</option>
                                </select>
                            </div>
                            <x-primary-button>Hitung</x-primary-button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        @if (session('hasil'))
            @php $hasil = session('hasil'); @endphp
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">Hasil</h3>
                <p class="mt-1 text-sm text-gray-600">
                    @foreach ($hasil['pesanan'] as $p)
                        {{ $p['kode_barang'] }} {{ $angka($p['jumlah'], 2) }} {{ $p['satuan'] }}@if (! $loop->last), @endif
                    @endforeach
                </p>

                <div class="mt-4 grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">L_beli (beli bahan)</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ $angka($hasil['lead_time_pembelian_hari']) }} hari</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">L_produksi (hari kerja)</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ $angka($hasil['lead_time_produksi_hari']) }} hari</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">L_total</p>
                        <p class="mt-1 text-xl font-semibold text-indigo-700">{{ $angka($hasil['lead_time_total_hari']) }} hari</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal Mulai Produksi</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $hasil['tanggal_mulai_produksi'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal Pesan Bahan</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $hasil['tanggal_pesan_bahan'] }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    L_produksi = jalur kritis {{ $angka($hasil['jalur_kritis_hari']) }} hari
                    + (tahap terlama {{ $hasil['hari_tahap_terlama'] }} hari - 1)
                    - tumpang tindih {{ $hasil['tumpang_tindih_hari'] }} hari
                    = <strong>{{ $angka($hasil['lead_time_produksi_hari']) }} hari</strong>.
                    Jalur kritis adalah urutan tahap terpanjang dari nol (cetak kepala, coating, rakit + kemas).
                    Tumpang tindih terjadi bila tahap berikutnya bisa mulai di hari yang sama karena hasil tahap sebelumnya sudah mencapai satu muatan.
                </div>

                <h4 class="mt-6 text-sm font-semibold text-gray-900">Rincian per tahap</h4>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-sm border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold text-gray-600">
                                <th class="px-3 py-2 border-b">Tahap</th>
                                <th class="px-3 py-2 border-b text-right">Jumlah</th>
                                <th class="px-3 py-2 border-b text-right">Kapasitas per hari</th>
                                <th class="px-3 py-2 border-b text-right">Waktu proses</th>
                                <th class="px-3 py-2 border-b text-right">Hari menurut kapasitas</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            @foreach ($hasil['rincian_tahapan'] as $t)
                                <tr @class(['font-semibold' => $t['hari_kapasitas'] === $hasil['hari_tahap_terlama']])>
                                    <td class="px-3 py-2 border-b">{{ $t['kode_tahapan'] }} {{ $t['nama_tahapan'] }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $bulatAtas($t['jumlah']) }} {{ $t['satuan'] }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $angka($t['kapasitas_per_hari']) }} {{ $t['satuan'] }}</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $angka($t['waktu_proses_hari']) }} hari</td>
                                    <td class="px-3 py-2 border-b text-right">{{ $t['hari_kapasitas'] }} hari</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h4 class="mt-6 text-sm font-semibold text-gray-900">Bahan yang harus dibeli dulu</h4>
                @if (empty($hasil['bahan_kurang']))
                    <p class="mt-1 text-sm text-gray-600">Semua bahan cukup, jadi produksi bisa langsung dimulai tanpa menunggu pembelian.</p>
                @else
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-sm border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-semibold text-gray-600">
                                    <th class="px-3 py-2 border-b">Bahan</th>
                                    <th class="px-3 py-2 border-b text-right">Kebutuhan</th>
                                    <th class="px-3 py-2 border-b text-right">Stok</th>
                                    <th class="px-3 py-2 border-b text-right">Lama datang</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                @foreach ($hasil['bahan_kurang'] as $b)
                                    <tr>
                                        <td class="px-3 py-2 border-b">{{ $b['kode_barang'] }} - {{ $b['nama_barang'] }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ $bulatAtas($b['kebutuhan']) }} {{ $b['satuan'] }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ $angka($b['stok_tersedia'], 2) }} {{ $b['satuan'] }}</td>
                                        <td class="px-3 py-2 border-b text-right">{{ $angka($b['lead_time_hari']) }} hari</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @unless ($hasil['periksa_stok'])
                        <p class="mt-1 text-xs text-gray-500">Mode "selalu beli bahan dulu": semua bahan dianggap harus dibeli.</p>
                    @endunless
                @endif

                @if ($hasil['lead_time_total_hari'] > 30)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Waktu tunggu total lebih dari 30 hari: perintah produksi untuk bulan depan harus sudah dibuat bulan ini.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
