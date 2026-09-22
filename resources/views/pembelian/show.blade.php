<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Order {{ $pembelian->no_pembelian }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $pembelian->tanggal_pembelian?->format('d F Y') }}
                    &middot; {{ $pembelian->supplier?->nama_supplier ?? 'Tanpa supplier' }}
                </p>
            </div>
            <a href="{{ route('pembelian.order.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Daftar order</a>
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

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-8 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span @class([
                                'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                'bg-amber-100 text-amber-800' => $pembelian->status === 'dipesan',
                                'bg-green-100 text-green-800' => $pembelian->status === 'diterima',
                                'bg-gray-100 text-gray-600' => $pembelian->status === 'batal',
                            ])>{{ $daftarStatus[$pembelian->status] ?? $pembelian->status }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal Terima</dt>
                        <dd class="mt-1 text-gray-900">{{ $pembelian->tanggal_terima?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Dibuat oleh</dt>
                        <dd class="mt-1 text-gray-900">{{ $pembelian->user?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</dt>
                        <dd class="mt-1 text-gray-900 font-semibold">Rp {{ number_format((float) $pembelian->total_harga, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </div>

            @if ($pembelian->keterangan)
                <p class="mt-4 text-sm text-gray-600 border-t border-gray-100 pt-4">{{ $pembelian->keterangan }}</p>
            @endif
        </div>

        {{-- Baris barang --}}
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Barang yang Dipesan</h3>
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
                        @foreach ($pembelian->detail as $i => $baris)
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
                                Rp {{ number_format((float) $pembelian->total_harga, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Tindakan: mengubah status/data order, hanya admin & gudang (pimpinan cuma lihat). --}}
        @if ($pembelian->status === 'dipesan' && in_array(auth()->user()?->role, ['admin', 'gudang'], true))
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-900">Penerimaan Barang</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Saat barang sudah sampai di gudang, catat penerimaannya di sini. Stok seluruh barang pada
                    order ini akan bertambah dan tercatat di mutasi stok. Setelah diterima, order tidak dapat
                    diubah lagi.
                </p>

                <form method="POST" action="{{ route('pembelian.order.terima', $pembelian) }}"
                      class="mt-4 flex flex-wrap items-end gap-3"
                      onsubmit="return confirm('Catat penerimaan order {{ $pembelian->no_pembelian }}? Stok akan bertambah dan order tidak dapat diubah lagi.')">
                    @csrf
                    <div>
                        <x-input-label for="tanggal_terima" value="Tanggal Terima" />
                        <x-text-input id="tanggal_terima" name="tanggal_terima" type="date" class="mt-1 block"
                                      required value="{{ old('tanggal_terima', now()->format('Y-m-d')) }}" />
                    </div>
                    <x-primary-button class="mb-0.5">Terima Barang</x-primary-button>
                </form>

                <div class="mt-6 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-4">
                    <a href="{{ route('pembelian.order.edit', $pembelian) }}"
                       class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Ubah order</a>

                    <form method="POST" action="{{ route('pembelian.order.batal', $pembelian) }}"
                          onsubmit="return confirm('Batalkan order ini?')">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-amber-700 hover:text-amber-900">Batalkan order</button>
                    </form>

                    <form method="POST" action="{{ route('pembelian.order.destroy', $pembelian) }}"
                          onsubmit="return confirm('Hapus order ini beserta seluruh barisnya?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Hapus order</button>
                    </form>
                </div>
            </div>
        @elseif ($pembelian->status !== 'dipesan')
            <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Order berstatus <strong>{{ $daftarStatus[$pembelian->status] ?? $pembelian->status }}</strong> dan sudah dikunci.
                @if ($pembelian->status === 'diterima')
                    Perubahan stok dari order ini dapat dilihat di
                    <a href="{{ route('persediaan.mutasi', ['sumber' => 'pembelian']) }}"
                       class="font-medium text-indigo-600 hover:text-indigo-800">Mutasi Stok</a>.
                @endif
            </div>
        @else
            <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Order ini masih berstatus <strong>{{ $daftarStatus[$pembelian->status] ?? $pembelian->status }}</strong>.
                Menerima, mengubah, atau membatalkan order adalah wewenang admin dan gudang.
            </div>
        @endif
    </div>
</x-app-layout>
