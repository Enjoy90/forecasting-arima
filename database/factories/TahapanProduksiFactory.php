<?php

namespace Database\Factories;

use App\Models\TahapanProduksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahapanProduksi>
 */
class TahapanProduksiFactory extends Factory
{
    protected $model = TahapanProduksi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $urutan = fake()->unique()->numberBetween(1, 255);

        return [
            'kode_tahapan' => 'TP-'.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT),
            'nama_tahapan' => 'Tahapan '.fake()->unique()->word(),
            'urutan' => $urutan,
            'waktu_proses_hari' => fake()->randomFloat(2, 0.5, 5),
            'kapasitas_per_hari' => fake()->numberBetween(100, 300),
            'deskripsi' => fake()->sentence(),
            'is_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['is_aktif' => false]);
    }
}
