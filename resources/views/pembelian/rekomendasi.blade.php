<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rekomendasi Pembelian Bahan</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Kekurangan bahan hasil perhitungan target produksi, siap diubah menjadi order.
            </p>
        </div>
    </x-slot>

    <div class="w-full space-y-4">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif
        @if (session('gagal'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            Daftar ini diisi <strong>Modul B</strong> saat menghitung target produksi dan meledakkan BOM.
            Modul A hanya membacanya. Order yang dihasilkan berstatus <strong>Dipesan</strong> seperti order
            biasa &mdash; stok baru bertambah setelah barang diterima.
        </div>

        @if ($total === 0)
            <div class="bg-white shadow-sm sm:rounded-lg p-10 text-center">
                <p class="text-sm text-gray-600">Belum ada rekomendasi pembelian.</p>
                <p class="mt-2 text-xs text-gray-500 max-w-md mx-auto">
                    Rekomendasi muncul setelah Modul B menjalankan perhitungan target produksi dan menemukan
                    bahan yang stoknya kurang. Selama itu belum dilakukan, halaman ini memang kosong.
                </p>
                <a href="{{ route('pembelian.order.create') }}"
                   class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Buat Order Manual
                </a>
            </div>
        @else
            @foreach ($perSupplier as $supplierId => $baris)
                @php $supplier = $baris->first()->barang->supplier; @endphp

                <form method="POST" action="{{ route('pembelian.rekomendasi.store') }}">
                    @csrf

                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ $supplier?->nama_supplier ?? 'Supplier tidak dikenal' }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $supplier?->kode_supplier }} &middot; {{ $baris->count() }} bahan
                                    @unless ($supplier?->is_aktif)
                                        &middot; <span class="text-amber-700">supplier nonaktif</span>
                                    @endunless
                                </p>
                            </div>
                            <x-primary-button>Buat Order</x-primary-button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <th class="px-6 py-3 w-10"></th>
                                        <th class="px-6 py-3">Bahan</th>
                                        <th class="px-6 py-3 w-28 text-right">Dibutuhkan</th>
                                        <th class="px-6 py-3 w-24 text-right">Stok</th>
                                        <th class="px-6 py-3 w-28 text-right">Kurang</th>
                                        <th class="px-6 py-3 w-32 text-right">Saran Beli</th>
                                        <th class="px-6 py-3 w-24 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($baris as $r)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-3">
                                                <input type="checkbox" name="kebutuhan[]" value="{{ $r->id }}" checked
                                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            </td>
                                            <td class="px-6 py-3">
                                                <div class="text-gray-900">{{ $r->barang?->nama_barang ?? '-' }}</div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    {{ $r->barang?->kode_barang }}
                                                    @if ($r->tahapan) &middot; {{ $r->tahapan->nama_tahapan }} @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-3 text-right text-gray-600">
                                                {{ rtrim(rtrim(number_format((float) $r->jumlah_kebutuhan, 4, ',', '.'), '0'), ',') }}
                                            </td>
                                            <td class="px-6 py-3 text-right text-gray-600">
                                                {{ rtrim(rtrim(number_format((float) $r->stok_tersedia, 4, ',', '.'), '0'), ',') }}
                                            </td>
                                            <td class="px-6 py-3 text-right text-red-600 font-medium">
                                                {{ rtrim(rtrim(number_format((float) $r->kekurangan, 4, ',', '.'), '0'), ',') }}
                                            </td>
                                            <td class="px-6 py-3 text-right font-semibold text-gray-900">
                                                {{ rtrim(rtrim(number_format((float) $r->qty_rekomendasi_beli, 4, ',', '.'), '0'), ',') }}
                                                <span class="text-xs font-normal text-gray-400">{{ $r->satuan ?? $r->barang?->satuan }}</span>
                                            </td>
                                            <td class="px-6 py-3 text-center">
                                                <span @class([
                                                    'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                                    'bg-amber-100 text-amber-800' => $r->status === 'perlu_beli',
                                                    'bg-red-100 text-red-800' => $r->status === 'mendesak',
                                                ])>{{ $r->status === 'mendesak' ? 'Mendesak' : 'Perlu Beli' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="px-6 py-3 border-t border-gray-200 text-xs text-gray-500">
                            Jumlah pesan dibulatkan ke atas ke satuan utuh. Harga diambil dari harga beli terakhir
                            pada master barang &mdash; periksa kembali sebelum order dikirim ke supplier.
                        </p>
                    </div>
                </form>
            @endforeach

            @if ($tanpaSupplier->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Belum Punya Supplier</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Bahan berikut tidak dapat diorder sebelum suppliernya ditentukan di master barang.
                        </p>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tanpaSupplier as $r)
                                <tr>
                                    <td class="px-6 py-3">
                                        <div class="text-gray-900">{{ $r->barang?->nama_barang ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ $r->barang?->kode_barang }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-right w-32 text-gray-900">
                                        {{ rtrim(rtrim(number_format((float) $r->qty_rekomendasi_beli, 4, ',', '.'), '0'), ',') }}
                                        <span class="text-xs text-gray-400">{{ $r->satuan }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right w-32">
                                        @if ($r->barang)
                                            <a href="{{ route('master.barang.edit', $r->barang) }}"
                                               class="text-indigo-600 hover:text-indigo-800">Tentukan supplier</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
