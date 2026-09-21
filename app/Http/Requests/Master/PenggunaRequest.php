<?php

namespace App\Http\Requests\Master;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi form pengguna sistem.
 *
 * Kata sandi wajib saat menambah pengguna baru, tetapi opsional saat mengubah:
 * mengosongkannya berarti sandi lama tetap dipakai. Dengan begitu admin dapat
 * membetulkan nama atau role tanpa harus mengetahui sandi orang lain.
 */
class PenggunaRequest extends FormRequest
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
        $penggunaId = $this->route('pengguna')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($penggunaId),
            ],
            'password' => [
                $penggunaId ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
            'role' => ['required', Rule::in(array_keys(self::ROLE))],
            'is_aktif' => ['required', 'boolean'],
        ];
    }

    /**
     * Role yang tersedia beserta penjelasan singkat hak aksesnya,
     * sesuai docs/01-alur-kerja-sistem.md bagian 1.2.
     *
     * @var array<string, string>
     */
    public const ROLE = [
        User::ROLE_ADMIN => 'Admin — seluruh master data & transaksi, kelola pengguna',
        User::ROLE_PRODUKSI => 'Staf Produksi — BOM, perintah produksi, realisasi bahan',
        User::ROLE_GUDANG => 'Staf Gudang — persediaan, mutasi stok, penerimaan pembelian',
        User::ROLE_PIMPINAN => 'Pimpinan — peramalan, simulasi, persetujuan, seluruh laporan',
    ];

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pengguna = $this->route('pengguna');

            if (! $pengguna) {
                return;
            }

            // Admin tidak boleh mengunci dirinya sendiri di luar sistem, baik
            // dengan menonaktifkan akunnya maupun menurunkan rolenya sendiri.
            if ($pengguna->id === auth()->id()) {
                if (! $this->boolean('is_aktif')) {
                    $validator->errors()->add('is_aktif', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
                }

                if ($this->input('role') !== User::ROLE_ADMIN) {
                    $validator->errors()->add('role', 'Anda tidak dapat mengubah role akun Anda sendiri dari admin.');
                }
            }

            // Sistem harus selalu punya minimal satu admin aktif, kalau tidak
            // master data dan pengelolaan pengguna jadi tidak bisa diakses siapa pun.
            $masihAdmin = $this->input('role') === User::ROLE_ADMIN && $this->boolean('is_aktif');

            if ($pengguna->role === User::ROLE_ADMIN && $pengguna->is_aktif && ! $masihAdmin) {
                $jumlahAdminLain = User::query()
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_aktif', true)
                    ->whereKeyNot($pengguna->id)
                    ->count();

                if ($jumlahAdminLain === 0) {
                    $validator->errors()->add('role', 'Ini satu-satunya admin aktif. Angkat admin lain lebih dulu sebelum mengubahnya.');
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
            'name' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
            'role' => 'role',
            'is_aktif' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email :input sudah dipakai pengguna lain.',
            'password.confirmed' => 'Ulangan kata sandi tidak cocok.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'is_aktif' => $this->boolean('is_aktif'),
        ]);
    }
}
