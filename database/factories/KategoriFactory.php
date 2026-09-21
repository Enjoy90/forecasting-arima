<?php

namespace Database\Factories;

use App\Models\Kategori;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kategori>
 */
class KategoriFactory extends Factory
{
    protected $model = Kategori::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_kategori' => 'KTG-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'nama_kategori' => 'Kategori '.fake()->unique()->word(),
            'keterangan' => fake()->sentence(),
        ];
    }
}
