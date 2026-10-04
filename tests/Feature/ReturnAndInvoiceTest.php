<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Tests\TestCase;

class ReturnAndInvoiceTest extends TestCase
{
    private function sell(Medicine $medicine, int $qty, array $extra = []): Sale
    {
        $this->flushSession();
        $id = $this->actingAs($this->admin())->postJson('/pos', array_merge([
            'payment_method_id' => $this->cash()->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => $qty]],
        ], $extra))->assertCreated()->json('id');

        return Sale::query()->with('items')->findOrFail($id);
    }

    private function returnItems(Sale $sale, array $items, array $extra = [], ?User $user = null)
    {
        $this->flushSession();

        return $this->actingAs($user ?? $this->admin())->post(route('returns.store', $sale), array_merge([
            'reason' => 'Customer changed mind',
            'refund_method_id' => $this->cash()->id,
            'items' => $items,
        ], $extra));
    }

    /**
     * The end-to-end scenario: create medicine → add stock → sell → verify
     * stock → invoice → return → stock restored → staff blocked from admin.
     */
    public function test_full_pharmacy_workflow(): void
    {
        $admin = $this->admin();

        // 1. Create medicine
        $this->actingAs($admin)->post('/medicines', [
            'name' => 'Amoxicillin', 'sku' => 'AMX-500', 'unit' => 'capsule',
            'purchase_price' => '0.20', 'selling_price' => '0.50', 'tax_rate' => '0', 'min_stock_level' => 20, 'is_active' => 1,
        ])->assertRedirect();
        $medicine = Medicine::query()->where('sku', 'AMX-500')->firstOrFail();

        // 2. Add stock via a purchase
        $this->actingAs($admin)->post('/purchases', [
            'supplier_id' => $this->supplier()->id,
            'purchase_date' => today()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'batch_number' => 'AX1', 'expiry_date' => now()->addYear()->toDateString(), 'quantity' => 100, 'unit_cost' => '0.20']],
        ])->assertRedirect();
        $this->assertSame(100, $medicine->refresh()->stock_quantity);

        // 3. Sell medicine
        $sale = $this->sell($medicine, 30);

        // 4. Stock decreases
        $this->assertSame(70, $medicine->refresh()->stock_quantity);

        // 5. Invoice generated, viewable, printable and downloadable as PDF
        $this->actingAs($admin)->get(route('sales.show', $sale))->assertOk()->assertSee($sale->invoice_number)->assertSee('Amoxicillin');
        $this->actingAs($admin)->get(route('sales.print', $sale))->assertOk()->assertSee($sale->invoice_number);
        $pdf = $this->actingAs($admin)->get(route('sales.pdf', $sale))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // 6. Process a return of 10 units
        $this->returnItems($sale, [['sale_item_id' => $sale->items->first()->id, 'quantity' => 10, 'restock' => 1]])->assertRedirect();
        $return = SaleReturn::query()->firstOrFail();
        $this->assertSame('5.00', $return->total);
        $this->assertSame('5.00', $return->refund_amount);

        // 7. Stock restored to the original batch
        $this->assertSame(80, $medicine->refresh()->stock_quantity);
        $this->assertSame(80, $medicine->batches()->where('batch_number', 'AX1')->value('quantity'));
        $sale->refresh();
        $this->assertSame(SaleStatus::PartiallyReturned, $sale->status);
        $this->assertSame('10.00', $sale->netTotal());
        $this->assertSame('10.00', $sale->paid_amount);
        $this->assertStockConsistent($medicine);

        // 8. Staff cannot reach admin functionality
        $staff = User::factory()->create();
        $this->flushSession();
        $this->actingAs($staff)->get('/staff')->assertForbidden();
        $this->actingAs($staff)->get('/settings')->assertForbidden();
        $this->actingAs($staff)->get('/roles')->assertForbidden();
        $this->actingAs($staff)->post(route('returns.store', $sale), ['reason' => 'x', 'items' => []])->assertForbidden();
    }

    public function test_cannot_return_more_than_was_sold(): void
    {
        $medicine = $this->medicineWithStock(50);
        $sale = $this->sell($medicine, 5);
        $itemId = $sale->items->first()->id;

        $this->returnItems($sale, [['sale_item_id' => $itemId, 'quantity' => 6]])->assertSessionHas('error');
        $this->returnItems($sale, [['sale_item_id' => $itemId, 'quantity' => 5]])->assertRedirect();
        $this->returnItems($sale, [['sale_item_id' => $itemId, 'quantity' => 1]])->assertSessionHas('error');

        $this->assertSame(1, SaleReturn::query()->count());
        $this->assertSame(SaleStatus::Returned, $sale->refresh()->status);
        $this->assertSame(50, $medicine->refresh()->stock_quantity);
    }

    public function test_items_from_another_invoice_cannot_be_returned(): void
    {
        $medicine = $this->medicineWithStock(50);
        $saleA = $this->sell($medicine, 5);
        $saleB = $this->sell($medicine, 5);

        $this->returnItems($saleA, [['sale_item_id' => $saleB->items->first()->id, 'quantity' => 1]])->assertSessionHas('error');
        $this->assertSame(0, SaleReturn::query()->count());
        $this->assertSame(40, $medicine->refresh()->stock_quantity);
    }

    public function test_damaged_returns_are_not_restocked(): void
    {
        $medicine = $this->medicineWithStock(10);
        $sale = $this->sell($medicine, 4);

        $this->returnItems($sale, [['sale_item_id' => $sale->items->first()->id, 'quantity' => 2, 'restock' => 0]])->assertRedirect();

        $this->assertSame(6, $medicine->refresh()->stock_quantity);
        $this->assertFalse(SaleReturn::query()->first()->items()->first()->restocked);
        $this->assertSame('4.00', SaleReturn::query()->first()->total);
    }

    public function test_return_on_a_credit_sale_reduces_the_customer_due_before_refunding(): void
    {
        $medicine = $this->medicineWithStock(10);
        $customer = Customer::factory()->create();
        // 5 × 2.00 = 10.00, paid 4.00 → 6.00 due
        $sale = $this->sell($medicine, 5, ['customer_id' => $customer->id, 'amount_received' => '4.00']);
        $this->assertSame('6.00', $customer->refresh()->balance);

        // Return 4 units = 8.00 → 6.00 clears the due, 2.00 is refunded
        $this->returnItems($sale, [['sale_item_id' => $sale->items->first()->id, 'quantity' => 4]])->assertRedirect();

        $return = SaleReturn::query()->firstOrFail();
        $this->assertSame('6.00', $return->credit_amount);
        $this->assertSame('2.00', $return->refund_amount);
        $this->assertSame('0.00', $customer->refresh()->balance);
        $sale->refresh();
        $this->assertSame('2.00', $sale->paid_amount);
        $this->assertSame('0.00', $sale->dueAmount());
        $this->assertSame(PaymentStatus::Paid, $sale->payment_status);
        $this->assertDatabaseHas('payments', ['sale_return_id' => $return->id, 'type' => 'refund', 'direction' => 'out', 'amount' => '2.00']);
    }

    public function test_partial_returns_add_up_exactly_to_the_line_total(): void
    {
        $medicine = $this->medicineWithStock(10, null, '1.00', ['selling_price' => '3.33', 'tax_rate' => '5']);
        $sale = $this->sell($medicine, 3, ['items' => [['medicine_id' => $medicine->id, 'quantity' => 3, 'discount_percent' => 10]]]);
        $item = $sale->items->first();

        foreach ([1, 1, 1] as $qty) {
            $this->returnItems($sale, [['sale_item_id' => $item->id, 'quantity' => $qty]])->assertRedirect();
        }

        $this->assertSame($item->line_total, number_format((float) SaleReturn::query()->sum('total'), 2, '.', ''));
        $this->assertSame('0.00', $sale->refresh()->netTotal());
        $this->assertSame('0.00', $sale->paid_amount);
    }

    public function test_return_lookup_by_invoice_number(): void
    {
        $medicine = $this->medicineWithStock(10);
        $sale = $this->sell($medicine, 1);

        $this->actingAs($this->admin())->get(route('returns.create', ['invoice' => $sale->invoice_number]))
            ->assertOk()->assertSee('Process return');
        $this->actingAs($this->admin())->get(route('returns.create', ['invoice' => 'NOPE-1']))
            ->assertOk()->assertSee('Invoice not found');
    }

    public function test_customer_payment_is_allocated_to_oldest_invoices(): void
    {
        $medicine = $this->medicineWithStock(50);
        $customer = Customer::factory()->create();
        $first = $this->sell($medicine, 5, ['customer_id' => $customer->id, 'amount_received' => '0']);  // 10.00 due
        $second = $this->sell($medicine, 5, ['customer_id' => $customer->id, 'amount_received' => '0']); // 10.00 due

        $this->flushSession();
        $this->actingAs($this->admin())->post(route('customers.payments', $customer), ['amount' => '15', 'payment_method_id' => $this->cash()->id])
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, $first->refresh()->payment_status);
        $this->assertSame(PaymentStatus::Partial, $second->refresh()->payment_status);
        $this->assertSame('5.00', $customer->refresh()->balance);

        $this->actingAs($this->admin())->post(route('customers.payments', $customer), ['amount' => '6', 'payment_method_id' => $this->cash()->id])
            ->assertSessionHas('error');
        $this->assertSame('5.00', $customer->refresh()->balance);
    }

    public function test_customer_with_balance_cannot_be_archived(): void
    {
        $medicine = $this->medicineWithStock(10);
        $customer = Customer::factory()->create();
        $this->sell($medicine, 1, ['customer_id' => $customer->id, 'amount_received' => '0']);

        $this->actingAs($this->admin())->delete(route('customers.destroy', $customer))->assertSessionHas('error');
        $this->assertNotSoftDeleted($customer);
    }

    public function test_invoice_list_filters(): void
    {
        $medicine = $this->medicineWithStock(10);
        $customer = Customer::factory()->create(['name' => 'Zara Unique']);
        $this->sell($medicine, 1, ['customer_id' => $customer->id]);
        $this->sell($medicine, 1);

        $this->actingAs($this->admin())->get('/sales?q=Zara')->assertOk()->assertSee('INV-000001')->assertDontSee('INV-000002');
        $this->actingAs($this->admin())->get('/sales?payment=bogus')->assertSessionHasErrors('payment');
    }
}
