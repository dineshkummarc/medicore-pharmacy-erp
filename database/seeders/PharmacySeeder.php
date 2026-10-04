<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Pharmacy;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Tenancy\CurrentPharmacy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the pharmacy (tenant), its system roles, defaults and first admin.
 */
class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::query()->firstOrCreate(
            ['slug' => Str::slug(config('app.name')) ?: 'pharmacy'],
            ['name' => config('app.name'), 'is_active' => true],
        );
        app(CurrentPharmacy::class)->set($pharmacy);

        $admin = Role::query()->firstOrNew(['slug' => Role::ADMIN]);
        $admin->fill(['name' => 'Administrator', 'description' => 'Full access to everything'])->forceFill(['is_system' => true])->save();

        $staff = Role::query()->firstOrNew(['slug' => Role::STAFF]);
        $staff->fill(['name' => 'Staff', 'description' => 'Counter staff: POS, customers, stock lookup'])->forceFill(['is_system' => true])->save();
        if ($staff->wasRecentlyCreated) {
            $staff->permissions()->sync(Permission::query()->whereIn('name', config('pharmacy.default_staff_permissions'))->pluck('id'));
        }

        $pharmacist = Role::query()->firstOrNew(['slug' => 'pharmacist']);
        if (! $pharmacist->exists) {
            $pharmacist->fill(['name' => 'Pharmacist', 'description' => 'Inventory, purchases, sales and returns'])->save();
            $pharmacist->permissions()->sync(Permission::query()->whereIn('name', [
                'dashboard.view', 'medicines.view', 'medicines.create', 'medicines.update', 'inventory.view', 'inventory.adjust',
                'catalog.manage', 'customers.view', 'customers.manage', 'customers.payments', 'suppliers.view',
                'purchases.view', 'purchases.create', 'sales.create', 'sales.view', 'sales.discount', 'sales.credit',
                'returns.view', 'returns.create', 'reports.view',
            ])->pluck('id'));
        }

        foreach ([['Cash', 'cash'], ['Card', 'card'], ['Bank transfer', 'bank_transfer'], ['Other', 'other']] as $i => [$name, $code]) {
            PaymentMethod::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true, 'sort_order' => $i]);
        }

        foreach (['Rent', 'Utilities', 'Salaries', 'Maintenance', 'Supplies', 'Transport', 'Marketing', 'Miscellaneous'] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach ([DocumentNumberService::INVOICE, DocumentNumberService::PURCHASE, DocumentNumberService::RETURN] as $type) {
            DocumentSequence::query()->firstOrCreate(['type' => $type], ['next_number' => 1]);
        }

        $this->createAdmin($admin);
    }

    private function createAdmin(Role $role): void
    {
        $email = config('pharmacy.seed.admin_email');

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $password = config('pharmacy.seed.admin_password');
        $generated = blank($password);
        $password = $generated ? Str::password(16) : $password;

        $user = new User(['name' => config('pharmacy.seed.admin_name'), 'email' => $email, 'password' => $password]);
        $user->role_id = $role->id;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        $this->command?->info("Admin account: {$email}");

        if ($generated) {
            $this->command?->warn("Generated admin password (shown once): {$password}");
        }
    }
}
