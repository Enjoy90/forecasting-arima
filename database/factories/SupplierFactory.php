<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_supplier' => 'SUP-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'nama_supplier' => 'UD '.fake()->unique()->company(),
            'telepon' => fake()->numerify('031-#######'),
            'email' => fake()->unique()->safeEmail(),
            'alamat' => fake()->address(),
            'lead_time_default' => fake()->numberBetween(3, 21),
            'is_aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['is_aktif' => false]);
    }
}
