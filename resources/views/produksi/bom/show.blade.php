<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $bom->kode_bom }} &mdash; {{ $bom->nama_bom }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $bom->tahapan?->nama_tahapan }} &middot; menghasilkan {{ $bom->barang?->nama_barang }}
                </p>
            </div>
            <a href="{{ route('produksi.bom.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Daftar resep</a>
        </div>
    </x-slot>

    <div class="w-full space-y-4">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tahapan</dt>
                    <dd class="mt-1 text-gray-900">{{ $bom->tahapan?->nama_tahapan ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Menghasilkan</dt>
                    <dd class="mt-1 text-gray-900">
                        {{ rtrim(rtrim(number_format((float) $bom->jumlah_output, 4, ',', '.'), '0'), ',') }}
                        {{ $bom->barang?->satuan }} {{ $bom->barang?->nama_barang }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dipakai Perintah</dt>
                    <dd class="mt-1 text-gray-900">{{ $bom->produksi_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</dt>
                    <dd class="mt-1">
                        <span @class([
                            'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                            'bg-green-100 text-green-800' => $bom->is_aktif,
                            'bg-gray-100 text-gray-600' => ! $bom->is_aktif,
                        ])>{{ $bom->is_aktif ? 'Aktif' : 'Nonaktif' }}</span>
                    </dd>
                </div>
            </dl>

            @if ($bom->keterangan)
                <p class="mt-4 text-sm text-gray-600 border-t border-gray-100 pt-4">{{ $bom->keterangan }}</p>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Komponen Bahan</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Kebutuhan untuk menghasilkan
                    {{ rtrim(rtrim(number_format((float) $bom->jumlah_output, 4, ',', '.'), '0'), ',') }}
                    {{ $bom->barang?->satuan }}.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-6 py-3 w-12">#</th>
                            <th class="px-6 py-3">Komponen</th>
                            <th class="px-6 py-3 w-32 text-right">Kebutuhan</th>
                            <th class="px-6 py-3 w-24 text-right">Susut</th>
                            <th class="px-6 py-3 w-32 text-right">Efektif</th>
                            <th class="px-6 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($bom->detail as $i => $baris)
                            @php
                                $efektif = (float) $baris->jumlah_kebutuhan * (1 + (float) $baris->persen_susut / 100);
                            @endphp
                            <tr>
                                <td class="px-6 py-3 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-6 py-3">
                                    <div class="text-gray-900">{{ $baris->barang?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $baris->barang?->kode_barang }}</div>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-600">
                                    {{ rtrim(rtrim(number_format((float) $baris->jumlah_kebutuhan, 4, ',', '.'), '0'), ',') }}
                                    <span class="text-xs text-gray-400">{{ $baris->satuan }}</span>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-600">
                                    {{ rtrim(rtrim(number_format((float) $baris->persen_susut, 2, ',', '.'), '0'), ',') }}%
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900">
                                    {{ rtrim(rtrim(number_format($efektif, 4, ',', '.'), '0'), ',') }}
                                    <span class="text-xs text-gray-400">{{ $baris->satuan }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $baris->keterangan ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center gap-4">
            <a href="{{ route('produksi.bom.edit', $bom) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Ubah resep</a>

            @if ($bom->tahapan)
                <a href="{{ route('produksi.perintah.index', $bom->tahapan->kode_tahapan) }}"
                   class="text-sm font-medium text-gray-600 hover:text-gray-900">
                    Perintah produksi {{ $bom->tahapan->nama_tahapan }}
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
