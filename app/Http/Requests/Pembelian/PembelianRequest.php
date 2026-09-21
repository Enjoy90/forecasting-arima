<?php

namespace App\Http\Requests\Pembelian;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form order pembelian (header + baris barang).
 *
 * `no_pembelian` tidak divalidasi di sini karena dibuat otomatis oleh
 * controller — nomor dokumen bukan isian bebas pengguna.
 *
 * `subtotal` juga tidak diambil dari form. Nilainya dihitung ulang di server
 * dari jumlah x harga_satuan, supaya angka pada nota tidak bisa dikirimi
 * nilai yang tidak konsisten dari luar.
 */
class PembelianRequest extends FormRequest
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
        return [
            'tanggal_pembelian' => ['required', 'date', 'before_or_equal:today'],
            'supplier_id' => ['required', 'integer', Rule::exists('supplier', 'id')],
            'keterangan' => ['nullable', 'string', 'max:500'],

            'detail' => ['required', 'array', 'min:1'],
            // Satu barang hanya boleh muncul sekali dalam satu order, supaya
            // tidak ada dua baris yang saling bertentangan pada nota yang sama.
            'detail.*.barang_id' => ['required', 'integer', 'distinct', Rule::exists('barang', 'id')],
            'detail.*.jumlah' => ['required', 'integer', 'min:1', 'max:9999999'],
            'detail.*.harga_satuan' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tanggal_pembelian' => 'tanggal pembelian',
            'supplier_id' => 'supplier',
            'detail' => 'daftar barang',
            'detail.*.barang_id' => 'barang',
            'detail.*.jumlah' => 'jumlah',
            'detail.*.harga_satuan' => 'harga satuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detail.required' => 'Order pembelian harus berisi minimal satu barang.',
            'detail.min' => 'Order pembelian harus berisi minimal satu barang.',
            'detail.*.barang_id.distinct' => 'Barang yang sama tidak boleh ditulis dua kali dalam satu order.',
            'detail.*.jumlah.min' => 'Jumlah pesan minimal 1.',
            'tanggal_pembelian.before_or_equal' => 'Tanggal pembelian tidak boleh di masa depan.',
        ];
    }
}
