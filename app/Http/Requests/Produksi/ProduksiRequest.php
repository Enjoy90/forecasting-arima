<?php

namespace App\Http\Requests\Produksi;

use App\Models\Bom;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pembuatan & perubahan perintah produksi (bagian rencana).
 *
 * Realisasi (jumlah_pakai, jumlah_hasil, jumlah_gagal) divalidasi terpisah
 * oleh RealisasiProduksiRequest, karena diisi pada tahap yang berbeda.
 */
class ProduksiRequest extends FormRequest
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
            'tanggal_produksi' => ['required', 'date', 'before_or_equal:today'],
            'bom_id' => ['required', 'integer', Rule::exists('bom', 'id')],
            'jumlah_target' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bom = Bom::find($this->input('bom_id'));

            if (! $bom) {
                return;
            }

            // BOM menentukan tahapan sekaligus barang hasilnya, jadi resep dari
            // tahapan lain tidak boleh dipakai di menu tahapan ini.
            $tahapan = $this->route('tahapan');

            if ($tahapan && $bom->tahapan_id !== $tahapan->id) {
                $validator->errors()->add(
                    'bom_id',
                    "Resep {$bom->kode_bom} milik tahapan lain, tidak dapat dipakai pada tahapan {$tahapan->nama_tahapan}."
                );
            }

            if (! $bom->is_aktif) {
                $validator->errors()->add('bom_id', "Resep {$bom->kode_bom} sudah nonaktif.");
            }

            if ($bom->detail()->count() === 0) {
                $validator->errors()->add('bom_id', "Resep {$bom->kode_bom} belum punya komponen bahan.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tanggal_produksi' => 'tanggal produksi',
            'bom_id' => 'resep BOM',
            'jumlah_target' => 'jumlah target',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_produksi.before_or_equal' => 'Tanggal produksi tidak boleh di masa depan.',
            'jumlah_target.min' => 'Jumlah target harus lebih besar dari nol.',
        ];
    }
}
