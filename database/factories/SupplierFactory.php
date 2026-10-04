<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('+1 555 ####'),
            'email' => fake()->companyEmail(),
            'is_active' => true,
        ];
    }
}
