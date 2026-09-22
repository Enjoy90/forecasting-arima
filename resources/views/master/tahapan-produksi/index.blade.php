<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tahapan Produksi</h2>
                <p class="text-sm text-gray-500 mt-0.5">Waktu proses &amp; kapasitas tiap tahapan pembuatan sekop.</p>
            </div>
            <a href="{{ route('master.tahapan-produksi.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Tambah Tahapan
            </a>
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

        {{-- Ringkasan ini adalah angka yang dipakai Modul B sebagai L_produksi.
             Ditampilkan supaya salah ketik pada waktu proses langsung terlihat. --}}
        <div class="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Waktu Produksi</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ rtrim(rtrim(number_format($totalWaktuProses, 2, ',', '.'), '0'), ',') }}
                    <span class="text-base font-normal text-gray-500">hari</span>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Jumlah waktu proses {{ $jumlahTahapanAktif }} tahapan aktif (L<sub>produksi</sub>)
                </p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Kapasitas Tersempit</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    @if ($kapasitasTersempit)
                        {{ number_format((float) $kapasitasTersempit, 0, ',', '.') }}
                        <span class="text-base font-normal text-gray-500">unit/hari</span>
                    @else
                        <span class="text-base font-normal text-gray-500">Tidak dibatasi</span>
                    @endif
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ $namaTersempit ? 'Penentu laju rantai: '.$namaTersempit : 'Belum ada tahapan berkapasitas' }}
                </p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Catatan</p>
                <p class="mt-1 text-sm text-gray-600 leading-relaxed">
                    Sistem menjumlahkan seluruh tahapan secara berurutan. Bila di lapangan ada tahapan yang
                    dikerjakan paralel, angka ini lebih besar dari kenyataan &mdash; sengaja konservatif.
                </p>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('master.tahapan-produksi.index') }}" class="flex flex-wrap items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-64 text-sm"
                                  placeholder="Cari kode atau nama tahapan" />

                    <select name="status"
                            class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($status === 'semua')>Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Hanya aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Hanya nonaktif</option>
                    </select>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $status !== 'semua')
                        <a href="{{ route('master.tahapan-produksi.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-16 text-center">Urutan</th>
                            <th class="px-4 py-3 w-24">Kode</th>
                            <th class="px-4 py-3">Nama Tahapan</th>
                            <th class="px-4 py-3 w-28 text-center">Waktu Proses</th>
                            <th class="px-4 py-3 w-32 text-center">Kapasitas/Hari</th>
                            <th class="px-4 py-3 w-28 text-center">Dipakai</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                            <th class="px-4 py-3 w-36 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tahapan as $item)
                            @php $terpakai = $item->bom_count + $item->produksi_count; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-100 text-gray-700 font-medium">
                                        {{ $item->urutan }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $item->kode_tahapan }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->nama_tahapan }}</div>
                                    @if ($item->deskripsi)
                                        <div class="text-xs text-gray-500 mt-0.5">{{ Str::limit($item->deskripsi, 70) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-gray-900">
                                    {{ rtrim(rtrim(number_format((float) $item->waktu_proses_hari, 2, ',', '.'), '0'), ',') }} hari
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    @if ((float) $item->kapasitas_per_hari > 0)
                                        {{ number_format((float) $item->kapasitas_per_hari, 0, ',', '.') }} unit
                                    @else
                                        <span class="text-gray-400" title="Kapasitas tidak dibatasi, tidak menambah hari">tidak dibatasi</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    @if ($terpakai === 0)
                                        <span class="text-gray-400">-</span>
                                    @else
                                        <span title="{{ $item->bom_count }} BOM, {{ $item->produksi_count }} perintah produksi">
                                            {{ $item->bom_count }} BOM / {{ $item->produksi_count }} prod
                                        </span>
                                    @endif
                                </td>
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
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('master.tahapan-produksi.edit', $item) }}"
                                           class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                        <form method="POST" action="{{ route('master.tahapan-produksi.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus tahapan {{ $item->nama_tahapan }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    @disabled($terpakai > 0)
                                                    @class([
                                                        'text-red-600 hover:text-red-800' => $terpakai === 0,
                                                        'text-gray-300 cursor-not-allowed' => $terpakai > 0,
                                                    ])
                                                    title="{{ $terpakai > 0 ? 'Masih dipakai BOM / produksi — nonaktifkan saja lewat Ubah' : 'Hapus tahapan' }}">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    {{ $cari !== '' || $status !== 'semua' ? 'Tahapan tidak ditemukan.' : 'Belum ada tahapan produksi.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tahapan->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $tahapan->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
