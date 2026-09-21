<?php

namespace App\Http\Requests\Produksi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pencatatan realisasi produksi.
 *
 * Diisi staf produksi setelah pekerjaan berjalan: berapa bahan yang benar-benar
 * terpakai, berapa unit yang jadi, dan berapa yang gagal. Angka-angka inilah
 * yang kemudian diterjemahkan menjadi mutasi stok saat perintah diselesaikan.
 */
class RealisasiProduksiRequest extends FormRequest
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
            'jumlah_hasil' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'jumlah_gagal' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'keterangan' => ['nullable', 'string', 'max:500'],

            'bahan' => ['required', 'array', 'min:1'],
            // Kunci larik adalah id baris detail_produksi_bahan.
            'bahan.*.jumlah_pakai' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'bahan.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'jumlah_hasil' => 'jumlah hasil',
            'jumlah_gagal' => 'jumlah gagal',
            'bahan' => 'pemakaian bahan',
            'bahan.*.jumlah_pakai' => 'jumlah pakai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'jumlah_hasil.min' => 'Jumlah hasil tidak boleh negatif.',
            'jumlah_gagal.min' => 'Jumlah gagal tidak boleh negatif.',
            'bahan.*.jumlah_pakai.min' => 'Jumlah pakai tidak boleh negatif.',
        ];
    }
}
