<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import Pembelian dari Excel</h2>
                <p class="text-sm text-gray-500 mt-0.5">Entri banyak order pembelian sekaligus.</p>
            </div>
            <a href="{{ route('pembelian.order.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Daftar pembelian</a>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-4">
        @if (session('gagal'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">{{ session('gagal') }}</p>

                @if (session('galatImport'))
                    <ul class="list-disc list-inside mt-2 space-y-0.5">
                        @foreach (session('galatImport') as $galat)
                            <li>{{ $galat }}</li>
                        @endforeach
                    </ul>
                    @if (session('sisaGalat') > 0)
                        <p class="mt-2 text-xs">...dan {{ session('sisaGalat') }} kesalahan lainnya.</p>
                    @endif
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium">Order hasil import berstatus "Dipesan", stok tidak langsung bertambah.</p>
            <p class="mt-1">
                Persis seperti order yang dibuat lewat form manual. Stok baru bertambah nanti setelah tiap order
                diproses penerimaannya satu per satu di menu Pembelian. Jalan masuk ini hanya mempercepat entri
                banyak order sekaligus, misalnya dari catatan atau backlog lama.
            </p>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900">Susunan Berkas</h3>
            <p class="mt-1 text-sm text-gray-600">
                Satu baris berisi satu barang. Beberapa baris dengan <code class="text-xs bg-gray-100 px-1 rounded">no_pembelian</code>
                yang sama akan digabung menjadi satu order.
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold text-gray-600">
                            <th class="px-3 py-2 border-b border-gray-200">tanggal</th>
                            <th class="px-3 py-2 border-b border-gray-200">no_pembelian</th>
                            <th class="px-3 py-2 border-b border-gray-200">kode_supplier</th>
                            <th class="px-3 py-2 border-b border-gray-200">kode_barang</th>
                            <th class="px-3 py-2 border-b border-gray-200">jumlah</th>
                            <th class="px-3 py-2 border-b border-gray-200">harga_satuan</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600">
                        <tr>
                            <td class="px-3 py-2 border-b border-gray-100">2026-08-05</td>
                            <td class="px-3 py-2 border-b border-gray-100">PB-2026-0001</td>
                            <td class="px-3 py-2 border-b border-gray-100">SUP-01</td>
                            <td class="px-3 py-2 border-b border-gray-100">BB-01</td>
                            <td class="px-3 py-2 border-b border-gray-100">100</td>
                            <td class="px-3 py-2 border-b border-gray-100">15000</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="mt-4 space-y-1 text-sm text-gray-600 list-disc list-inside">
                <li><strong>kode_supplier</strong> dan <strong>kode_barang</strong> harus sudah terdaftar di master.</li>
                <li>Satu order (<strong>no_pembelian</strong> yang sama) hanya boleh untuk <strong>satu supplier</strong>.</li>
                <li>Barang yang sama tidak boleh ditulis dua kali dalam satu order — gabungkan jadi satu baris.</li>
                <li>Nomor pembelian yang sudah ada akan <strong>dilewati</strong>, bukan ditimpa — jadi import ulang tidak menggandakan order.</li>
                <li>Bila ada satu baris yang salah, <strong>seluruh berkas dibatalkan</strong> agar data tidak masuk setengah-setengah.</li>
            </ul>

            <a href="{{ route('pembelian.import.template') }}"
               class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                Unduh Berkas Contoh
            </a>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <form method="POST" action="{{ route('pembelian.import.store') }}" enctype="multipart/form-data">
                @csrf

                <x-input-label for="berkas" value="Berkas Excel" />
                <input id="berkas" name="berkas" type="file" required accept=".xlsx,.xls,.csv"
                       class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-md
                              file:border-0 file:text-xs file:font-semibold file:uppercase file:tracking-widest
                              file:bg-gray-800 file:text-white hover:file:bg-gray-700">
                <p class="mt-1 text-xs text-gray-500">Format .xlsx, .xls, atau .csv. Maksimal 5 MB.</p>
                <x-input-error class="mt-1" :messages="$errors->get('berkas')" />

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Proses Import</x-primary-button>
                    <a href="{{ route('pembelian.order.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
