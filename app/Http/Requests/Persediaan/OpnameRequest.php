<?php

namespace App\Http\Requests\Persediaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form stok opname.
 *
 * Yang diisi pengguna adalah HASIL HITUNG FISIK di gudang, bukan selisihnya.
 * Selisih dihitung sendiri oleh StockMutator agar tidak ada salah hitung manual.
 */
class OpnameRequest extends FormRequest
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
            'barang_id' => ['required', 'integer', Rule::exists('barang', 'id')],
            'stok_fisik' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'barang_id' => 'barang',
            'stok_fisik' => 'hasil hitung fisik',
            'tanggal' => 'tanggal opname',
            'keterangan' => 'keterangan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stok_fisik.min' => 'Hasil hitung fisik tidak boleh negatif.',
            'tanggal.before_or_equal' => 'Tanggal opname tidak boleh di masa depan.',
        ];
    }
}
