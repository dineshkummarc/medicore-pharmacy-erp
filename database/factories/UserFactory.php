<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'role_id' => fn () => Role::query()->where('slug', Role::STAFF)->value('id') ?? Role::factory(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role_id' => Role::query()->where('slug', Role::ADMIN)->value('id')]);
    }

    /**
     * A user whose role has exactly the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(function () use ($permissions) {
            $role = Role::factory()->create();
            $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));

            return ['role_id' => $role->id];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
