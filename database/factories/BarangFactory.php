<?php

namespace Database\Factories;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barang>
 */
class BarangFactory extends Factory
{
    protected $model = Barang::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_barang' => 'BB-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'nama_barang' => 'Bahan '.fake()->unique()->word(),
            'jenis_barang' => Barang::JENIS_BAHAN_BAKU,
            'kategori_id' => Kategori::factory(),
            'supplier_id' => Supplier::factory(),
            'satuan' => 'Pcs',
            'harga_beli' => fake()->numberBetween(1000, 200000),
            'harga_jual' => 0,
            'stok_tersedia' => fake()->numberBetween(50, 500),
            'stok_minimum' => fake()->numberBetween(10, 40),
            'lead_time_hari' => fake()->numberBetween(3, 21),
            'service_level' => 95,
            'is_diramalkan' => false,
            'is_aktif' => true,
        ];
    }

    public function setengahJadi(): static
    {
        return $this->state(fn (array $attributes) => [
            'kode_barang' => 'SJ-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'jenis_barang' => Barang::JENIS_SETENGAH_JADI,
            'supplier_id' => null,
            'lead_time_hari' => 0,
        ]);
    }

    public function barangJadi(): static
    {
        return $this->state(fn (array $attributes) => [
            'kode_barang' => 'BJ-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'jenis_barang' => Barang::JENIS_BARANG_JADI,
            'supplier_id' => null,
            'harga_jual' => fake()->numberBetween(50000, 150000),
            'lead_time_hari' => 0,
            'is_diramalkan' => true,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['is_aktif' => false]);
    }
}
