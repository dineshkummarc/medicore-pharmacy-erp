<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Sale;
use App\Services\SettingsService;
use App\Support\Tenancy\CurrentPharmacy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleTest extends TestCase
{
    private function sell(array $items, array $extra = [], $user = null)
    {
        // A fresh session per actor: auth.session binds a session to one user's password hash.
        $this->flushSession();

        return $this->actingAs($user ?? $this->admin())->postJson('/pos', array_merge([
            'payment_method_id' => $this->cash()->id,
            'items' => $items,
        ], $extra));
    }

    public function test_sale_decreases_stock_records_payment_and_movements(): void
    {
        $medicine = $this->medicineWithStock(50);

        $response = $this->sell([['medicine_id' => $medicine->id, 'quantity' => 3]])->assertCreated();

        $sale = Sale::query()->with('items', 'payments')->findOrFail($response->json('id'));
        $this->assertSame('INV-000001', $sale->invoice_number);
        $this->assertSame('6.00', $sale->total);
        $this->assertSame(PaymentStatus::Paid, $sale->payment_status);
        $this->assertCount(1, $sale->payments);
        $this->assertSame('6.00', $sale->payments->first()->amount);
        $this->assertSame('1.00', $sale->items->first()->unit_cost);
        $this->assertSame(47, $medicine->refresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['medicine_id' => $medicine->id, 'type' => 'sale', 'quantity' => -3, 'reference_type' => 'sale', 'reference_id' => $sale->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sale.created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'invoice.generated']);
        $this->assertStockConsistent($medicine);
    }

    public function test_a_line_spanning_batches_creates_one_item_per_batch_with_correct_costs(): void
    {
        $medicine = Medicine::factory()->create(['selling_price' => '5.00']);
        $this->addBatch($medicine, 2, now()->addMonth()->toDateString(), '1.00', 'A');
        $this->addBatch($medicine, 10, now()->addYear()->toDateString(), '3.00', 'B');

        $id = $this->sell([['medicine_id' => $medicine->id, 'quantity' => 5]])->assertCreated()->json('id');
        $items = Sale::query()->findOrFail($id)->items()->orderBy('id')->get();

        $this->assertSame(['A', 'B'], $items->pluck('batch_number')->all());
        $this->assertSame([2, 3], $items->pluck('quantity')->all());
        $this->assertSame(['1.00', '3.00'], $items->pluck('unit_cost')->all());
        $this->assertSame('25.00', Sale::query()->find($id)->total);
    }

    public function test_insufficient_stock_rolls_back_the_whole_sale(): void
    {
        $plenty = $this->medicineWithStock(100);
        $scarce = $this->medicineWithStock(2);

        $this->sell([
            ['medicine_id' => $plenty->id, 'quantity' => 5],
            ['medicine_id' => $scarce->id, 'quantity' => 3],
        ])->assertStatus(422)->assertJsonFragment(['message' => "Insufficient stock for {$scarce->displayName()}: requested 3, only 2 sellable (non-expired) units available."]);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(100, $plenty->refresh()->stock_quantity);
        $this->assertSame(2, $scarce->refresh()->stock_quantity);
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertDatabaseHas('document_sequences', ['type' => 'invoice', 'next_number' => 1]);

        // The next successful sale still gets the first number: no gaps.
        $this->sell([['medicine_id' => $plenty->id, 'quantity' => 1]])->assertCreated()->assertJsonPath('invoice_number', 'INV-000001');
    }

    public function test_invoice_numbers_are_sequential_and_unique(): void
    {
        $medicine = $this->medicineWithStock(100);

        $numbers = collect(range(1, 5))->map(fn () => $this->sell([['medicine_id' => $medicine->id, 'quantity' => 1]])->json('invoice_number'));

        $this->assertSame(['INV-000001', 'INV-000002', 'INV-000003', 'INV-000004', 'INV-000005'], $numbers->all());
    }

    public function test_tax_and_discount_calculation(): void
    {
        $medicine = $this->medicineWithStock(10, null, '1.00', ['selling_price' => '3.33', 'tax_rate' => '5']);

        $id = $this->sell([['medicine_id' => $medicine->id, 'quantity' => 3, 'discount_percent' => 10]])->assertCreated()->json('id');
        $sale = Sale::query()->findOrFail($id);

        // gross 9.99; discount 10% = 1.00 (0.999 → 1.00); taxable 8.99; tax 5% = 0.45 → 9.44
        $this->assertSame('9.99', $sale->subtotal);
        $this->assertSame('1.00', $sale->discount_total);
        $this->assertSame('0.45', $sale->tax_total);
        $this->assertSame('9.44', $sale->total);
    }

    public function test_tax_is_not_charged_when_disabled(): void
    {
        app(SettingsService::class)->put(['tax_enabled' => false]);
        $medicine = $this->medicineWithStock(10, null, '1.00', ['tax_rate' => '18']);

        $id = $this->sell([['medicine_id' => $medicine->id, 'quantity' => 1]])->json('id');

        $this->assertSame('0.00', Sale::query()->find($id)->tax_total);
    }

    public function test_discount_requires_permission_and_respects_limit(): void
    {
        $medicine = $this->medicineWithStock(10);
        $cashier = $this->staffWith(['sales.create']);
        $senior = $this->staffWith(['sales.create', 'sales.discount']);

        $this->sell([['medicine_id' => $medicine->id, 'quantity' => 1, 'discount_percent' => 5]], [], $cashier)
            ->assertStatus(422)->assertJsonFragment(['message' => 'You are not authorised to apply discounts.']);

        $this->sell([['medicine_id' => $medicine->id, 'quantity' => 1, 'discount_percent' => 50]], [], $senior)
            ->assertStatus(422)->assertJsonFragment(['message' => 'Discount cannot exceed 20%.']);

        $this->sell([['medicine_id' => $medicine->id, 'quantity' => 1, 'discount_percent' => 20]], [], $senior)->assertCreated();
        $this->assertSame(9, $medicine->refresh()->stock_quantity);
    }

    public function test_credit_sale_needs_customer_and_permission_and_updates_balance(): void
    {
        $medicine = $this->medicineWithStock(10);
        $customer = Customer::factory()->create();
        $cashier = $this->staffWith(['sales.create']);

        $this->sell([['medicine_id' => $medicine->id, 'quantity' => 5]], ['amount_received' => '4.00'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Select a customer to sell on credit (amount received is less than the total).']);

        $this->sell([['medicine_id' => $medicine->id, 'quantity' => 5]], ['amount_received' => '4.00', 'customer_id' => $customer->id], $cashier)
            ->assertStatus(422);

        $id = $this->sell([['medicine_id' => $medicine->id, 'quantity' => 5]], ['amount_received' => '4.00', 'customer_id' => $customer->id])
            ->assertCreated()->assertJsonPath('due', '6.00')->json('id');

        $this->assertSame(PaymentStatus::Partial, Sale::query()->find($id)->payment_status);
        $this->assertSame('6.00', $customer->refresh()->balance);
    }

    public function test_overpayment_records_only_the_total_as_paid(): void
    {
        $medicine = $this->medicineWithStock(10);

        $id = $this->sell([['medicine_id' => $medicine->id, 'quantity' => 1]], ['amount_received' => '50'])->json('id');

        $this->assertSame('2.00', Sale::query()->find($id)->paid_amount);
        $this->assertDatabaseHas('payments', ['sale_id' => $id, 'amount' => '2.00']);
    }

    public function test_duplicate_lines_are_merged(): void
    {
        $medicine = $this->medicineWithStock(10);

        $this->sell([['medicine_id' => $medicine->id, 'quantity' => 2], ['medicine_id' => $medicine->id, 'quantity' => 3]])->assertCreated();

        $this->assertSame(5, $medicine->refresh()->stock_quantity);
    }

    public function test_inactive_or_unknown_medicines_cannot_be_sold(): void
    {
        $inactive = $this->medicineWithStock(10, null, '1.00', ['is_active' => false]);

        $this->sell([['medicine_id' => $inactive->id, 'quantity' => 1]])->assertStatus(422);
        $this->sell([['medicine_id' => 999999, 'quantity' => 1]])->assertJsonValidationErrors('items.0.medicine_id');
        $this->sell([['medicine_id' => $inactive->id, 'quantity' => -4]])->assertJsonValidationErrors('items.0.quantity');
        $this->sell([])->assertJsonValidationErrors('items');
    }

    public function test_another_pharmacys_records_are_invisible(): void
    {
        $mine = $this->medicineWithStock(10);

        $other = Pharmacy::query()->create(['name' => 'Other', 'slug' => 'other']);
        app(CurrentPharmacy::class)->set($other);
        $foreign = Medicine::factory()->create();
        $foreignCustomer = Customer::factory()->create();
        app(CurrentPharmacy::class)->forget();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('medicines.show', $foreign->id))->assertNotFound();
        $this->actingAs($admin)->get(route('customers.show', $foreignCustomer->id))->assertNotFound();
        $this->sell([['medicine_id' => $foreign->id, 'quantity' => 1]])->assertJsonValidationErrors('items.0.medicine_id');
        $this->sell([['medicine_id' => $mine->id, 'quantity' => 1]], ['customer_id' => $foreignCustomer->id])->assertJsonValidationErrors('customer_id');
        $this->actingAs($admin)->get('/medicines')->assertDontSee($foreign->name);
    }

    public function test_pos_search_returns_sellable_stock_without_costs(): void
    {
        $medicine = Medicine::factory()->create(['name' => 'Cetirizine', 'barcode' => '5000000000001']);
        $this->addBatch($medicine, 4, now()->addYear()->toDateString());
        $this->addBatch($medicine, 9, now()->subDay()->toDateString());

        $response = $this->actingAs($this->admin())->getJson('/pos/search?q=5000000000001')->assertOk();

        $response->assertJsonPath('0.stock', 4);
        $this->assertArrayNotHasKey('purchase_price', $response->json('0'));
    }
}
