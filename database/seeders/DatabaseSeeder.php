<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan penting: master data harus ada sebelum BOM dan transaksi.
     *
     * Jalankan dengan:
     *   php artisan migrate:fresh --seed
     *
     * Kedua anggota tim WAJIB memakai perintah yang sama agar isi database
     * persis sama, sehingga hasil peramalan dan simulasi bisa dibandingkan.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MasterDataSeeder::class,
            TahapanProduksiSeeder::class,
            BarangSeeder::class,
            BomSeeder::class,
            PenjualanHistorisSeeder::class,
        ]);
    }
}
