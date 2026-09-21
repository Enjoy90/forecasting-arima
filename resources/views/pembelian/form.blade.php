{{--
    Isi form order pembelian, dipakai bersama create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $pembelian       instance Pembelian (kosong saat tambah)
      $baris           array baris barang untuk nilai awal
      $daftarSupplier  koleksi Supplier aktif
      $daftarBarang    koleksi Barang aktif

    Baris barang dikelola Alpine.js: tambah/hapus baris dan hitung subtotal
    berjalan di sisi pengguna, tetapi seluruh angka dihitung ulang di server
    saat disimpan.
--}}

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

<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <x-input-label for="tanggal_pembelian" value="Tanggal Pembelian" />
            <x-text-input id="tanggal_pembelian" name="tanggal_pembelian" type="date" class="mt-1 block w-full"
                          required
                          value="{{ old('tanggal_pembelian', optional($pembelian->tanggal_pembelian)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('tanggal_pembelian')" />
        </div>

        <div>
            <x-input-label for="supplier_id" value="Supplier" />
            <select id="supplier_id" name="supplier_id" required
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">-- pilih supplier --</option>
                @foreach ($daftarSupplier as $sup)
                    <option value="{{ $sup->id }}"
                        @selected((string) old('supplier_id', $pembelian->supplier_id) === (string) $sup->id)>
                        {{ $sup->kode_supplier }} &mdash; {{ $sup->nama_supplier }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-1" :messages="$errors->get('supplier_id')" />
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    <div x-data="formPembelian({{ Js::from(array_values($baris)) }}, {{ Js::from($daftarBarang) }})">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-sm font-semibold text-gray-900">Barang yang Dipesan</h3>
            <button type="button" @click="tambahBaris()"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Tambah baris</button>
        </div>

        <x-input-error class="mt-2" :messages="$errors->get('detail')" />

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">Barang</th>
                        <th class="px-3 py-2 w-28">Jumlah</th>
                        <th class="px-3 py-2 w-40">Harga Satuan</th>
                        <th class="px-3 py-2 w-40 text-right">Subtotal</th>
                        <th class="px-3 py-2 w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(b, i) in baris" :key="i">
                        <tr>
                            <td class="px-3 py-2">
                                <select :name="`detail[${i}][barang_id]`" x-model="b.barang_id"
                                        @change="pakaiHargaBawaan(i)" required
                                        class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">-- pilih barang --</option>
                                    <template x-for="brg in daftarBarang" :key="brg.id">
                                        <option :value="brg.id"
                                                x-text="`${brg.kode_barang} — ${brg.nama_barang} (${brg.satuan})`"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="`detail[${i}][jumlah]`" x-model.number="b.jumlah"
                                       min="1" required
                                       class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="`detail[${i}][harga_satuan]`" x-model.number="b.harga_satuan"
                                       min="0" step="0.01" required
                                       class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </td>
                            <td class="px-3 py-2 text-right text-gray-700 whitespace-nowrap"
                                x-text="rupiah(b.jumlah * b.harga_satuan)"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="hapusBaris(i)" x-show="baris.length > 1"
                                        class="text-red-600 hover:text-red-800" title="Hapus baris">&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200">
                        <td colspan="3" class="px-3 py-3 text-right text-sm font-semibold text-gray-700">Total</td>
                        <td class="px-3 py-3 text-right text-base font-semibold text-gray-900" x-text="rupiah(total())"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div>
        <x-input-label for="keterangan" value="Keterangan" />
        <textarea id="keterangan" name="keterangan" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Catatan order (opsional)">{{ old('keterangan', $pembelian->keterangan) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('keterangan')" />
    </div>
</div>

@push('skrip')
<script>
    function formPembelian(barisAwal, daftarBarang) {
        return {
            baris: barisAwal.length ? barisAwal : [{ barang_id: '', jumlah: 1, harga_satuan: 0 }],
            daftarBarang: daftarBarang,

            tambahBaris() {
                this.baris.push({ barang_id: '', jumlah: 1, harga_satuan: 0 });
            },

            hapusBaris(i) {
                this.baris.splice(i, 1);
            },

            // Harga beli terakhir pada master barang dipakai sebagai nilai awal,
            // tetapi tetap boleh diubah karena harga supplier bisa berbeda.
            pakaiHargaBawaan(i) {
                const brg = this.daftarBarang.find(b => String(b.id) === String(this.baris[i].barang_id));
                if (brg && !this.baris[i].harga_satuan) {
                    this.baris[i].harga_satuan = Number(brg.harga_beli);
                }
            },

            total() {
                return this.baris.reduce((t, b) => t + (Number(b.jumlah) || 0) * (Number(b.harga_satuan) || 0), 0);
            },

            rupiah(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
            },
        };
    }
</script>
@endpush
