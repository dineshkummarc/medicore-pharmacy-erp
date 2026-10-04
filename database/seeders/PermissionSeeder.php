<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Syncs the permission catalogue in config/pharmacy.php to the database.
 * Safe to re-run after adding permissions.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [];

        foreach (config('pharmacy.permissions') as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::query()->updateOrCreate(['name' => $name], ['group' => $group, 'label' => $label]);
                $names[] = $name;
            }
        }

        Permission::query()->whereNotIn('name', $names)->delete();
    }
}
