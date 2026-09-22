<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stok Opname Baru</h2>
    </x-slot>

    <div class="w-full">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="mb-5 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Isi dengan <strong>hasil hitung fisik di gudang</strong>, bukan selisihnya. Sistem yang akan
                menghitung selisih terhadap catatan, lalu mencatatnya sebagai satu mutasi penyesuaian.
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    Periksa kembali isian berikut:
                    <ul class="list-disc list-inside mt-1">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('persediaan.opname.store') }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <x-input-label for="barang_id" value="Barang" />
                        <select id="barang_id" name="barang_id" required
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">-- pilih barang --</option>
                            @foreach ($daftarBarang as $b)
                                <option value="{{ $b->id }}" @selected((string) old('barang_id') === (string) $b->id)>
                                    {{ $b->kode_barang }} &mdash; {{ $b->nama_barang }}
                                    (catatan: {{ number_format($b->stok_tersedia, 0, ',', '.') }} {{ $b->satuan }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-1" :messages="$errors->get('barang_id')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="stok_fisik" value="Hasil Hitung Fisik" />
                            <x-text-input id="stok_fisik" name="stok_fisik" type="number" class="mt-1 block w-full"
                                          min="0" step="0.0001" required value="{{ old('stok_fisik') }}" />
                            <p class="mt-1 text-xs text-gray-500">Jumlah barang yang benar-benar ada di gudang.</p>
                            <x-input-error class="mt-1" :messages="$errors->get('stok_fisik')" />
                        </div>

                        <div>
                            <x-input-label for="tanggal" value="Tanggal Opname" />
                            <x-text-input id="tanggal" name="tanggal" type="date" class="mt-1 block w-full"
                                          required value="{{ old('tanggal', now()->format('Y-m-d')) }}" />
                            <x-input-error class="mt-1" :messages="$errors->get('tanggal')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="keterangan" value="Keterangan" />
                        <textarea id="keterangan" name="keterangan" rows="2" maxlength="500"
                                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  placeholder="Alasan selisih bila diketahui, misalnya barang rusak atau salah catat">{{ old('keterangan') }}</textarea>
                        <x-input-error class="mt-1" :messages="$errors->get('keterangan')" />
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>Simpan Penyesuaian</x-primary-button>
                    <a href="{{ route('persediaan.opname.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
