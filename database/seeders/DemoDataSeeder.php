<?php

namespace Database\Seeders;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Realistic sample data generated through the real services, so stock,
 * balances, invoices and the audit log are all consistent.
 *
 * Enable with SEED_DEMO_DATA=true (local environment only).
 */
class DemoDataSeeder extends Seeder
{
    public function run(PurchaseService $purchases, SaleService $sales, ReturnService $returns): void
    {
        mt_srand(20261004);

        $admin = User::query()->whereHas('role', fn ($q) => $q->where('slug', Role::ADMIN))->firstOrFail();
        $cashier = $this->staffUser();
        Auth::setUser($admin);

        $categories = collect(['Analgesics', 'Antibiotics', 'Antihistamines', 'Gastrointestinal', 'Cardiovascular', 'Diabetes', 'Respiratory', 'Vitamins & Supplements', 'Dermatology', 'First Aid'])
            ->mapWithKeys(fn ($name) => [$name => Category::query()->firstOrCreate(['name' => $name], ['is_active' => true])]);

        $manufacturers = collect(['Helix Pharma', 'Northbridge Labs', 'Citadel Healthcare', 'Aster Generics', 'Meridian Life Sciences'])
            ->map(fn ($name) => Manufacturer::query()->firstOrCreate(['name' => $name], ['country' => 'Local', 'is_active' => true]));

        $suppliers = collect([
            ['name' => 'Prime Medical Distributors', 'contact_person' => 'Sara Malik', 'phone' => '+1 555 0101'],
            ['name' => 'CityCare Wholesale', 'contact_person' => 'Omar Siddiqui', 'phone' => '+1 555 0102'],
            ['name' => 'Wellness Supply Co.', 'contact_person' => 'Lena Fischer', 'phone' => '+1 555 0103'],
        ])->map(fn ($row) => Supplier::query()->firstOrCreate(['name' => $row['name']], $row + ['email' => strtolower(str_replace([' ', '.'], '', $row['name'])).'@example.com', 'is_active' => true]));

        $catalogue = [
            ['Paracetamol', 'Paracetamol', '500mg', 'tablet', 'Analgesics', 0.05, 0.10],
            ['Ibuprofen', 'Ibuprofen', '400mg', 'tablet', 'Analgesics', 0.08, 0.15],
            ['Diclofenac Gel', 'Diclofenac sodium', '1%', 'tube', 'Analgesics', 2.40, 4.20],
            ['Amoxicillin', 'Amoxicillin', '500mg', 'capsule', 'Antibiotics', 0.18, 0.35],
            ['Azithromycin', 'Azithromycin', '250mg', 'tablet', 'Antibiotics', 0.60, 1.10],
            ['Ciprofloxacin', 'Ciprofloxacin', '500mg', 'tablet', 'Antibiotics', 0.22, 0.45],
            ['Cetirizine', 'Cetirizine HCl', '10mg', 'tablet', 'Antihistamines', 0.04, 0.09],
            ['Loratadine', 'Loratadine', '10mg', 'tablet', 'Antihistamines', 0.06, 0.12],
            ['Omeprazole', 'Omeprazole', '20mg', 'capsule', 'Gastrointestinal', 0.10, 0.22],
            ['Pantoprazole', 'Pantoprazole', '40mg', 'tablet', 'Gastrointestinal', 0.14, 0.28],
            ['ORS Sachet', 'Oral rehydration salts', '20.5g', 'sachet', 'Gastrointestinal', 0.20, 0.40],
            ['Amlodipine', 'Amlodipine besylate', '5mg', 'tablet', 'Cardiovascular', 0.07, 0.16],
            ['Atorvastatin', 'Atorvastatin', '20mg', 'tablet', 'Cardiovascular', 0.15, 0.32],
            ['Losartan', 'Losartan potassium', '50mg', 'tablet', 'Cardiovascular', 0.12, 0.26],
            ['Metformin', 'Metformin HCl', '500mg', 'tablet', 'Diabetes', 0.05, 0.11],
            ['Glimepiride', 'Glimepiride', '2mg', 'tablet', 'Diabetes', 0.09, 0.20],
            ['Salbutamol Inhaler', 'Salbutamol', '100mcg', 'inhaler', 'Respiratory', 3.10, 5.50],
            ['Cough Syrup', 'Dextromethorphan', '100ml', 'bottle', 'Respiratory', 1.60, 2.90],
            ['Vitamin C', 'Ascorbic acid', '500mg', 'tablet', 'Vitamins & Supplements', 0.03, 0.08],
            ['Multivitamin', 'Multivitamin', null, 'tablet', 'Vitamins & Supplements', 0.10, 0.25],
            ['Calcium + D3', 'Calcium carbonate / Vitamin D3', '500mg', 'tablet', 'Vitamins & Supplements', 0.08, 0.18],
            ['Hydrocortisone Cream', 'Hydrocortisone', '1%', 'tube', 'Dermatology', 1.80, 3.40],
            ['Antiseptic Solution', 'Chlorhexidine', '100ml', 'bottle', 'First Aid', 1.20, 2.20],
            ['Bandage Roll', null, '7.5cm', 'piece', 'First Aid', 0.45, 0.95],
        ];

        $medicines = collect($catalogue)->map(function (array $row, int $i) use ($categories, $manufacturers, $suppliers) {
            [$name, $generic, $strength, $unit, $category, $cost, $price] = $row;

            return Medicine::query()->firstOrCreate(['sku' => sprintf('MED-%04d', $i + 1)], [
                'name' => $name,
                'generic_name' => $generic,
                'strength' => $strength,
                'barcode' => (string) (890100000000 + $i * 7 + 3),
                'unit' => $unit,
                'category_id' => $categories[$category]->id,
                'manufacturer_id' => $manufacturers[$i % $manufacturers->count()]->id,
                'supplier_id' => $suppliers[$i % $suppliers->count()]->id,
                'purchase_price' => $cost,
                'selling_price' => $price,
                'tax_rate' => in_array($category, ['Vitamins & Supplements', 'First Aid'], true) ? 5 : 0,
                'min_stock_level' => in_array($unit, ['tablet', 'capsule'], true) ? 100 : 10,
                'requires_prescription' => $category === 'Antibiotics',
                'is_active' => true,
            ]);
        });

        $customers = collect([
            ['Ahmed Raza', '+1 555 0201'], ['Maria Gonzalez', '+1 555 0202'], ['John Carter', '+1 555 0203'],
            ['Fatima Noor', '+1 555 0204'], ['Wei Chen', '+1 555 0205'], ['Grace Okafor', '+1 555 0206'],
            ['Daniel Kim', '+1 555 0207'], ['Aisha Khan', '+1 555 0208'],
        ])->map(fn ($c) => Customer::query()->firstOrCreate(['phone' => $c[1]], ['name' => $c[0], 'is_active' => true]));

        $cash = PaymentMethod::query()->where('code', 'cash')->value('id');
        $card = PaymentMethod::query()->where('code', 'card')->value('id');
        $bank = PaymentMethod::query()->where('code', 'bank_transfer')->value('id');

        // 1) Stock in: two purchase rounds, plus one batch that has since expired.
        $this->at(now()->subDays(75), function () use ($purchases, $suppliers, $medicines, $admin, $bank) {
            foreach ($suppliers as $s => $supplier) {
                $items = $medicines->filter(fn ($m, $i) => $i % 3 === $s)->map(fn (Medicine $m) => [
                    'medicine_id' => $m->id,
                    'batch_number' => 'B'.now()->format('ym').'-'.str_pad((string) $m->id, 3, '0', STR_PAD_LEFT),
                    'expiry_date' => now()->addDays(mt_rand(60, 700))->toDateString(),
                    'quantity' => in_array($m->unit, ['tablet', 'capsule'], true) ? mt_rand(400, 900) : mt_rand(30, 80),
                    'unit_cost' => $m->purchase_price,
                    'selling_price' => $m->selling_price,
                    'discount' => 0,
                    'tax_rate' => 0,
                ])->values()->all();

                $purchases->create([
                    'supplier_id' => $supplier->id,
                    'supplier_invoice_no' => 'SUP-'.mt_rand(10000, 99999),
                    'purchase_date' => now()->toDateString(),
                    'paid_amount' => $s === 0 ? null : 50,
                    'payment_method_id' => $bank,
                    'items' => $items,
                ], $admin);
            }
        });

        $this->at(now()->subDays(20), function () use ($purchases, $suppliers, $medicines, $admin, $cash) {
            $items = $medicines->take(12)->map(fn (Medicine $m, $i) => [
                'medicine_id' => $m->id,
                'batch_number' => 'R'.now()->format('ymd').'-'.$m->id,
                'expiry_date' => now()->addDays($i < 3 ? 70 : mt_rand(300, 720))->toDateString(),
                'quantity' => in_array($m->unit, ['tablet', 'capsule'], true) ? 300 : 25,
                'unit_cost' => $m->purchase_price,
                'selling_price' => $m->selling_price,
                'discount' => 1,
                'tax_rate' => 0,
            ])->values()->all();

            $purchases->create([
                'supplier_id' => $suppliers[1]->id,
                'supplier_invoice_no' => 'CCW-'.mt_rand(1000, 9999),
                'purchase_date' => now()->toDateString(),
                'paid_amount' => 100,
                'payment_method_id' => $cash,
                'items' => $items,
            ], $admin);
        });

        // 2) Sixty days of counter sales.
        $sellers = [$admin, $cashier];

        for ($day = 59; $day >= 0; $day--) {
            $count = mt_rand(3, 9) + ($day < 7 ? 2 : 0);

            for ($n = 0; $n < $count; $n++) {
                $when = now()->subDays($day)->setTime(mt_rand(9, 20), mt_rand(0, 59));

                if ($when->isFuture()) {
                    continue;
                }

                $this->at($when, function () use ($sales, $medicines, $customers, $sellers, $cash, $card) {
                    $customer = mt_rand(1, 100) <= 45 ? $customers->random() : null;
                    $items = $medicines->random(mt_rand(1, 4))->map(fn (Medicine $m) => [
                        'medicine_id' => $m->id,
                        'quantity' => in_array($m->unit, ['tablet', 'capsule'], true) ? mt_rand(1, 3) * 10 : mt_rand(1, 2),
                        'discount_percent' => mt_rand(1, 10) === 1 ? 5 : 0,
                    ])->values()->all();

                    $onCredit = $customer && mt_rand(1, 100) <= 15;

                    try {
                        $sales->create([
                            'customer_id' => $customer?->id,
                            'payment_method_id' => mt_rand(1, 100) <= 65 ? $cash : $card,
                            'amount_received' => $onCredit ? 0 : null,
                            'items' => $items,
                        ], $sellers[array_rand($sellers)]);
                    } catch (BusinessRuleException) {
                        // Ran out of stock for a random basket; skip it.
                    }
                });
            }
        }

        // An old batch that has since expired (kept out of the sales loop so FEFO doesn't sell it first).
        $this->at(now()->subDays(400), function () use ($purchases, $suppliers, $medicines, $admin) {
            $m = $medicines[6];
            $purchases->create([
                'supplier_id' => $suppliers[0]->id,
                'purchase_date' => now()->toDateString(),
                'items' => [[
                    'medicine_id' => $m->id, 'batch_number' => 'OLD-'.$m->id, 'expiry_date' => now()->addDays(385)->toDateString(),
                    'quantity' => 40, 'unit_cost' => $m->purchase_price, 'selling_price' => $m->selling_price,
                ]],
            ], $admin);
        });

        // Recently received short-dated stock, so expiry alerts have something to show.
        $this->at(now()->subDays(3), function () use ($purchases, $suppliers, $medicines, $admin) {
            $purchases->create([
                'supplier_id' => $suppliers[2]->id,
                'supplier_invoice_no' => 'WSC-'.mt_rand(1000, 9999),
                'purchase_date' => now()->toDateString(),
                'items' => collect([[$medicines[10], 25], [$medicines[17], 40], [$medicines[2], 60]])->map(fn ($row) => [
                    'medicine_id' => $row[0]->id,
                    'batch_number' => 'SD-'.$row[0]->id.'-'.now()->format('md'),
                    'expiry_date' => now()->addDays($row[1])->toDateString(),
                    'quantity' => 12,
                    'unit_cost' => $row[0]->purchase_price,
                    'selling_price' => $row[0]->selling_price,
                ])->all(),
            ], $admin);
        });

        // 3) A few returns.
        Sale::query()->where('sold_at', '>=', now()->subDays(10))->inRandomOrder()->limit(3)->get()
            ->each(function (Sale $sale) use ($returns, $admin, $cash) {
                $item = $sale->items()->first();

                $this->at($sale->sold_at->copy()->addHours(2)->min(now()), fn () => $returns->create($sale, [
                    'reason' => 'Customer returned unopened pack',
                    'refund_method_id' => $cash,
                    'items' => [['sale_item_id' => $item->id, 'quantity' => max(1, intdiv($item->quantity, 2)), 'restock' => true]],
                ], $admin));
            });

        // 4) Monthly running costs.
        $expenseCategories = ExpenseCategory::query()->pluck('id', 'name');

        foreach ([2, 1, 0] as $monthsAgo) {
            $month = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();

            foreach ([['Rent', 220, 1], ['Utilities', mt_rand(35, 55), 5], ['Salaries', 260, 28], ['Supplies', mt_rand(10, 25), 12]] as [$cat, $amount, $dayOfMonth]) {
                $date = $month->copy()->day($dayOfMonth);

                if ($date->isFuture()) {
                    continue;
                }

                $expense = new Expense([
                    'expense_category_id' => $expenseCategories[$cat],
                    'payment_method_id' => $bank,
                    'amount' => $amount,
                    'expense_date' => $date->toDateString(),
                    'description' => "{$cat} — ".$date->format('F Y'),
                ]);
                $expense->user_id = $admin->id;
                $expense->save();
            }
        }

        Auth::forgetUser();
        $this->command?->info('Demo data created: '.Sale::query()->count().' invoices, '.Medicine::query()->count().' medicines.');
    }

    private function staffUser(): User
    {
        $email = 'cashier@pharmacy.test';
        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            return $existing;
        }

        $role = Role::query()->where('slug', 'pharmacist')->firstOrFail();
        $user = new User(['name' => 'Demo Pharmacist', 'email' => $email, 'password' => Str::password(16)]);
        $user->role_id = $role->id;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function at(Carbon $moment, callable $callback): mixed
    {
        Carbon::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
