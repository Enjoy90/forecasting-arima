<?php

namespace App\Http\Requests\Master;

use App\Http\Controllers\Master\BarangController;
use App\Models\Barang;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form tambah & ubah barang.
 *
 * Dua aturan di sini bukan sekadar penjaga isian, tapi menjaga arti data:
 *
 *   1. `is_diramalkan` hanya boleh untuk barang jadi. ARIMA meramalkan
 *      penjualan barang jadi; menandai bahan baku sebagai "diramalkan" akan
 *      membuat Modul B mengolah deret yang tidak punya makna penjualan.
 *   2. `supplier_id` wajib untuk bahan baku, karena bahan bakulah yang dibeli
 *      dari pemasok dan menjadi sumber L_beli pada perhitungan waktu tunggu.
 *
 * `stok_tersedia` sengaja TIDAK ada di sini. Stok hanya boleh berubah lewat
 * mutasi stok (lihat Tahap 3 pada docs/05-roadmap-modul-a.md).
 */
class BarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $barangId = $this->route('barang')?->id;

        return [
            'kode_barang' => [
                'required',
                'string',
                'max:30',
                Rule::unique('barang', 'kode_barang')->ignore($barangId),
            ],
            'nama_barang' => ['required', 'string', 'max:150'],
            'jenis_barang' => ['required', Rule::in(array_keys(BarangController::JENIS))],
            'kategori_id' => ['required', 'integer', Rule::exists('kategori', 'id')],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier', 'id'),
                Rule::requiredIf(fn () => $this->input('jenis_barang') === Barang::JENIS_BAHAN_BAKU),
            ],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_beli' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'stok_minimum' => ['required', 'integer', 'min:0'],
            // unsignedSmallInteger, dipakai Modul B sebagai L_beli.
            'lead_time_hari' => ['required', 'integer', 'min:0', 'max:365'],
            // decimal(5,2), persen. Menentukan nilai Z pada rumus safety stock.
            'service_level' => ['required', 'numeric', 'min:50', 'max:99.99'],
            'is_diramalkan' => ['required', 'boolean'],
            'is_aktif' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('is_diramalkan') && $this->input('jenis_barang') !== Barang::JENIS_BARANG_JADI) {
                $validator->errors()->add(
                    'is_diramalkan',
                    'Hanya barang jadi yang penjualannya dapat diramalkan. Ubah jenis barang menjadi Barang Jadi, atau hapus centang ini.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode_barang' => 'kode barang',
            'nama_barang' => 'nama barang',
            'jenis_barang' => 'jenis barang',
            'kategori_id' => 'kategori',
            'supplier_id' => 'supplier',
            'satuan' => 'satuan',
            'harga_beli' => 'harga beli',
            'harga_jual' => 'harga jual',
            'stok_minimum' => 'stok minimum',
            'lead_time_hari' => 'lead time',
            'service_level' => 'service level',
            'is_diramalkan' => 'penanda diramalkan',
            'is_aktif' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_barang.unique' => 'Kode barang :input sudah dipakai barang lain.',
            'supplier_id.required' => 'Bahan baku harus punya supplier, karena lead time pembeliannya dipakai menghitung waktu tunggu.',
            'service_level.min' => 'Service level di bawah 50% tidak masuk akal untuk perencanaan stok.',
            'lead_time_hari.min' => 'Lead time tidak boleh bernilai negatif.',
            'stok_minimum.min' => 'Stok minimum tidak boleh bernilai negatif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_barang' => strtoupper(trim((string) $this->input('kode_barang'))),
            'nama_barang' => trim((string) $this->input('nama_barang')),
            'supplier_id' => $this->input('supplier_id') ?: null,
            // Checkbox tidak terkirim saat tidak dicentang, jadi perlu dinormalkan.
            'is_diramalkan' => $this->boolean('is_diramalkan'),
            'is_aktif' => $this->boolean('is_aktif'),
        ]);
    }
}
