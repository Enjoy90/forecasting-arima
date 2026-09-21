{{--
    Isi form BOM, dipakai bersama create.blade.php dan edit.blade.php.

    Variabel yang diharapkan:
      $bom             instance Bom (kosong saat tambah)
      $baris           array komponen untuk nilai awal
      $daftarTahapan   koleksi TahapanProduksi aktif
      $daftarBarang    koleksi Barang aktif
      $kodeUsulan      nilai awal kode saat tambah (opsional)
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
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <x-input-label for="kode_bom" value="Kode BOM" />
            <x-text-input id="kode_bom" name="kode_bom" type="text" class="mt-1 block w-full"
                          maxlength="30" required autofocus
                          value="{{ old('kode_bom', $bom->kode_bom ?? ($kodeUsulan ?? '')) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('kode_bom')" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="nama_bom" value="Nama Resep" />
            <x-text-input id="nama_bom" name="nama_bom" type="text" class="mt-1 block w-full"
                          maxlength="150" required placeholder="Resep Kepala Sekop"
                          value="{{ old('nama_bom', $bom->nama_bom) }}" />
            <x-input-error class="mt-1" :messages="$errors->get('nama_bom')" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <x-input-label for="tahapan_id" value="Tahapan Produksi" />
            <select id="tahapan_id" name="tahapan_id" required
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">-- pilih tahapan --</option>
                @foreach ($daftarTahapan as $t)
                    <option value="{{ $t->id }}" @selected((string) old('tahapan_id', $bom->tahapan_id) === (string) $t->id)>
                        {{ $t->kode_tahapan }} &mdash; {{ $t->nama_tahapan }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-1" :messages="$errors->get('tahapan_id')" />
        </div>

        <div>
            <x-input-label for="barang_id" value="Barang yang Dihasilkan" />
            <select id="barang_id" name="barang_id" required
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="">-- pilih barang --</option>
                @foreach ($daftarBarang as $b)
                    <option value="{{ $b->id }}" @selected((string) old('barang_id', $bom->barang_id) === (string) $b->id)>
                        {{ $b->kode_barang }} &mdash; {{ $b->nama_barang }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-1" :messages="$errors->get('barang_id')" />
        </div>

        <div>
            <x-input-label for="jumlah_output" value="Jumlah Output per Resep" />
            <x-text-input id="jumlah_output" name="jumlah_output" type="number" class="mt-1 block w-full"
                          min="0.0001" step="0.0001" required
                          value="{{ old('jumlah_output', $bom->jumlah_output ?? 1) }}" />
            <p class="mt-1 text-xs text-gray-500">Basis perhitungan: komponen di bawah adalah kebutuhan untuk sebanyak ini unit.</p>
            <x-input-error class="mt-1" :messages="$errors->get('jumlah_output')" />
        </div>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    <div x-data="formBom({{ Js::from(array_values($baris)) }}, {{ Js::from($daftarBarang) }})">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Komponen Bahan</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Komponen tidak boleh sama dengan barang yang dihasilkan resep ini.
                </p>
            </div>
            <button type="button" @click="tambahBaris()"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Tambah komponen</button>
        </div>

        <x-input-error class="mt-2" :messages="$errors->get('detail')" />

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-3 py-2">Komponen</th>
                        <th class="px-3 py-2 w-32">Kebutuhan</th>
                        <th class="px-3 py-2 w-28">Susut (%)</th>
                        <th class="px-3 py-2 w-36 text-right">Efektif</th>
                        <th class="px-3 py-2">Keterangan</th>
                        <th class="px-3 py-2 w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(b, i) in baris" :key="i">
                        <tr>
                            <td class="px-3 py-2">
                                <select :name="`detail[${i}][barang_id]`" x-model="b.barang_id" required
                                        class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">-- pilih komponen --</option>
                                    <template x-for="brg in daftarBarang" :key="brg.id">
                                        <option :value="brg.id"
                                                x-text="`${brg.kode_barang} — ${brg.nama_barang} (${brg.satuan})`"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="`detail[${i}][jumlah_kebutuhan]`" x-model.number="b.jumlah_kebutuhan"
                                       min="0.0001" step="0.0001" required
                                       class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="`detail[${i}][persen_susut]`" x-model.number="b.persen_susut"
                                       min="0" max="100" step="0.01" required
                                       class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </td>
                            <td class="px-3 py-2 text-right text-gray-700 whitespace-nowrap" x-text="efektif(i)"></td>
                            <td class="px-3 py-2">
                                <input type="text" :name="`detail[${i}][keterangan]`" x-model="b.keterangan"
                                       maxlength="255" placeholder="opsional"
                                       class="block w-full text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="hapusBaris(i)" x-show="baris.length > 1"
                                        class="text-red-600 hover:text-red-800" title="Hapus komponen">&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p class="mt-2 text-xs text-gray-500">
            Kolom <strong>Efektif</strong> adalah kebutuhan setelah ditambah susut
            (kebutuhan &times; (1 + susut/100)) &mdash; itulah angka yang dipakai saat resep diledakkan.
        </p>
    </div>

    <div>
        <x-input-label for="keterangan" value="Keterangan Resep" />
        <textarea id="keterangan" name="keterangan" rows="2" maxlength="500"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                  placeholder="Catatan resep (opsional)">{{ old('keterangan', $bom->keterangan) }}</textarea>
        <x-input-error class="mt-1" :messages="$errors->get('keterangan')" />
    </div>

    <div>
        <label for="is_aktif" class="inline-flex items-center">
            <input type="hidden" name="is_aktif" value="0">
            <input id="is_aktif" type="checkbox" name="is_aktif" value="1"
                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                   @checked(old('is_aktif', $bom->is_aktif ?? true))>
            <span class="ms-2 text-sm text-gray-700">Resep aktif</span>
        </label>
        <p class="mt-1 text-xs text-gray-500">Resep nonaktif tidak muncul saat membuat perintah produksi baru.</p>
    </div>
</div>

@push('skrip')
<script>
    function formBom(barisAwal, daftarBarang) {
        const kosong = { barang_id: '', jumlah_kebutuhan: 1, persen_susut: 0, keterangan: '' };

        return {
            baris: barisAwal.length ? barisAwal : [{ ...kosong }],
            daftarBarang: daftarBarang,

            tambahBaris() { this.baris.push({ ...kosong }); },
            hapusBaris(i) { this.baris.splice(i, 1); },

            efektif(i) {
                const b = this.baris[i];
                const nilai = (Number(b.jumlah_kebutuhan) || 0) * (1 + (Number(b.persen_susut) || 0) / 100);
                const brg = this.daftarBarang.find(x => String(x.id) === String(b.barang_id));

                return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(nilai)
                    + (brg ? ' ' + brg.satuan : '');
            },
        };
    }
</script>
@endpush
