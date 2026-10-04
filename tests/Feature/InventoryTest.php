<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Medicine;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    public function test_admin_can_create_a_medicine(): void
    {
        $this->actingAs($this->admin())->post('/medicines', [
            'name' => 'Paracetamol', 'generic_name' => 'Acetaminophen', 'strength' => '500mg',
            'sku' => 'para-500', 'barcode' => '8901234567890', 'unit' => 'tablet',
            'purchase_price' => '0.05', 'selling_price' => '0.10', 'tax_rate' => '0', 'min_stock_level' => 50,
            'is_active' => 1,
            'stock_quantity' => 9999, // not fillable, must be ignored
        ])->assertRedirect();

        $medicine = Medicine::query()->where('sku', 'PARA-500')->firstOrFail();
        $this->assertSame(0, $medicine->stock_quantity);
        $this->assertDatabaseHas('activity_logs', ['action' => 'medicine.created', 'subject_id' => $medicine->id]);
    }

    public function test_medicine_validation_rejects_bad_input(): void
    {
        Medicine::factory()->create(['sku' => 'DUP-1']);

        $this->actingAs($this->admin())->post('/medicines', [
            'name' => '', 'sku' => 'dup-1', 'unit' => 'spaceship',
            'purchase_price' => '5', 'selling_price' => '1', 'tax_rate' => '150', 'min_stock_level' => -1,
        ])->assertSessionHasErrors(['name', 'sku', 'unit', 'selling_price', 'tax_rate', 'min_stock_level']);
    }

    public function test_html_in_names_is_escaped_on_output(): void
    {
        $medicine = Medicine::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin())->get(route('medicines.show', $medicine))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_opening_stock_creates_batch_and_movement(): void
    {
        $medicine = Medicine::factory()->create();

        $this->actingAs($this->admin())->post(route('batches.store', $medicine), [
            'batch_number' => 'OPEN-1', 'expiry_date' => now()->addYear()->toDateString(),
            'quantity' => 40, 'purchase_price' => '1.50',
        ])->assertRedirect(route('medicines.show', $medicine));

        $this->assertSame(40, $medicine->refresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['medicine_id' => $medicine->id, 'type' => 'opening', 'quantity' => 40, 'balance_after' => 40]);
        $this->assertStockConsistent($medicine);
    }

    public function test_stock_adjustment_requires_reason_and_cannot_go_negative(): void
    {
        $medicine = $this->medicineWithStock(10);
        $batch = $medicine->batches()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('batches.adjust', $batch), ['direction' => 'remove', 'quantity' => 3])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('batches.adjust', $batch), ['direction' => 'remove', 'quantity' => 11, 'reason' => 'Count'])
            ->assertSessionHas('error');
        $this->assertSame(10, $medicine->refresh()->stock_quantity);

        $this->actingAs($admin)->post(route('batches.adjust', $batch), ['direction' => 'remove', 'quantity' => 3, 'reason' => 'Damaged'])
            ->assertSessionHas('success');
        $this->assertSame(7, $medicine->refresh()->stock_quantity);
        $this->assertDatabaseHas('activity_logs', ['action' => 'inventory.adjusted']);
        $this->assertStockConsistent($medicine);
    }

    public function test_staff_without_permission_cannot_adjust_stock(): void
    {
        $medicine = $this->medicineWithStock(10);
        $batch = $medicine->batches()->first();

        $this->actingAs($this->staffWith(['medicines.view', 'inventory.view']))
            ->post(route('batches.adjust', $batch), ['direction' => 'add', 'quantity' => 100, 'reason' => 'free stock'])
            ->assertForbidden();
        $this->assertSame(10, $medicine->refresh()->stock_quantity);
    }

    public function test_fefo_takes_the_earliest_expiring_batch_first(): void
    {
        $medicine = Medicine::factory()->create();
        $late = $this->addBatch($medicine, 10, now()->addMonths(12)->toDateString(), '1.00', 'LATE');
        $early = $this->addBatch($medicine, 5, now()->addMonths(2)->toDateString(), '1.00', 'EARLY');

        $allocations = app(InventoryService::class)->deductFefo($medicine->refresh(), 8, $this->admin());

        $this->assertSame('EARLY', $allocations[0]['batch']->batch_number);
        $this->assertSame(5, $allocations[0]['quantity']);
        $this->assertSame('LATE', $allocations[1]['batch']->batch_number);
        $this->assertSame(3, $allocations[1]['quantity']);
        $this->assertSame(0, $early->refresh()->quantity);
        $this->assertSame(7, $late->refresh()->quantity);
    }

    public function test_expired_stock_is_never_allocated(): void
    {
        $medicine = Medicine::factory()->create();
        $this->addBatch($medicine, 50, now()->subDay()->toDateString(), '1.00', 'EXPIRED');
        $this->addBatch($medicine, 2, now()->addYear()->toDateString(), '1.00', 'GOOD');

        $this->assertSame(52, $medicine->refresh()->stock_quantity);
        $this->assertSame(2, app(InventoryService::class)->sellableQuantity($medicine));

        $this->expectException(BusinessRuleException::class);
        app(InventoryService::class)->deductFefo($medicine, 3, $this->admin());
    }

    public function test_only_expired_batches_can_be_written_off(): void
    {
        $medicine = Medicine::factory()->create();
        $good = $this->addBatch($medicine, 5, now()->addYear()->toDateString());
        $expired = $this->addBatch($medicine, 7, now()->subDays(3)->toDateString());

        $this->actingAs($this->admin())->post(route('batches.write-off', $good))->assertSessionHas('error');
        $this->actingAs($this->admin())->post(route('batches.write-off', $expired))->assertSessionHas('success');

        $this->assertSame(0, $expired->refresh()->quantity);
        $this->assertSame(5, $medicine->refresh()->stock_quantity);
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::ExpiredWriteOff)->count());
        $this->assertStockConsistent($medicine);
    }

    public function test_archiving_keeps_history_and_can_be_restored(): void
    {
        $medicine = $this->medicineWithStock(5);

        $this->actingAs($this->admin())->delete(route('medicines.destroy', $medicine))->assertRedirect();
        $this->assertSoftDeleted($medicine);
        $this->assertSame(1, StockMovement::query()->where('medicine_id', $medicine->id)->count());

        $this->actingAs($this->admin())->post(route('medicines.restore', $medicine))->assertRedirect();
        $this->assertNotSoftDeleted($medicine);
    }

    public function test_low_stock_scope_uses_medicine_level_or_store_default(): void
    {
        $this->medicineWithStock(5, null, '1.00', ['min_stock_level' => 10]);   // low (own level)
        $this->medicineWithStock(50, null, '1.00', ['min_stock_level' => 10]);  // fine
        $this->medicineWithStock(8, null, '1.00', ['min_stock_level' => 0]);    // low (store default 10)

        $this->assertSame(2, Medicine::query()->lowStock()->count());
    }

    public function test_medicine_index_search_filter_and_export(): void
    {
        $this->medicineWithStock(5, null, '1.00', ['name' => 'Amoxicillin', 'sku' => 'AMX-1']);
        $this->medicineWithStock(500, null, '1.00', ['name' => 'Ibuprofen', 'sku' => 'IBU-1']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/medicines?q=amox')->assertOk()->assertSee('Amoxicillin')->assertDontSee('Ibuprofen');
        $this->actingAs($admin)->get('/medicines?stock=low')->assertOk()->assertSee('Amoxicillin')->assertDontSee('Ibuprofen');
        $this->actingAs($admin)->get('/medicines?sort=stock&direction=asc')->assertOk();
        $this->actingAs($admin)->get('/medicines?sort=password;drop table&direction=sideways')->assertOk(); // whitelisted

        $csv = $this->actingAs($admin)->get('/medicines/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('AMX-1', $csv);
    }
}
