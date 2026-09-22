<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Data Barang</h2>
                <p class="text-sm text-gray-500 mt-0.5">Bahan baku, barang setengah jadi, dan barang jadi dalam satu daftar.</p>
            </div>
            @if (auth()->user()?->role === 'admin')
                <a href="{{ route('master.barang.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    + Tambah Barang
                </a>
            @endif
        </div>
    </x-slot>

    <div class="w-full">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('sukses') }}
            </div>
        @endif

        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('gagal') }}
            </div>
        @endif

        @if ($jumlahMenipis > 0 && ! $hanyaMenipis)
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 flex items-center justify-between gap-4">
                <span><strong>{{ $jumlahMenipis }} barang</strong> stoknya sudah menyentuh atau di bawah stok minimum.</span>
                <a href="{{ route('master.barang.index', ['menipis' => 1]) }}"
                   class="shrink-0 font-medium underline hover:no-underline">Lihat daftarnya</a>
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('master.barang.index') }}" class="flex flex-wrap items-center gap-2">
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

                    <select name="status"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($status === 'semua')>Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Hanya aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Hanya nonaktif</option>
                    </select>

                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                        <input type="checkbox" name="menipis" value="1" @checked($hanyaMenipis)
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Stok menipis
                    </label>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $jenis !== 'semua' || $kategoriId !== 'semua' || $status !== 'semua' || $hanyaMenipis)
                        <a href="{{ route('master.barang.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
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
                            <th class="px-4 py-3 w-32 text-right">Harga</th>
                            <th class="px-4 py-3 w-32 text-right">Stok</th>
                            <th class="px-4 py-3 w-20 text-center">Lead Time</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                            <th class="px-4 py-3 w-32 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($barang as $item)
                            @php
                                $terpakai = $item->bom_count + $item->dipakai_di_bom_count
                                    + $item->detail_pembelian_count + $item->detail_penjualan_count
                                    + $item->mutasi_stok_count + $item->data_time_series_count
                                    + $item->peramalan_count + $item->simulasi_count;
                                $menipis = $item->stok_tersedia <= $item->stok_minimum;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->kode_barang }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900 flex items-center gap-2">
                                        {{ $item->nama_barang }}
                                        @if ($item->is_diramalkan)
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-100 text-purple-700"
                                                  title="Penjualannya diramalkan dengan ARIMA">ARIMA</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $item->kategori?->nama_kategori ?? '-' }}
                                        @if ($item->supplier)
                                            &middot; {{ $item->supplier->nama_supplier }}
                                        @endif
                                    </div>
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
                                <td class="px-4 py-3 text-right text-gray-600">
                                    @if ($item->jenis_barang === 'barang_jadi')
                                        <div title="Harga jual">Rp {{ number_format((float) $item->harga_jual, 0, ',', '.') }}</div>
                                    @else
                                        <div title="Harga beli">Rp {{ number_format((float) $item->harga_beli, 0, ',', '.') }}</div>
                                    @endif
                                    <div class="text-xs text-gray-400">per {{ $item->satuan }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div @class(['font-medium', 'text-red-600' => $menipis, 'text-gray-900' => ! $menipis])>
                                        {{ number_format($item->stok_tersedia, 0, ',', '.') }}
                                    </div>
                                    <div class="text-xs text-gray-400">min {{ number_format($item->stok_minimum, 0, ',', '.') }}</div>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->lead_time_hari }} hr</td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800' => $item->is_aktif,
                                        'bg-gray-100 text-gray-600' => ! $item->is_aktif,
                                    ])>
                                        {{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if (auth()->user()?->role === 'admin')
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('master.barang.edit', $item) }}"
                                               class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                            <form method="POST" action="{{ route('master.barang.destroy', $item) }}"
                                                  onsubmit="return confirm('Hapus barang {{ $item->nama_barang }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        @disabled($terpakai > 0)
                                                        @class([
                                                            'text-red-600 hover:text-red-800' => $terpakai === 0,
                                                            'text-gray-300 cursor-not-allowed' => $terpakai > 0,
                                                        ])
                                                        title="{{ $terpakai > 0 ? 'Sudah punya riwayat transaksi / analisis — nonaktifkan saja lewat Ubah' : 'Hapus barang' }}">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    Barang tidak ditemukan.
                                </td>
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
