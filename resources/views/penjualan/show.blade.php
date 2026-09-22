<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Faktur {{ $penjualan->no_faktur }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $penjualan->tanggal_penjualan?->format('d F Y') }} &middot; {{ $penjualan->nama_pembeli }}
                </p>
            </div>
            <a href="{{ route('penjualan.faktur.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Daftar faktur</a>
        </div>
    </x-slot>

    <div class="w-full space-y-4">
        @if (session('sukses'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('sukses') }}</div>
        @endif
        @if (session('gagal'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('gagal') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pembeli</dt>
                    <dd class="mt-1 text-gray-900">{{ $penjualan->nama_pembeli }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sumber Data</dt>
                    <dd class="mt-1 text-gray-900">{{ ucfirst($penjualan->sumber_data) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dicatat oleh</dt>
                    <dd class="mt-1 text-gray-900">{{ $penjualan->user?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</dt>
                    <dd class="mt-1 text-gray-900 font-semibold">Rp {{ number_format((float) $penjualan->total_harga, 0, ',', '.') }}</dd>
                </div>
            </dl>

            @if ($penjualan->keterangan)
                <p class="mt-4 text-sm text-gray-600 border-t border-gray-100 pt-4">{{ $penjualan->keterangan }}</p>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Barang yang Dijual</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-6 py-3 w-12">#</th>
                            <th class="px-6 py-3">Barang</th>
                            <th class="px-6 py-3 w-28 text-right">Jumlah</th>
                            <th class="px-6 py-3 w-36 text-right">Harga Satuan</th>
                            <th class="px-6 py-3 w-36 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($penjualan->detail as $i => $baris)
                            <tr>
                                <td class="px-6 py-3 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-6 py-3">
                                    <div class="text-gray-900">{{ $baris->barang?->nama_barang ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $baris->barang?->kode_barang }}</div>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-900">
                                    {{ number_format($baris->jumlah, 0, ',', '.') }}
                                    <span class="text-xs text-gray-400">{{ $baris->barang?->satuan }}</span>
                                </td>
                                <td class="px-6 py-3 text-right text-gray-600">Rp {{ number_format((float) $baris->harga_satuan, 0, ',', '.') }}</td>
                                <td class="px-6 py-3 text-right text-gray-900">Rp {{ number_format((float) $baris->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-3 text-right text-sm font-semibold text-gray-700">Total</td>
                            <td class="px-6 py-3 text-right text-base font-semibold text-gray-900">
                                Rp {{ number_format((float) $penjualan->total_harga, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center gap-4">
            @if (auth()->user()?->role === 'admin')
                <a href="{{ route('penjualan.faktur.edit', $penjualan) }}"
                   class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Ubah keterangan faktur</a>
            @endif

            <a href="{{ route('persediaan.mutasi', ['sumber' => 'penjualan']) }}"
               class="text-sm font-medium text-gray-600 hover:text-gray-900">Lihat mutasi stok</a>

            @if (auth()->user()?->role === 'admin')
                <form method="POST" action="{{ route('penjualan.faktur.destroy', $penjualan) }}" class="ms-auto"
                      onsubmit="return confirm('Hapus faktur {{ $penjualan->no_faktur }}? Stok barangnya akan dikembalikan lewat mutasi pengimbang.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Hapus faktur</button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
