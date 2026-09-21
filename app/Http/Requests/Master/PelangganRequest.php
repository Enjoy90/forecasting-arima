<?php

namespace App\Http\Requests\Master;

use App\Http\Controllers\Master\PelangganController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form tambah & ubah pelanggan.
 *
 * Dipakai bersama oleh store() dan update() pada PelangganController.
 * Saat update, aturan unique mengabaikan baris yang sedang diubah.
 */
class PelangganRequest extends FormRequest
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
        $pelangganId = $this->route('pelanggan')?->id;

        return [
            'kode_pelanggan' => [
                'required',
                'string',
                'max:20',
                Rule::unique('pelanggan', 'kode_pelanggan')->ignore($pelangganId),
            ],
            'nama_pelanggan' => ['required', 'string', 'max:150'],
            // Harus cocok dengan enum pada migration pelanggan.
            'jenis' => ['required', Rule::in(array_keys(PelangganController::JENIS))],
            'telepon' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'kota' => ['nullable', 'string', 'max:100'],
            'is_aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode_pelanggan' => 'kode pelanggan',
            'nama_pelanggan' => 'nama pelanggan',
            'jenis' => 'jenis pelanggan',
            'telepon' => 'telepon',
            'email' => 'email',
            'alamat' => 'alamat',
            'kota' => 'kota',
            'is_aktif' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_pelanggan.unique' => 'Kode pelanggan :input sudah dipakai pelanggan lain.',
            'jenis.in' => 'Jenis pelanggan harus salah satu dari: toko, distributor, perorangan, atau instansi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_pelanggan' => strtoupper(trim((string) $this->input('kode_pelanggan'))),
            'nama_pelanggan' => trim((string) $this->input('nama_pelanggan')),
            'kota' => trim((string) $this->input('kota')) ?: null,
            // Checkbox tidak terkirim saat tidak dicentang, jadi perlu dinormalkan.
            'is_aktif' => $this->boolean('is_aktif'),
        ]);
    }
}
