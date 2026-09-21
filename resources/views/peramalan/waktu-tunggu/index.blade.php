<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Waktu Tunggu Operasional</h2>
            <p class="text-sm text-gray-500 mt-0.5">Hitung waktu beli bahan + waktu produksi untuk jumlah target tertentu.</p>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-4">
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
            @if ($daftarBarang->isEmpty())
                <p class="text-sm text-gray-500">Belum ada barang jadi yang ditandai "diramalkan".</p>
            @else
                <form method="POST" action="{{ route('peramalan.waktu-tunggu.hitung') }}" class="flex flex-wrap items-end gap-3">
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
                        <x-input-label for="jumlah_target" value="Jumlah Target" />
                        <x-text-input id="jumlah_target" name="jumlah_target" type="number" step="0.01" min="0.01"
                                      value="{{ old('jumlah_target') }}" class="mt-1 w-32 text-sm" required />
                    </div>
                    <div>
                        <x-input-label for="awal_periode" value="Awal Periode Penjualan" />
                        <x-text-input id="awal_periode" name="awal_periode" type="date"
                                      value="{{ old('awal_periode') }}" class="mt-1 text-sm" required />
                    </div>
                    <x-primary-button>Hitung</x-primary-button>
                </form>
            @endif
        </div>

        @if (session('hasil'))
            @php $hasil = session('hasil'); @endphp
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">Hasil: {{ $hasil['barang'] }} ({{ number_format($hasil['jumlah_target'], 0, ',', '.') }} unit)</h3>

                <div class="mt-4 grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">L_beli (beli bahan)</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($hasil['lead_time_pembelian_hari'], 1) }} hari</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">L_produksi (5 tahapan)</p>
                        <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($hasil['lead_time_produksi_hari'], 1) }} hari</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">L_total</p>
                        <p class="mt-1 text-xl font-semibold text-indigo-700">{{ number_format($hasil['lead_time_total_hari'], 1) }} hari</p>
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

                @if ($hasil['lead_time_total_hari'] > 30)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Waktu tunggu total lebih dari 30 hari: perintah produksi untuk bulan depan harus sudah dibuat bulan ini.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
