<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' role';

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'is_system' => false];
    }
}
