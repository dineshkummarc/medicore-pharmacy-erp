<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            PharmacySeeder::class,
        ]);

        // Sample catalogue and transactions for local evaluation only.
        if (app()->environment('local') && config('pharmacy.seed.demo_data')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
