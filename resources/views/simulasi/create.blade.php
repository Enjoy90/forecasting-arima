<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Jalankan Simulasi Baru</h2>
                <p class="text-sm text-gray-500 mt-0.5">24 bulan pertama membentuk model, 12 bulan terakhir diuji dua skenario berdampingan.</p>
            </div>
            <a href="{{ route('simulasi.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Riwayat simulasi</a>
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-4">
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
                <form method="POST" action="{{ route('simulasi.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="barang_id" value="Barang Jadi" />
                        <select id="barang_id" name="barang_id" required
                                class="mt-1 block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($daftarBarang as $b)
                                <option value="{{ $b->id }}" @selected(old('barang_id') == $b->id)>
                                    {{ $b->kode_barang }} - {{ $b->nama_barang }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Butuh minimal 36 bulan data historis (menu Data Historis).</p>
                    </div>

                    <div>
                        <x-input-label for="metode_pembanding" value="Metode Pembanding (Kebijakan Perusahaan)" />
                        <select id="metode_pembanding" name="metode_pembanding" required
                                class="mt-1 block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="naif_bulan_lalu" @selected(old('metode_pembanding') === 'naif_bulan_lalu')>Naif -- produksi = penjualan bulan lalu</option>
                            <option value="rata_rata_bergerak" @selected(old('metode_pembanding') === 'rata_rata_bergerak')>Rata-rata bergerak 3 bulan</option>
                            <option value="produksi_aktual" @selected(old('metode_pembanding') === 'produksi_aktual')>Produksi aktual (butuh data produksi asli)</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label for="stok_awal_simulasi" value="Stok Awal Simulasi" />
                        <x-text-input id="stok_awal_simulasi" name="stok_awal_simulasi" type="number" step="0.01" min="0"
                                      value="{{ old('stok_awal_simulasi') }}" class="mt-1 block w-full text-sm" required />
                        <p class="mt-1 text-xs text-gray-500">Perkiraan stok barang jadi pada awal periode 12 bulan simulasi.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="biaya_simpan_per_unit" value="Biaya Simpan / Unit / Bulan" />
                            <x-text-input id="biaya_simpan_per_unit" name="biaya_simpan_per_unit" type="number" step="0.01" min="0"
                                          value="{{ old('biaya_simpan_per_unit') }}" class="mt-1 block w-full text-sm" required />
                        </div>
                        <div>
                            <x-input-label for="biaya_stockout_per_unit" value="Biaya Stockout / Unit" />
                            <x-text-input id="biaya_stockout_per_unit" name="biaya_stockout_per_unit" type="number" step="0.01" min="0"
                                          value="{{ old('biaya_stockout_per_unit') }}" class="mt-1 block w-full text-sm" required />
                        </div>
                    </div>

                    <div class="pt-2">
                        <x-primary-button>Jalankan Simulasi</x-primary-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
