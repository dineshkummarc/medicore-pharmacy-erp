<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 0.5, 20);

        return [
            'name' => ucfirst(fake()->unique()->word()).'zol',
            'generic_name' => fake()->word(),
            'strength' => fake()->randomElement(['250mg', '500mg', '10mg', '5ml']),
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'barcode' => fake()->unique()->ean13(),
            'unit' => 'tablet',
            'purchase_price' => $cost,
            'selling_price' => round($cost * 1.4, 2),
            'tax_rate' => 0,
            'min_stock_level' => 10,
            'is_active' => true,
        ];
    }
}
