<?php

namespace App\Http\Requests\Produksi;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form BOM (resep komposisi bahan).
 *
 * Satu aturan di sini menjaga arti data, bukan sekadar bentuk isian:
 * komponen tidak boleh sama dengan barang yang dihasilkan. Resep yang
 * memakai dirinya sendiri sebagai bahan akan membuat ledak BOM berputar
 * tanpa henti saat Modul B menghitung kebutuhan bahan.
 */
class BomRequest extends FormRequest
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
        $bomId = $this->route('bom')?->id;

        return [
            'kode_bom' => [
                'required',
                'string',
                'max:30',
                Rule::unique('bom', 'kode_bom')->ignore($bomId),
            ],
            'nama_bom' => ['required', 'string', 'max:150'],
            'barang_id' => ['required', 'integer', Rule::exists('barang', 'id')],
            'tahapan_id' => ['required', 'integer', Rule::exists('tahapan_produksi', 'id')],
            'jumlah_output' => ['required', 'numeric', 'min:0.0001', 'max:9999999'],
            'is_aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],

            'detail' => ['required', 'array', 'min:1'],
            'detail.*.barang_id' => ['required', 'integer', 'distinct', Rule::exists('barang', 'id')],
            'detail.*.jumlah_kebutuhan' => ['required', 'numeric', 'min:0.0001', 'max:9999999'],
            'detail.*.persen_susut' => ['required', 'numeric', 'min:0', 'max:100'],
            'detail.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $output = (int) $this->input('barang_id');

            foreach ((array) $this->input('detail', []) as $i => $baris) {
                if ((int) ($baris['barang_id'] ?? 0) === $output && $output > 0) {
                    $validator->errors()->add(
                        "detail.{$i}.barang_id",
                        'Komponen tidak boleh sama dengan barang yang dihasilkan resep ini.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode_bom' => 'kode BOM',
            'nama_bom' => 'nama BOM',
            'barang_id' => 'barang yang dihasilkan',
            'tahapan_id' => 'tahapan produksi',
            'jumlah_output' => 'jumlah output',
            'is_aktif' => 'status',
            'detail' => 'daftar komponen',
            'detail.*.barang_id' => 'komponen',
            'detail.*.jumlah_kebutuhan' => 'jumlah kebutuhan',
            'detail.*.persen_susut' => 'persen susut',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_bom.unique' => 'Kode BOM :input sudah dipakai resep lain.',
            'detail.required' => 'Resep harus berisi minimal satu komponen.',
            'detail.min' => 'Resep harus berisi minimal satu komponen.',
            'detail.*.barang_id.distinct' => 'Komponen yang sama tidak boleh ditulis dua kali dalam satu resep.',
            'jumlah_output.min' => 'Jumlah output harus lebih besar dari nol.',
            'detail.*.jumlah_kebutuhan.min' => 'Jumlah kebutuhan harus lebih besar dari nol.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_bom' => strtoupper(trim((string) $this->input('kode_bom'))),
            'nama_bom' => trim((string) $this->input('nama_bom')),
            'is_aktif' => $this->boolean('is_aktif'),
        ]);
    }
}
