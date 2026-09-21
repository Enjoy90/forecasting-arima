<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form tambah & ubah kategori barang.
 *
 * Dipakai bersama oleh store() dan update() pada KategoriController.
 * Saat update, aturan unique mengabaikan baris yang sedang diubah.
 */
class KategoriRequest extends FormRequest
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
        $kategoriId = $this->route('kategori')?->id;

        return [
            'kode_kategori' => [
                'required',
                'string',
                'max:20',
                Rule::unique('kategori', 'kode_kategori')->ignore($kategoriId),
            ],
            'nama_kategori' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode_kategori' => 'kode kategori',
            'nama_kategori' => 'nama kategori',
            'keterangan' => 'keterangan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_kategori.unique' => 'Kode kategori :input sudah dipakai kategori lain.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_kategori' => strtoupper(trim((string) $this->input('kode_kategori'))),
            'nama_kategori' => trim((string) $this->input('nama_kategori')),
        ]);
    }
}
