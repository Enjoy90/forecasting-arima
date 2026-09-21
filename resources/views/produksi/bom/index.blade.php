<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">BOM / Komposisi</h2>
                <p class="text-sm text-gray-500 mt-0.5">Resep bahan untuk tiap barang pada tiap tahapan produksi.</p>
            </div>
            <a href="{{ route('produksi.bom.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Resep Baru
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif
        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('produksi.bom.index') }}" class="flex flex-wrap items-center gap-2">
                    <x-text-input type="text" name="cari" value="{{ $cari }}" class="w-full sm:w-56 text-sm"
                                  placeholder="Cari kode atau nama resep" />

                    <select name="tahapan" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($tahapanId === 'semua')>Semua tahapan</option>
                        @foreach ($daftarTahapan as $t)
                            <option value="{{ $t->id }}" @selected((string) $tahapanId === (string) $t->id)>{{ $t->nama_tahapan }}</option>
                        @endforeach
                    </select>

                    <select name="status" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="semua" @selected($status === 'semua')>Semua status</option>
                        <option value="aktif" @selected($status === 'aktif')>Hanya aktif</option>
                        <option value="nonaktif" @selected($status === 'nonaktif')>Hanya nonaktif</option>
                    </select>

                    <x-primary-button>Cari</x-primary-button>

                    @if ($cari !== '' || $tahapanId !== 'semua' || $status !== 'semua')
                        <a href="{{ route('produksi.bom.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-28">Kode</th>
                            <th class="px-4 py-3">Resep</th>
                            <th class="px-4 py-3">Tahapan</th>
                            <th class="px-4 py-3">Menghasilkan</th>
                            <th class="px-4 py-3 w-24 text-center">Komponen</th>
                            <th class="px-4 py-3 w-24 text-center">Dipakai</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                            <th class="px-4 py-3 w-32 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($bom as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('produksi.bom.show', $item) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-800">{{ $item->kode_bom }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-900">{{ $item->nama_bom }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->tahapan?->nama_tahapan ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->barang?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ rtrim(rtrim(number_format((float) $item->jumlah_output, 4, ',', '.'), '0'), ',') }}
                                        {{ $item->barang?->satuan }} per resep
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->detail_count }}</td>
                                <td class="px-4 py-3 text-center text-gray-600">
                                    {{ $item->produksi_count > 0 ? $item->produksi_count : '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800' => $item->is_aktif,
                                        'bg-gray-100 text-gray-600' => ! $item->is_aktif,
                                    ])>{{ $item->is_aktif ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('produksi.bom.edit', $item) }}" class="text-indigo-600 hover:text-indigo-800">Ubah</a>

                                        <form method="POST" action="{{ route('produksi.bom.destroy', $item) }}"
                                              onsubmit="return confirm('Hapus resep {{ $item->nama_bom }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" @disabled($item->produksi_count > 0)
                                                    @class([
                                                        'text-red-600 hover:text-red-800' => $item->produksi_count === 0,
                                                        'text-gray-300 cursor-not-allowed' => $item->produksi_count > 0,
                                                    ])
                                                    title="{{ $item->produksi_count > 0 ? 'Sudah dipakai perintah produksi — nonaktifkan saja' : 'Hapus resep' }}">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada resep BOM.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($bom->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">{{ $bom->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
