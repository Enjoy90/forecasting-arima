<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ubah Faktur {{ $penjualan->no_faktur }}</h2>
    </x-slot>

    <div class="max-w-3xl">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Hanya keterangan faktur yang dapat diubah. <strong>Baris barang tidak dapat disunting</strong>
                karena stoknya sudah bergerak saat faktur dibuat. Bila isi faktur keliru, hapus faktur ini
                (stok akan dikembalikan) lalu buat faktur baru.
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('penjualan.faktur.update', $penjualan) }}">
                @csrf
                @method('PUT')

                <div class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <x-input-label for="tanggal_penjualan" value="Tanggal Penjualan" />
                            <x-text-input id="tanggal_penjualan" name="tanggal_penjualan" type="date" class="mt-1 block w-full"
                                          required value="{{ old('tanggal_penjualan', $penjualan->tanggal_penjualan?->format('Y-m-d')) }}" />
                            <x-input-error class="mt-1" :messages="$errors->get('tanggal_penjualan')" />
                        </div>

                        <div>
                            <x-input-label for="pelanggan_id" value="Pelanggan Terdaftar" />
                            <select id="pelanggan_id" name="pelanggan_id"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">-- tanpa pelanggan terdaftar --</option>
                                @foreach ($daftarPelanggan as $p)
                                    <option value="{{ $p->id }}" @selected((string) old('pelanggan_id', $penjualan->pelanggan_id) === (string) $p->id)>
                                        {{ $p->kode_pelanggan }} &mdash; {{ $p->nama_pelanggan }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="nama_pelanggan_manual" value="Atau Nama Pembeli" />
                            <x-text-input id="nama_pelanggan_manual" name="nama_pelanggan_manual" type="text" class="mt-1 block w-full"
                                          maxlength="150" value="{{ old('nama_pelanggan_manual', $penjualan->nama_pelanggan_manual) }}" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="keterangan" value="Keterangan" />
                        <textarea id="keterangan" name="keterangan" rows="2" maxlength="500"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('keterangan', $penjualan->keterangan) }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Perbarui</x-primary-button>
                    <a href="{{ route('penjualan.faktur.show', $penjualan) }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>

        <div class="mt-4 bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900">Baris Barang (tidak dapat diubah)</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <tbody class="divide-y divide-gray-100">
                    @foreach ($penjualan->detail as $baris)
                        <tr>
                            <td class="px-6 py-3 text-gray-900">{{ $baris->barang?->nama_barang ?? '-' }}</td>
                            <td class="px-6 py-3 text-right text-gray-600 w-32">
                                {{ number_format($baris->jumlah, 0, ',', '.') }} {{ $baris->barang?->satuan }}
                            </td>
                            <td class="px-6 py-3 text-right text-gray-900 w-40">
                                Rp {{ number_format((float) $baris->subtotal, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
