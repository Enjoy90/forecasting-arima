<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form tambah & ubah tahapan produksi.
 *
 * Angka pada form ini adalah bahan perhitungan waktu tunggu operasional
 * (docs/01-alur-kerja-sistem.md bagian 4), jadi validasinya sengaja ketat:
 * tidak boleh negatif, dan `urutan` tidak boleh kembar supaya rantai tahapan
 * tetap punya susunan yang jelas.
 */
class TahapanProduksiRequest extends FormRequest
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
        $tahapanId = $this->route('tahapan_produksi')?->id;

        return [
            'kode_tahapan' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tahapan_produksi', 'kode_tahapan')->ignore($tahapanId),
            ],
            'nama_tahapan' => ['required', 'string', 'max:100'],
            // Kolom unsignedTinyInteger, jadi rentangnya 1-255.
            // Keunikan ditegakkan di sini karena migration belum memberi
            // constraint unique pada kolom ini.
            'urutan' => [
                'required',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('tahapan_produksi', 'urutan')->ignore($tahapanId),
            ],
            // decimal(8,2) - boleh pecahan, misalnya 0.5 hari.
            'waktu_proses_hari' => ['required', 'numeric', 'min:0', 'max:365'],
            // decimal(15,2) - nilai 0 berarti kapasitas tidak dibatasi.
            'kapasitas_per_hari' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kode_tahapan' => 'kode tahapan',
            'nama_tahapan' => 'nama tahapan',
            'urutan' => 'urutan',
            'waktu_proses_hari' => 'waktu proses',
            'kapasitas_per_hari' => 'kapasitas per hari',
            'deskripsi' => 'deskripsi',
            'is_aktif' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_tahapan.unique' => 'Kode tahapan :input sudah dipakai tahapan lain.',
            'urutan.unique' => 'Urutan :input sudah dipakai tahapan lain. Setiap tahapan harus punya urutan sendiri.',
            'urutan.min' => 'Urutan dimulai dari 1.',
            'waktu_proses_hari.min' => 'Waktu proses tidak boleh bernilai negatif.',
            'kapasitas_per_hari.min' => 'Kapasitas per hari tidak boleh bernilai negatif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode_tahapan' => strtoupper(trim((string) $this->input('kode_tahapan'))),
            'nama_tahapan' => trim((string) $this->input('nama_tahapan')),
            // Checkbox tidak terkirim saat tidak dicentang, jadi perlu dinormalkan.
            'is_aktif' => $this->boolean('is_aktif'),
        ]);
    }
}
