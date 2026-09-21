<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stok Saat Ini</h2>
                <p class="text-sm text-gray-500 mt-0.5">Potret persediaan seluruh barang aktif.</p>
            </div>
            <a href="{{ route('persediaan.opname.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                Stok Opname
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl">
        <div class="mb-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Barang Aktif</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($jumlahBarangAktif, 0, ',', '.') }}</p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Menipis</p>
                <p @class(['mt-1 text-2xl font-semibold', 'text-red-600' => $jumlahMenipis > 0, 'text-gray-900' => $jumlahMenipis === 0])>
                    {{ number_format($jumlahMenipis, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Stok &le; stok minimum</p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Kosong</p>
                <p @class(['mt-1 text-2xl font-semibold', 'text-red-600' => $jumlahKosong > 0, 'text-gray-900' => $jumlahKosong === 0])>
                    {{ number_format($jumlahKosong, 0, ',', '.') }}
                </p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nilai Persediaan</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    <span class="text-base font-normal text-gray-500">Rp</span>
                    {{ number_format($nilaiPersediaan, 0, ',', '.') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Dihitung memakai harga beli</p>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('persediaan.stok') }}" class="flex flex-wrap items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-56 text-sm"
                                  placeholder="Cari kode atau nama barang" />

                    <select name="jenis"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($jenis === 'semua')>Semua jenis</option>
                        @foreach ($daftarJenis as $nilai => $label)
                            <option value="{{ $nilai }}" @selected($jenis === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="kategori"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($kategoriId === 'semua')>Semua kategori</option>
                        @foreach ($daftarKategori as $kat)
                            <option value="{{ $kat->id }}" @selected((string) $kategoriId === (string) $kat->id)>
                                {{ $kat->nama_kategori }}
                            </option>
                        @endforeach
                    </select>

                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                        <input type="checkbox" name="menipis" value="1" @checked($hanyaMenipis)
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Stok menipis
                    </label>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $jenis !== 'semua' || $kategoriId !== 'semua' || $hanyaMenipis)
                        <a href="{{ route('persediaan.stok') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-24">Kode</th>
                            <th class="px-4 py-3">Nama Barang</th>
                            <th class="px-4 py-3 w-28">Jenis</th>
                            <th class="px-4 py-3 w-28 text-right">Stok</th>
                            <th class="px-4 py-3 w-24 text-right">Minimum</th>
                            <th class="px-4 py-3 w-28 text-center">Keadaan</th>
                            <th class="px-4 py-3 w-32 text-right">Nilai</th>
                            <th class="px-4 py-3 w-28 text-right">Kartu Stok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($barang as $item)
                            @php
                                $kosong = $item->stok_tersedia <= 0;
                                $menipis = $item->stok_tersedia <= $item->stok_minimum;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->kode_barang }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->nama_barang }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->kategori?->nama_kategori ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-amber-50 text-amber-800' => $item->jenis_barang === 'bahan_baku',
                                        'bg-sky-50 text-sky-800' => $item->jenis_barang === 'setengah_jadi',
                                        'bg-emerald-50 text-emerald-800' => $item->jenis_barang === 'barang_jadi',
                                    ])>
                                        {{ $daftarJenis[$item->jenis_barang] ?? $item->jenis_barang }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span @class(['font-semibold', 'text-red-600' => $menipis, 'text-gray-900' => ! $menipis])>
                                        {{ number_format($item->stok_tersedia, 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $item->satuan }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">
                                    {{ number_format($item->stok_minimum, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($kosong)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Kosong</span>
                                    @elseif ($menipis)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Menipis</span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aman</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">
                                    Rp {{ number_format($item->stok_tersedia * (float) $item->harga_beli, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('persediaan.mutasi', ['barang' => $item->id]) }}"
                                       class="text-indigo-600 hover:text-indigo-800">Lihat riwayat</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Barang tidak ditemukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($barang->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $barang->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
