<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Supplier;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(Supplier $supplier, Medicine $medicine, array $overrides = []): array
    {
        return array_replace_recursive([
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'SUP-77',
            'purchase_date' => today()->toDateString(),
            'paid_amount' => '30.00',
            'payment_method_id' => $this->cash()->id,
            'items' => [[
                'medicine_id' => $medicine->id,
                'batch_number' => 'LOT-1',
                'expiry_date' => now()->addYear()->toDateString(),
                'quantity' => 100,
                'unit_cost' => '1.00',
                'selling_price' => '1.80',
                'discount' => '10.00',
                'tax_rate' => '5',
            ]],
        ], $overrides);
    }

    public function test_purchase_receives_stock_and_updates_supplier_balance(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();

        $this->actingAs($this->admin())->post('/purchases', $this->payload($supplier, $medicine))->assertRedirect();

        $purchase = Purchase::query()->with('items')->firstOrFail();
        // 100 × 1.00 = 100.00, −10.00 discount = 90.00, +5% tax = 4.50 → 94.50
        $this->assertSame('100.00', $purchase->subtotal);
        $this->assertSame('10.00', $purchase->discount_total);
        $this->assertSame('4.50', $purchase->tax_total);
        $this->assertSame('94.50', $purchase->total);
        $this->assertSame(PaymentStatus::Partial, $purchase->payment_status);
        $this->assertSame('PUR-000001', $purchase->reference_no);

        $this->assertSame(100, $medicine->refresh()->stock_quantity);
        $this->assertSame('1.80', $medicine->selling_price, 'latest purchase updates the selling price');
        $batch = $medicine->batches()->first();
        $this->assertSame('LOT-1', $batch->batch_number);
        $this->assertSame('0.90', $batch->purchase_price, 'batch cost is net of discount');

        $this->assertSame('64.50', $supplier->refresh()->balance);
        $this->assertDatabaseHas('payments', ['purchase_id' => $purchase->id, 'type' => 'supplier_payment', 'amount' => '30.00']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'purchase.created']);
        $this->assertStockConsistent($medicine);
    }

    public function test_purchase_validation(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();

        $this->actingAs($this->admin())->post('/purchases', $this->payload($supplier, $medicine, [
            'purchase_date' => today()->toDateString(),
            'items' => [['expiry_date' => today()->subDay()->toDateString(), 'quantity' => 0, 'batch_number' => 'bad lot <x>']],
        ]))->assertSessionHasErrors(['items.0.expiry_date', 'items.0.quantity', 'items.0.batch_number']);

        $this->actingAs($this->admin())->post('/purchases', array_merge($this->payload($supplier, $medicine), ['items' => []]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_paying_more_than_the_total_rolls_back_everything(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();

        $this->actingAs($this->admin())->post('/purchases', $this->payload($supplier, $medicine, ['paid_amount' => '500']))
            ->assertSessionHas('error');

        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, $medicine->refresh()->stock_quantity);
        $this->assertSame(0, $medicine->batches()->count());
        $this->assertSame('0.00', $supplier->refresh()->balance);
        $this->assertDatabaseHas('document_sequences', ['type' => 'purchase', 'next_number' => 1]);
    }

    public function test_cancelling_an_untouched_purchase_reverses_stock_and_balance(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();
        $this->actingAs($this->admin())->post('/purchases', $this->payload($supplier, $medicine));
        $purchase = Purchase::query()->firstOrFail();

        $this->actingAs($this->admin())->post(route('purchases.cancel', $purchase), ['reason' => 'Wrong delivery'])->assertRedirect();

        $this->assertSame(PurchaseStatus::Cancelled, $purchase->refresh()->status);
        $this->assertSame(0, $medicine->refresh()->stock_quantity);
        // 94.50 owed − 30 paid = 64.50; cancelling removes 94.50 → supplier holds 30.00 credit.
        $this->assertSame('-30.00', $supplier->refresh()->balance);
        $this->assertStockConsistent($medicine);

        $this->actingAs($this->admin())->post(route('purchases.cancel', $purchase), ['reason' => 'again'])->assertSessionHas('error');
    }

    public function test_purchase_cannot_be_cancelled_once_stock_was_sold(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();
        $this->actingAs($this->admin())->post('/purchases', $this->payload($supplier, $medicine));
        $purchase = Purchase::query()->firstOrFail();

        $this->actingAs($this->admin())->postJson('/pos', [
            'payment_method_id' => $this->cash()->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->actingAs($this->admin())->post(route('purchases.cancel', $purchase), ['reason' => 'Try'])->assertSessionHas('error');
        $this->assertSame(PurchaseStatus::Received, $purchase->refresh()->status);
        $this->assertSame(99, $medicine->refresh()->stock_quantity);
    }

    public function test_supplier_payment_is_allocated_to_oldest_purchases(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/purchases', $this->payload($supplier, $medicine, ['paid_amount' => '0', 'purchase_date' => today()->subDays(5)->toDateString()]));
        $this->actingAs($admin)->post('/purchases', $this->payload($supplier, $medicine, ['paid_amount' => '0', 'items' => [['batch_number' => 'LOT-2']]]));
        [$first, $second] = Purchase::query()->orderBy('id')->get()->all();

        $this->actingAs($admin)->post(route('suppliers.payments', $supplier), ['amount' => '100', 'payment_method_id' => $this->cash()->id])
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, $first->refresh()->payment_status);
        $this->assertSame('5.50', $second->refresh()->paid_amount);
        $this->assertSame('89.00', $supplier->refresh()->balance);

        $this->actingAs($admin)->post(route('suppliers.payments', $supplier), ['amount' => '1000', 'payment_method_id' => $this->cash()->id])
            ->assertSessionHas('error');
        $this->assertSame('89.00', $supplier->refresh()->balance);
    }

    public function test_purchase_header_can_be_edited_but_not_lines(): void
    {
        $supplier = $this->supplier();
        $medicine = Medicine::factory()->create();
        $this->actingAs($this->admin())->post('/purchases', $this->payload($supplier, $medicine));
        $purchase = Purchase::query()->firstOrFail();

        $this->actingAs($this->admin())->put(route('purchases.update', $purchase), [
            'supplier_invoice_no' => 'FIXED-1', 'purchase_date' => today()->toDateString(), 'total' => '1.00',
        ])->assertRedirect();

        $this->assertSame('FIXED-1', $purchase->refresh()->supplier_invoice_no);
        $this->assertSame('94.50', $purchase->total);
    }
}
