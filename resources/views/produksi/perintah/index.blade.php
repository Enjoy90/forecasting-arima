<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $tahapan->nama_tahapan }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Tahapan {{ $tahapan->urutan }} &middot; {{ $tahapan->kode_tahapan }} &middot;
                    waktu proses {{ rtrim(rtrim(number_format((float) $tahapan->waktu_proses_hari, 2, ',', '.'), '0'), ',') }} hari
                </p>
            </div>
            <a href="{{ route('produksi.perintah.create', $tahapan->kode_tahapan) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                + Perintah Baru
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        @if (session('sukses'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif
        @if (session('gagal'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif

        @if ($jumlahBerjalan > 0 && $status !== 'proses')
            <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 flex items-center justify-between gap-4">
                <span><strong>{{ $jumlahBerjalan }} perintah</strong> sedang berjalan dan menunggu realisasi.</span>
                <a href="{{ route('produksi.perintah.index', [$tahapan->kode_tahapan, 'status' => 'proses']) }}"
                   class="shrink-0 font-medium underline hover:no-underline">Lihat</a>
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-4 py-4 border-b border-gray-200">
                <form method="GET" action="{{ route('produksi.perintah.index', $tahapan->kode_tahapan) }}"
                      class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select id="status" name="status" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="semua" @selected($status === 'semua')>Semua</option>
                            @foreach ($daftarStatus as $nilai => $label)
                                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
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

                    @if ($status !== 'semua' || $dari || $sampai)
                        <a href="{{ route('produksi.perintah.index', $tahapan->kode_tahapan) }}"
                           class="text-sm text-gray-500 hover:text-gray-700 pb-2">Reset</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 w-48">No. Perintah</th>
                            <th class="px-4 py-3 w-28">Tanggal</th>
                            <th class="px-4 py-3">Barang Hasil</th>
                            <th class="px-4 py-3 w-24 text-right">Target</th>
                            <th class="px-4 py-3 w-24 text-right">Hasil</th>
                            <th class="px-4 py-3 w-24 text-right">Gagal</th>
                            <th class="px-4 py-3 w-24 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($produksi as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('produksi.perintah.show', [$tahapan->kode_tahapan, $item]) }}"
                                       class="font-medium text-indigo-600 hover:text-indigo-800">{{ $item->no_produksi }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item->tanggal_produksi?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $item->barangOutput?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $item->bom?->kode_bom }} &middot; {{ $item->bahan_count }} bahan</div>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-900">
                                    {{ rtrim(rtrim(number_format((float) $item->jumlah_target, 2, ',', '.'), '0'), ',') }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-900">
                                    {{ rtrim(rtrim(number_format((float) $item->jumlah_hasil, 2, ',', '.'), '0'), ',') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span @class(['text-red-600' => (float) $item->jumlah_gagal > 0, 'text-gray-400' => (float) $item->jumlah_gagal == 0])>
                                        {{ rtrim(rtrim(number_format((float) $item->jumlah_gagal, 2, ',', '.'), '0'), ',') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span @class([
                                        'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-gray-100 text-gray-700' => $item->status === 'draft',
                                        'bg-sky-100 text-sky-800' => $item->status === 'proses',
                                        'bg-green-100 text-green-800' => $item->status === 'selesai',
                                        'bg-red-50 text-red-700' => $item->status === 'batal',
                                    ])>{{ $daftarStatus[$item->status] ?? $item->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Belum ada perintah produksi pada tahapan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($produksi->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">{{ $produksi->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
