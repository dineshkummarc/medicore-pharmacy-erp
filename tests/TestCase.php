<?php

namespace Tests;

use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PharmacySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pharmacy.seed.admin_password' => 'Secret-Passw0rd', 'pharmacy.seed.admin_email' => 'admin@pharmacy.test']);
        $this->seed([PermissionSeeder::class, PharmacySeeder::class]);
        $this->withoutVite();
    }

    protected function admin(): User
    {
        return User::query()->where('email', 'admin@pharmacy.test')->firstOrFail();
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function staffWith(array $permissions = []): User
    {
        return User::factory()->withPermissions($permissions)->create();
    }

    protected function cash(): PaymentMethod
    {
        return PaymentMethod::query()->where('code', 'cash')->firstOrFail();
    }

    /**
     * A medicine with one batch of stock.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function medicineWithStock(int $quantity = 100, ?string $expiry = null, string $cost = '1.00', array $attributes = []): Medicine
    {
        $medicine = Medicine::factory()->create(array_merge(['purchase_price' => $cost, 'selling_price' => '2.00'], $attributes));

        if ($quantity > 0) {
            $this->addBatch($medicine, $quantity, $expiry ?? now()->addYear()->toDateString(), $cost);
        }

        return $medicine->refresh();
    }

    protected function addBatch(Medicine $medicine, int $quantity, string $expiry, string $cost = '1.00', ?string $number = null): Batch
    {
        return app(InventoryService::class)->receive($medicine, [
            'batch_number' => $number ?? 'B-'.fake()->unique()->numerify('#####'),
            'expiry_date' => $expiry,
            'purchase_price' => $cost,
            'selling_price' => $medicine->selling_price,
            'quantity' => $quantity,
        ], StockMovementType::Opening);
    }

    protected function supplier(): Supplier
    {
        return Supplier::factory()->create();
    }

    /**
     * Cached stock must always equal the sum of batches and of the ledger.
     */
    protected function assertStockConsistent(Medicine $medicine): void
    {
        $medicine->refresh();
        $this->assertSame((int) $medicine->batches()->sum('quantity'), $medicine->stock_quantity, 'stock_quantity != SUM(batches)');
        $this->assertSame((int) $medicine->stockMovements()->sum('quantity'), $medicine->stock_quantity, 'stock_quantity != SUM(movements)');
        $this->assertSame(0, $medicine->batches()->where('quantity', '<', 0)->count(), 'negative batch');
    }
}
