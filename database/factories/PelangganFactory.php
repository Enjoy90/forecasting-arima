<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggan>
 */
class PelangganFactory extends Factory
{
    protected $model = Pelanggan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_pelanggan' => 'PLG-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'nama_pelanggan' => 'Toko '.fake()->unique()->company(),
            'jenis' => fake()->randomElement(['toko', 'distributor', 'perorangan', 'instansi']),
            'telepon' => fake()->numerify('0321-######'),
            'email' => fake()->unique()->safeEmail(),
            'alamat' => fake()->address(),
            'kota' => fake()->randomElement(['Mojokerto', 'Jombang', 'Sidoarjo', 'Malang', 'Surabaya']),
            'is_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['is_aktif' => false]);
    }
}
