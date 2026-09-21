<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form tambah & ubah supplier.
 *
 * Dipakai bersama oleh store() dan update() pada SupplierController.
 * Saat update, aturan unique mengabaikan baris yang sedang diubah.
 */
class SupplierRequest extends FormRequest
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
        $supplierId = $this->route('supplier')?->id;

        return [
            'kode_supplier' => [
                'required',
                'string',
                'max:20',
                Rule::unique('supplier', 'kode_supplier')->ignore($supplierId),
            ],
            'nama_supplier' => ['required', 'string', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:500'],
            // Kolom database unsignedSmallInteger, jadi tidak boleh negatif.
            // Batas 365 hari dipakai sebagai penjaga salah ketik.
            'lead_time_default' => ['required', 'integer', 'min:0', 'max:365'],
            'is_aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode_supplier' => 'kode supplier',
            'nama_supplier' => 'nama supplier',
            'telepon' => 'telepon',
            'email' => 'email',
            'alamat' => 'alamat',
            'lead_time_default' => 'lead time default',
            'is_aktif' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_supplier.unique' => 'Kode supplier :input sudah dipakai supplier lain.',
            'lead_time_default.min' => 'Lead time tidak boleh bernilai negatif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_supplier' => strtoupper(trim((string) $this->input('kode_supplier'))),
            'nama_supplier' => trim((string) $this->input('nama_supplier')),
            // Checkbox tidak terkirim saat tidak dicentang, jadi perlu dinormalkan.
            'is_aktif' => $this->boolean('is_aktif'),
        ]);
    }
}
