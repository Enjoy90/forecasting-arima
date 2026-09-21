<?php

namespace App\Http\Requests\Penjualan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi form faktur penjualan (header + baris barang).
 *
 * Pelanggan boleh kosong: penjualan eceran di tempat sering tidak tercatat
 * atas nama siapa pun. Kolom `nama_pelanggan_manual` menampung nama bebas
 * untuk kasus itu maupun untuk hasil import Excel yang pelanggannya belum
 * terdaftar di master.
 */
class PenjualanRequest extends FormRequest
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
        $aturan = [
            'tanggal_penjualan' => ['required', 'date', 'before_or_equal:today'],
            'pelanggan_id' => ['nullable', 'integer', Rule::exists('pelanggan', 'id')],
            'nama_pelanggan_manual' => ['nullable', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];

        // Saat mengubah faktur, hanya bagian kepala yang boleh disunting.
        // Baris barang tidak ikut divalidasi karena stoknya sudah bergerak.
        if ($this->isMethod('POST')) {
            $aturan += [
                'detail' => ['required', 'array', 'min:1'],
                'detail.*.barang_id' => ['required', 'integer', 'distinct', Rule::exists('barang', 'id')],
                'detail.*.jumlah' => ['required', 'integer', 'min:1', 'max:9999999'],
                'detail.*.harga_satuan' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            ];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tanggal_penjualan' => 'tanggal penjualan',
            'pelanggan_id' => 'pelanggan',
            'nama_pelanggan_manual' => 'nama pembeli',
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
            'detail.required' => 'Faktur penjualan harus berisi minimal satu barang.',
            'detail.min' => 'Faktur penjualan harus berisi minimal satu barang.',
            'detail.*.barang_id.distinct' => 'Barang yang sama tidak boleh ditulis dua kali dalam satu faktur.',
            'tanggal_penjualan.before_or_equal' => 'Tanggal penjualan tidak boleh di masa depan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pelanggan_id' => $this->input('pelanggan_id') ?: null,
            'nama_pelanggan_manual' => trim((string) $this->input('nama_pelanggan_manual')) ?: null,
        ]);
    }
}
