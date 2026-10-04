<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Renders every screen against realistic demo data, plus settings, staff and
 * expense workflows (including upload safety).
 */
class ApplicationTest extends TestCase
{
    public function test_every_page_renders_for_admin_with_demo_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $admin = $this->admin();

        $medicine = Medicine::query()->firstOrFail();
        $customer = Customer::query()->has('sales')->firstOrFail();
        $supplier = Supplier::query()->firstOrFail();
        $purchase = Purchase::query()->firstOrFail();
        $sale = Sale::query()->firstOrFail();
        $return = SaleReturn::query()->firstOrFail();
        $staff = User::query()->where('email', 'cashier@pharmacy.test')->firstOrFail();
        $role = Role::query()->where('slug', 'pharmacist')->firstOrFail();
        $expense = Expense::query()->firstOrFail();

        $pages = [
            '/dashboard', '/pos', '/profile',
            '/medicines', '/medicines/create', "/medicines/{$medicine->id}", "/medicines/{$medicine->id}/edit", "/medicines/{$medicine->id}/barcode",
            "/medicines/{$medicine->id}/opening-stock", '/medicines?archived=1', '/medicines?stock=expiring',
            '/batches', '/batches?expiry=expired', '/stock-movements', '/categories', '/manufacturers',
            '/customers', '/customers/create', "/customers/{$customer->id}", "/customers/{$customer->id}/edit",
            '/suppliers', '/suppliers/create', "/suppliers/{$supplier->id}", "/suppliers/{$supplier->id}/edit",
            '/purchases', '/purchases/create', "/purchases/{$purchase->id}", "/purchases/{$purchase->id}/edit",
            '/sales', "/sales/{$sale->id}", "/sales/{$sale->id}/print",
            '/returns', '/returns/create', "/returns/create?invoice={$sale->invoice_number}", "/returns/{$return->id}",
            '/expenses', '/expenses/create', "/expenses/{$expense->id}/edit", '/expense-categories',
            '/reports', '/reports/sales?range=last30', '/reports/sales?range=year&group=month', '/reports/sales?group=week&range=last30',
            '/reports/medicines', '/reports/categories', '/reports/staff', '/reports/customers', '/reports/purchases', '/reports/suppliers',
            '/reports/inventory', '/reports/low-stock', '/reports/expiry', '/reports/expiry?scope=expired', '/reports/expenses',
            '/reports/payments', '/reports/profit?range=last_month',
            '/staff', '/staff/create', "/staff/{$staff->id}/edit", '/roles', '/roles/create', "/roles/{$role->id}/edit",
            '/settings', '/activity-log', '/activity-log?action=sale',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        foreach (['sales', 'medicines', 'inventory', 'expiry', 'payments'] as $report) {
            $this->actingAs($admin)->get("/reports/{$report}?export=csv")->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
        }

        $this->actingAs($admin)->getJson('/medicines/lookup?q=para')->assertOk()->assertJsonStructure([['id', 'name']]);
        $this->actingAs($admin)->getJson('/customers/lookup?q=ah')->assertOk();
    }

    public function test_demo_data_is_internally_consistent(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (Medicine::query()->get() as $medicine) {
            $this->assertStockConsistent($medicine);
        }

        foreach (Customer::query()->get() as $customer) {
            $due = Sale::query()->where('customer_id', $customer->id)->get()->sum(fn (Sale $s) => (float) $s->dueAmount());
            $this->assertEqualsWithDelta((float) $customer->balance, $due, 0.001, "Balance mismatch for {$customer->name}");
        }
    }

    public function test_settings_update_and_logo_upload_rejects_svg(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $base = [
            'store_name' => 'Green Cross Pharmacy', 'currency_symbol' => '₨', 'currency_code' => 'pkr',
            'invoice_prefix' => 'GC-', 'purchase_prefix' => 'PO-', 'return_prefix' => 'CR-', 'tax_label' => 'GST',
            'default_tax_rate' => '0', 'low_stock_threshold' => '5', 'expiry_alert_days' => '60', 'max_discount_percent' => '10',
        ];

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($admin)->put('/settings', $base + ['logo' => $svg])->assertSessionHasErrors('logo');

        $this->actingAs($admin)->put('/settings', $base + ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])->assertSessionHas('success');

        $this->assertSame('Green Cross Pharmacy', settings('store_name'));
        $this->assertSame('PKR', settings('currency_code'));
        Storage::disk('public')->assertExists(settings('store_logo'));

        // New prefix is used for the next invoice.
        $medicine = $this->medicineWithStock(5);
        $this->actingAs($admin)->postJson('/pos', ['payment_method_id' => $this->cash()->id, 'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]]])
            ->assertJsonPath('invoice_number', 'GC-000001');
    }

    public function test_expense_crud_with_private_receipt(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $category = ExpenseCategory::query()->firstOrFail();

        $this->actingAs($admin)->post('/expenses', [
            'expense_category_id' => $category->id, 'payment_method_id' => $this->cash()->id, 'amount' => '120.50',
            'expense_date' => today()->toDateString(), 'description' => 'Electricity bill',
            'receipt' => UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
            'user_id' => 999,
        ])->assertRedirect('/expenses');

        $expense = Expense::query()->firstOrFail();
        $this->assertSame($admin->id, $expense->user_id);
        Storage::disk('local')->assertExists($expense->receipt_path);
        $this->actingAs($admin)->get(route('expenses.receipt', $expense))->assertOk()->assertDownload();

        $this->actingAs($admin)->post('/expenses', [
            'expense_category_id' => $category->id, 'payment_method_id' => $this->cash()->id, 'amount' => '1',
            'expense_date' => today()->toDateString(), 'description' => 'Bad file',
            'receipt' => UploadedFile::fake()->createWithContent('evil.html', '<script>alert(1)</script>'),
        ])->assertSessionHasErrors('receipt');

        $this->actingAs($admin)->post('/expenses', [
            'expense_category_id' => $category->id, 'payment_method_id' => $this->cash()->id, 'amount' => '-5',
            'expense_date' => today()->addDay()->toDateString(), 'description' => '',
        ])->assertSessionHasErrors(['amount', 'expense_date', 'description']);

        $this->actingAs($admin)->delete(route('expenses.destroy', $expense))->assertRedirect();
        $this->assertSoftDeleted($expense);
        $this->assertDatabaseHas('activity_logs', ['action' => 'expense.deleted']);
    }

    public function test_admin_manages_staff_lifecycle(): void
    {
        $admin = $this->admin();
        $role = Role::query()->where('slug', 'pharmacist')->firstOrFail();

        $this->actingAs($admin)->post('/staff', [
            'name' => 'Nadia', 'email' => 'nadia@example.com', 'role_id' => $role->id,
            'password' => 'Passw0rd-123', 'password_confirmation' => 'Passw0rd-123', 'is_active' => 1,
        ])->assertRedirect('/staff');

        $user = User::query()->where('email', 'nadia@example.com')->firstOrFail();
        $this->assertSame($role->id, $user->role_id);
        $this->assertTrue($user->hasPermission('purchases.create'));
        $this->assertFalse($user->hasPermission('staff.manage'));

        $this->actingAs($admin)->post(route('staff.toggle', $user))->assertRedirect();
        $this->assertFalse($user->refresh()->is_active);

        $this->actingAs($admin)->put(route('staff.password', $user), ['password' => 'An0ther-Pass', 'password_confirmation' => 'An0ther-Pass'])->assertRedirect();
        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.password_reset', 'subject_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.deactivated', 'subject_id' => $user->id]);
    }

    public function test_security_headers_and_no_cache_for_authenticated_pages(): void
    {
        $response = $this->actingAs($this->admin())->get('/dashboard');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
