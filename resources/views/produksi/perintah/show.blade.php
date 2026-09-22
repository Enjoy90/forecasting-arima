<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $produksi->no_produksi }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $tahapan->nama_tahapan }} &middot; {{ $produksi->tanggal_produksi?->format('d F Y') }}
                </p>
            </div>
            <a href="{{ route('produksi.perintah.index', $tahapan->kode_tahapan) }}"
               class="text-sm text-gray-600 hover:text-gray-900">&larr; Daftar perintah</a>
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

        {{-- Ringkasan --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-5 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status</dt>
                    <dd class="mt-1">
                        <span @class([
                            'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                            'bg-gray-100 text-gray-700' => $produksi->status === 'draft',
                            'bg-sky-100 text-sky-800' => $produksi->status === 'proses',
                            'bg-green-100 text-green-800' => $produksi->status === 'selesai',
                            'bg-red-50 text-red-700' => $produksi->status === 'batal',
                        ])>{{ $daftarStatus[$produksi->status] ?? $produksi->status }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resep</dt>
                    <dd class="mt-1 text-gray-900">{{ $produksi->bom?->kode_bom ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Barang Hasil</dt>
                    <dd class="mt-1 text-gray-900">{{ $produksi->barangOutput?->nama_barang ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Target</dt>
                    <dd class="mt-1 text-gray-900 font-semibold">
                        {{ rtrim(rtrim(number_format((float) $produksi->jumlah_target, 2, ',', '.'), '0'), ',') }}
                        <span class="text-xs font-normal text-gray-500">{{ $produksi->barangOutput?->satuan }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hasil / Gagal</dt>
                    <dd class="mt-1 text-gray-900">
                        {{ rtrim(rtrim(number_format((float) $produksi->jumlah_hasil, 2, ',', '.'), '0'), ',') }}
                        <span class="text-gray-400">/</span>
                        <span @class(['text-red-600' => (float) $produksi->jumlah_gagal > 0])>
                            {{ rtrim(rtrim(number_format((float) $produksi->jumlah_gagal, 2, ',', '.'), '0'), ',') }}
                        </span>
                        @if ($produksi->persen_gagal > 0)
                            <span class="text-xs text-gray-500">({{ $produksi->persen_gagal }}% gagal)</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($produksi->keterangan)
                <p class="mt-4 text-sm text-gray-600 border-t border-gray-100 pt-4">{{ $produksi->keterangan }}</p>
            @endif
        </div>

        @if ($kekurangan !== [])
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">Stok bahan belum mencukupi untuk menyelesaikan perintah ini:</p>
                <ul class="list-disc list-inside mt-1">
                    @foreach ($kekurangan as $k)
                        <li>
                            <strong>{{ $k['nama'] }}</strong> kurang
                            {{ rtrim(rtrim(number_format($k['kurang'], 4, ',', '.'), '0'), ',') }} {{ $k['satuan'] }}
                            (butuh {{ rtrim(rtrim(number_format($k['dibutuhkan'], 4, ',', '.'), '0'), ',') }},
                            tersedia {{ rtrim(rtrim(number_format($k['tersedia'], 4, ',', '.'), '0'), ',') }})
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs">Pesan bahan lewat menu Pembelian, atau kurangi jumlah pakai pada realisasi.</p>
            </div>
        @endif

        {{-- Bahan --}}
        @if ($produksi->status === 'proses')
            <form method="POST" action="{{ route('produksi.perintah.realisasi', [$tahapan->kode_tahapan, $produksi]) }}">
                @csrf

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900">Realisasi Pemakaian Bahan</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Isi jumlah bahan yang benar-benar terpakai. Angka inilah yang dikurangkan dari stok
                            saat perintah diselesaikan.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <th class="px-6 py-3">Bahan</th>
                                    <th class="px-6 py-3 w-32 text-right">Rencana</th>
                                    <th class="px-6 py-3 w-36">Dipakai</th>
                                    <th class="px-6 py-3 w-28 text-right">Stok</th>
                                    <th class="px-6 py-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($produksi->bahan as $baris)
                                    <tr>
                                        <td class="px-6 py-3">
                                            <div class="text-gray-900">{{ $baris->barang?->nama_barang ?? '-' }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $baris->barang?->kode_barang }}</div>
                                        </td>
                                        <td class="px-6 py-3 text-right text-gray-600">
                                            {{ rtrim(rtrim(number_format((float) $baris->jumlah_rencana, 4, ',', '.'), '0'), ',') }}
                                            <span class="text-xs text-gray-400">{{ $baris->satuan }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="number" name="bahan[{{ $baris->id }}][jumlah_pakai]"
                                                   value="{{ old("bahan.{$baris->id}.jumlah_pakai", (float) $baris->jumlah_pakai) }}"
                                                   min="0" step="0.0001" required
                                                   class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        </td>
                                        <td class="px-6 py-3 text-right text-gray-500">
                                            {{ number_format($baris->barang?->stok_tersedia ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="text" name="bahan[{{ $baris->id }}][keterangan]"
                                                   value="{{ old("bahan.{$baris->id}.keterangan", $baris->keterangan) }}"
                                                   maxlength="255" placeholder="opsional"
                                                   class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <x-input-label for="jumlah_hasil" value="Jumlah Hasil (jadi)" />
                            <x-text-input id="jumlah_hasil" name="jumlah_hasil" type="number" class="mt-1 block w-full"
                                          min="0" step="0.01" required
                                          value="{{ old('jumlah_hasil', (float) $produksi->jumlah_hasil) }}" />
                        </div>
                        <div>
                            <x-input-label for="jumlah_gagal" value="Jumlah Gagal" />
                            <x-text-input id="jumlah_gagal" name="jumlah_gagal" type="number" class="mt-1 block w-full"
                                          min="0" step="0.01" required
                                          value="{{ old('jumlah_gagal', (float) $produksi->jumlah_gagal) }}" />
                            <p class="mt-1 text-xs text-gray-500">Tidak menambah stok.</p>
                        </div>
                        <div>
                            <x-input-label for="keterangan" value="Keterangan" />
                            <x-text-input id="keterangan" name="keterangan" type="text" class="mt-1 block w-full"
                                          maxlength="500" value="{{ old('keterangan', $produksi->keterangan) }}" />
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-200 flex items-center gap-3">
                        <x-primary-button>Simpan Realisasi</x-primary-button>
                        <span class="text-xs text-gray-500">Menyimpan realisasi belum menggerakkan stok.</span>
                    </div>
                </div>
            </form>
        @else
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ $produksi->status === 'draft' ? 'Rencana Pemakaian Bahan' : 'Pemakaian Bahan' }}
                    </h3>
                    @if ($produksi->status === 'draft')
                        <p class="text-xs text-gray-500 mt-0.5">Disalin dari resep, sudah memperhitungkan persen susut.</p>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-6 py-3 w-12">#</th>
                                <th class="px-6 py-3">Bahan</th>
                                <th class="px-6 py-3 w-32 text-right">Rencana</th>
                                <th class="px-6 py-3 w-32 text-right">Dipakai</th>
                                <th class="px-6 py-3 w-32 text-right">Selisih</th>
                                <th class="px-6 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($produksi->bahan as $i => $baris)
                                <tr>
                                    <td class="px-6 py-3 text-gray-500">{{ $i + 1 }}</td>
                                    <td class="px-6 py-3">
                                        <div class="text-gray-900">{{ $baris->barang?->nama_barang ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ $baris->barang?->kode_barang }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-600">
                                        {{ rtrim(rtrim(number_format((float) $baris->jumlah_rencana, 4, ',', '.'), '0'), ',') }}
                                        <span class="text-xs text-gray-400">{{ $baris->satuan }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right text-gray-900">
                                        {{ rtrim(rtrim(number_format((float) $baris->jumlah_pakai, 4, ',', '.'), '0'), ',') }}
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        @php $selisih = $baris->selisih; @endphp
                                        <span @class([
                                            'text-red-600' => $selisih > 0.0001,
                                            'text-green-700' => $selisih < -0.0001,
                                            'text-gray-400' => abs($selisih) <= 0.0001,
                                        ])>
                                            {{ $selisih > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($selisih, 4, ',', '.'), '0'), ',') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-600">{{ $baris->keterangan ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Tindakan --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            @if ($produksi->status === 'draft')
                <h3 class="text-sm font-semibold text-gray-900">Mulai Pekerjaan</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Setelah dimulai, perintah tidak dapat diubah lagi dan staf dapat mencatat realisasinya.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <form method="POST" action="{{ route('produksi.perintah.mulai', [$tahapan->kode_tahapan, $produksi]) }}">
                        @csrf
                        <x-primary-button>Mulai Produksi</x-primary-button>
                    </form>

                    <a href="{{ route('produksi.perintah.edit', [$tahapan->kode_tahapan, $produksi]) }}"
                       class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Ubah perintah</a>

                    <form method="POST" action="{{ route('produksi.perintah.batal', [$tahapan->kode_tahapan, $produksi]) }}"
                          onsubmit="return confirm('Batalkan perintah ini?')">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-amber-700 hover:text-amber-900">Batalkan</button>
                    </form>

                    <form method="POST" action="{{ route('produksi.perintah.destroy', [$tahapan->kode_tahapan, $produksi]) }}"
                          onsubmit="return confirm('Hapus perintah ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Hapus</button>
                    </form>
                </div>
            @elseif ($produksi->status === 'proses')
                <h3 class="text-sm font-semibold text-gray-900">Selesaikan Perintah</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Saat diselesaikan, bahan dikurangi dari stok sebanyak yang dipakai, dan barang hasil masuk
                    gudang sebanyak jumlah yang jadi. Langkah ini tidak dapat dibatalkan.
                </p>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <form method="POST" action="{{ route('produksi.perintah.selesaikan', [$tahapan->kode_tahapan, $produksi]) }}"
                          onsubmit="return confirm('Selesaikan perintah {{ $produksi->no_produksi }}? Stok bahan akan berkurang dan hasil produksi masuk gudang.')">
                        @csrf
                        <x-primary-button>Selesaikan &amp; Catat Mutasi Stok</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('produksi.perintah.batal', [$tahapan->kode_tahapan, $produksi]) }}"
                          onsubmit="return confirm('Batalkan perintah ini? Stok tidak akan tersentuh.')">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-amber-700 hover:text-amber-900">Batalkan perintah</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-gray-600">
                    Perintah berstatus <strong>{{ $daftarStatus[$produksi->status] ?? $produksi->status }}</strong> dan sudah dikunci.
                    @if ($produksi->status === 'selesai')
                        Pergerakan stoknya dapat ditelusuri di
                        <a href="{{ route('persediaan.mutasi', ['sumber' => 'produksi']) }}"
                           class="font-medium text-indigo-600 hover:text-indigo-800">Mutasi Stok</a>.
                    @endif
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
