<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Empat akun, satu per role. Dipakai untuk pengujian hak akses
     * pada bab pengujian skripsi.
     *
     * Password semua akun: password
     */
    public function run(): void
    {
        $akun = [
            ['Administrator',  'admin@pande.test',    User::ROLE_ADMIN],
            ['Staf Produksi',  'produksi@pande.test', User::ROLE_PRODUKSI],
            ['Staf Gudang',    'gudang@pande.test',   User::ROLE_GUDANG],
            ['Pimpinan',       'pimpinan@pande.test', User::ROLE_PIMPINAN],
        ];

        foreach ($akun as [$nama, $email, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nama,
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'is_aktif' => true,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
