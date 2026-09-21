<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mutasi Stok</h2>
            <p class="text-sm text-gray-500 mt-0.5">Kartu stok: seluruh riwayat keluar-masuk barang beserta asal-usulnya.</p>
        </div>
    </x-slot>

    <div class="max-w-7xl">
        <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            Catatan mutasi tidak dapat diubah maupun dihapus. Bila ada angka yang keliru, perbaiki lewat
            <a href="{{ route('persediaan.opname.create') }}" class="font-medium text-indigo-600 hover:text-indigo-800">Stok Opname</a>
            agar koreksinya ikut tercatat.
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('persediaan.mutasi') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="barang" class="block text-xs font-medium text-gray-600 mb-1">Barang</label>
                        <select id="barang" name="barang"
                                class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($barangId === 'semua')>Semua barang</option>
                            @foreach ($daftarBarang as $b)
                                <option value="{{ $b->id }}" @selected((string) $barangId === (string) $b->id)>
                                    {{ $b->kode_barang }} &mdash; {{ $b->nama_barang }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="jenis" class="block text-xs font-medium text-gray-600 mb-1">Jenis</label>
                        <select id="jenis" name="jenis"
                                class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($jenis === 'semua')>Semua</option>
                            @foreach ($daftarJenis as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($jenis === $nilai)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sumber" class="block text-xs font-medium text-gray-600 mb-1">Sumber</label>
                        <select id="sumber" name="sumber"
                                class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($sumber === 'semua')>Semua</option>
                            @foreach ($daftarSumber as $s)
                                <option value="{{ $s }}" @selected($sumber === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="dari" class="block text-xs font-medium text-gray-600 mb-1">Dari</label>
                        <x-text-input id="dari" type="date" name="dari" value="{{ $dari }}" class="text-sm" />
                    </div>

                    <div>
                        <label for="sampai" class="block text-xs font-medium text-gray-600 mb-1">Sampai</label>
                        <x-text-input id="sampai" type="date" name="sampai" value="{{ $sampai }}" class="text-sm" />
                    </div>

                    <x-primary-button>Saring</x-primary-button>

                    @if ($barangId !== 'semua' || $jenis !== 'semua' || $sumber !== 'semua' || $dari || $sampai)
                        <a href="{{ route('persediaan.mutasi') }}" class="text-sm text-gray-500 hover:text-gray-700 pb-2">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-28">Tanggal</th>
                            <th class="px-4 py-3">Barang</th>
                            <th class="px-4 py-3 w-28">Jenis</th>
                            <th class="px-4 py-3 w-24">Sumber</th>
                            <th class="px-4 py-3 w-24 text-right">Jumlah</th>
                            <th class="px-4 py-3 w-32 text-right">Stok</th>
                            <th class="px-4 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($mutasi as $item)
                            @php $nilai = (float) $item->jumlah; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $item->tanggal?->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->barang?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->barang?->kode_barang }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800' => $item->jenis_mutasi === 'masuk',
                                        'bg-red-100 text-red-800' => $item->jenis_mutasi === 'keluar',
                                        'bg-amber-100 text-amber-800' => $item->jenis_mutasi === 'penyesuaian',
                                    ])>
                                        {{ $daftarJenis[$item->jenis_mutasi] ?? $item->jenis_mutasi }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ ucfirst($item->sumber) }}</td>
                                <td class="px-4 py-3 text-right font-medium whitespace-nowrap">
                                    @if ($item->jenis_mutasi === 'masuk')
                                        <span class="text-green-700">+{{ number_format($nilai, 0, ',', '.') }}</span>
                                    @elseif ($item->jenis_mutasi === 'keluar')
                                        <span class="text-red-700">&minus;{{ number_format($nilai, 0, ',', '.') }}</span>
                                    @else
                                        <span @class(['text-green-700' => $nilai > 0, 'text-red-700' => $nilai < 0])>
                                            {{ $nilai > 0 ? '+' : '' }}{{ number_format($nilai, 0, ',', '.') }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400">{{ $item->barang?->satuan }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">
                                    {{ number_format((float) $item->stok_awal, 0, ',', '.') }}
                                    <span class="text-gray-400">&rarr;</span>
                                    <span class="font-medium text-gray-900">{{ number_format((float) $item->stok_akhir, 0, ',', '.') }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <div>{{ $item->keterangan ?: '-' }}</div>
                                    @if ($item->user)
                                        <div class="text-xs text-gray-400 mt-0.5">oleh {{ $item->user->name }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada mutasi stok yang cocok dengan penyaring ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mutasi->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $mutasi->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
