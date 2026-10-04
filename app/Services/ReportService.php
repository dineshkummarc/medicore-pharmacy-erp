<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Support\Tenancy\CurrentPharmacy;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregations for reports and the dashboard. All queries are pushed to the
 * database; nothing loads full transaction tables into memory.
 *
 * Revenue figures are net of returns. Profit = revenue (ex. tax) − cost of
 * goods sold (batch cost of units not returned).
 */
class ReportService
{
    /**
     * Fraction of a sale line that has not been returned.
     */
    private const NET_RATIO = '((sale_items.quantity - sale_items.returned_quantity) * 1.0 / sale_items.quantity)';

    /**
     * @return array<string, float|int>
     */
    public function salesSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = $this->salesBetween($from, $to)
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount_total),0) as discounts,
                COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(total),0) as total, COALESCE(SUM(returned_amount),0) as returned,
                COALESCE(SUM(paid_amount),0) as paid')
            ->first();

        $items = $this->itemsBetween($from, $to)
            ->selectRaw('COALESCE(SUM((sale_items.line_total - sale_items.tax_amount) * '.self::NET_RATIO.'),0) as revenue_ex_tax,
                COALESCE(SUM(sale_items.unit_cost * (sale_items.quantity - sale_items.returned_quantity)),0) as cogs,
                COALESCE(SUM(sale_items.quantity - sale_items.returned_quantity),0) as units')
            ->first();

        $net = (float) $sales->total - (float) $sales->returned;
        $revenueExTax = (float) $items->revenue_ex_tax;
        $cogs = (float) $items->cogs;

        return [
            'invoices' => (int) $sales->invoices,
            'gross' => (float) $sales->gross,
            'discounts' => (float) $sales->discounts,
            'tax' => (float) $sales->tax,
            'total' => (float) $sales->total,
            'returned' => (float) $sales->returned,
            'net' => $net,
            'paid' => (float) $sales->paid,
            'units' => (int) $items->units,
            'revenue_ex_tax' => $revenueExTax,
            'cogs' => $cogs,
            'gross_profit' => $revenueExTax - $cogs,
            'average' => $sales->invoices > 0 ? $net / (int) $sales->invoices : 0,
        ];
    }

    /**
     * Net sales per day/week/month/year, with empty periods filled with zero.
     *
     * @return Collection<int, array{period: string, label: string, invoices: int, total: float}>
     */
    public function salesTrend(CarbonInterface $from, CarbonInterface $to, string $granularity = 'day'): Collection
    {
        $bucket = $this->bucketExpression('sold_at', $granularity);

        $rows = $this->salesBetween($from, $to)
            ->selectRaw("{$bucket} as period, COUNT(*) as invoices, COALESCE(SUM(total - returned_amount),0) as total")
            ->groupBy(DB::raw($bucket))
            ->pluck('total', 'period')
            ->map(fn ($v) => (float) $v);

        $counts = $this->salesBetween($from, $to)
            ->selectRaw("{$bucket} as period, COUNT(*) as invoices")
            ->groupBy(DB::raw($bucket))
            ->pluck('invoices', 'period');

        return $this->periods($from, $to, $granularity)->map(fn (array $p) => [
            'period' => $p['key'],
            'label' => $p['label'],
            'invoices' => (int) ($counts[$p['key']] ?? 0),
            'total' => (float) ($rows[$p['key']] ?? 0),
        ])->values();
    }

    /**
     * @return Collection<int, object>
     */
    public function salesByMedicine(CarbonInterface $from, CarbonInterface $to, ?int $limit = null): Collection
    {
        return $this->itemsBetween($from, $to)
            ->join('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
            ->selectRaw('medicines.id, medicines.name, medicines.strength, medicines.sku,
                SUM(sale_items.quantity - sale_items.returned_quantity) as units,
                SUM(sale_items.line_total * '.self::NET_RATIO.') as revenue,
                SUM((sale_items.line_total - sale_items.tax_amount) * '.self::NET_RATIO.' - sale_items.unit_cost * (sale_items.quantity - sale_items.returned_quantity)) as profit')
            ->groupBy('medicines.id', 'medicines.name', 'medicines.strength', 'medicines.sku')
            ->orderByDesc('revenue')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function salesByCategory(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->itemsBetween($from, $to)
            ->join('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
            ->leftJoin('categories', 'categories.id', '=', 'medicines.category_id')
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as name,
                SUM(sale_items.quantity - sale_items.returned_quantity) as units,
                SUM(sale_items.line_total * ".self::NET_RATIO.') as revenue,
                SUM((sale_items.line_total - sale_items.tax_amount) * '.self::NET_RATIO.' - sale_items.unit_cost * (sale_items.quantity - sale_items.returned_quantity)) as profit')
            ->groupBy(DB::raw("COALESCE(categories.name, 'Uncategorised')"))
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function salesByStaff(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->salesBetween($from, $to)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.id, users.name, COUNT(sales.id) as invoices,
                SUM(sales.total - sales.returned_amount) as net, SUM(sales.discount_total) as discounts,
                AVG(sales.total - sales.returned_amount) as average')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('net')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function salesByCustomer(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->salesBetween($from, $to)
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->selectRaw('customers.id, customers.name, customers.phone, customers.balance, COUNT(sales.id) as invoices,
                SUM(sales.total - sales.returned_amount) as net, MAX(sales.sold_at) as last_purchase')
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'customers.balance')
            ->orderByDesc('net')
            ->get();
    }

    /**
     * @return array{summary: object, suppliers: Collection<int, object>}
     */
    public function purchases(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = fn () => Purchase::query()
            ->where('purchases.status', PurchaseStatus::Received)
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()]);

        return [
            'summary' => $base()->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_amount),0) as paid,
                COALESCE(SUM(discount_total),0) as discounts, COALESCE(SUM(tax_total),0) as tax')->first(),
            'suppliers' => $base()
                ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
                ->selectRaw('suppliers.id, suppliers.name, suppliers.balance, COUNT(purchases.id) as count,
                    SUM(purchases.total) as total, SUM(purchases.paid_amount) as paid')
                ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.balance')
                ->orderByDesc('total')
                ->get(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    public function inventoryValuation(): array
    {
        $row = Batch::query()
            ->join('medicines', 'medicines.id', '=', 'batches.medicine_id')
            ->whereNull('medicines.deleted_at')
            ->where('batches.quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(batches.quantity * batches.purchase_price),0) as cost,
                COALESCE(SUM(batches.quantity * medicines.selling_price),0) as retail,
                COALESCE(SUM(batches.quantity),0) as units,
                COALESCE(SUM(CASE WHEN batches.expiry_date <= ? THEN batches.quantity * batches.purchase_price ELSE 0 END),0) as expired_cost',
                [today()->toDateString()])
            ->first();

        return [
            'cost' => (float) $row->cost,
            'retail' => (float) $row->retail,
            'units' => (int) $row->units,
            'expired_cost' => (float) $row->expired_cost,
            'potential_margin' => (float) $row->retail - (float) $row->cost,
        ];
    }

    /**
     * Value of stock lost to write-offs and negative adjustments, net of
     * positive adjustments, valued at batch cost.
     */
    public function inventoryLosses(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) StockMovement::query()
            ->whereIn('type', [StockMovementType::ExpiredWriteOff, StockMovementType::AdjustmentOut, StockMovementType::AdjustmentIn])
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(-quantity * unit_cost),0) as loss')
            ->value('loss');
    }

    public function expensesTotal(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    /**
     * @return Collection<int, object>
     */
    public function expensesByCategory(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('expense_categories.name, COUNT(expenses.id) as count, SUM(expenses.amount) as total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Money in and out per payment method.
     *
     * @return Collection<int, object>
     */
    public function paymentMethods(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Payment::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_methods.name,
                SUM(CASE WHEN payments.type IN (?, ?) THEN payments.amount ELSE 0 END) as received,
                SUM(CASE WHEN payments.type = ? THEN payments.amount ELSE 0 END) as refunded,
                SUM(CASE WHEN payments.type = ? THEN payments.amount ELSE 0 END) as supplier_paid,
                COUNT(payments.id) as transactions', [
                PaymentType::Sale->value, PaymentType::CustomerPayment->value,
                PaymentType::Refund->value, PaymentType::SupplierPayment->value,
            ])
            ->groupBy('payment_methods.name')
            ->orderByDesc('received')
            ->get();
    }

    /**
     * @return array<string, float>
     */
    public function profitAndLoss(CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = $this->salesSummary($from, $to);
        $expenses = $this->expensesTotal($from, $to);
        $losses = $this->inventoryLosses($from, $to);

        return [
            'revenue' => $sales['revenue_ex_tax'],
            'tax' => $sales['tax'],
            'cogs' => $sales['cogs'],
            'gross_profit' => $sales['gross_profit'],
            'gross_margin' => $sales['revenue_ex_tax'] > 0 ? $sales['gross_profit'] / $sales['revenue_ex_tax'] * 100 : 0,
            'expenses' => $expenses,
            'inventory_losses' => $losses,
            'net_profit' => $sales['gross_profit'] - $expenses - $losses,
        ];
    }

    private function salesBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Sale::query()->whereBetween('sales.sold_at', [$from, $to]);
    }

    private function itemsBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.pharmacy_id', app(CurrentPharmacy::class)->id())
            ->whereBetween('sales.sold_at', [$from, $to]);
    }

    /**
     * Portable (MySQL / MariaDB / SQLite / PostgreSQL) date bucket expression.
     */
    private function bucketExpression(string $column, string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        $formats = [
            'day' => ['%Y-%m-%d', 'YYYY-MM-DD'],
            'week' => ['%x-W%v', 'IYYY-"W"IW'],
            'month' => ['%Y-%m', 'YYYY-MM'],
            'year' => ['%Y', 'YYYY'],
        ];
        [$mysql, $pg] = $formats[$granularity] ?? $formats['day'];

        return match ($driver) {
            'sqlite' => match ($granularity) {
                // SQLite has no ISO week; %W (Monday-based) is close enough for charting.
                'week' => "strftime('%Y-W%W', {$column})",
                default => "strftime('{$mysql}', {$column})",
            },
            'pgsql' => "to_char({$column}, '{$pg}')",
            default => "DATE_FORMAT({$column}, '{$mysql}')",
        };
    }

    /**
     * @return Collection<int, array{key: string, label: string}>
     */
    private function periods(CarbonInterface $from, CarbonInterface $to, string $granularity): Collection
    {
        $driver = DB::connection()->getDriverName();
        $interval = match ($granularity) {
            'week' => '1 week',
            'month' => '1 month',
            'year' => '1 year',
            default => '1 day',
        };

        $start = match ($granularity) {
            'week' => $from->copy()->startOfWeek(),
            'month' => $from->copy()->startOfMonth(),
            'year' => $from->copy()->startOfYear(),
            default => $from->copy()->startOfDay(),
        };

        return collect(CarbonPeriod::create($start, $interval, $to))->map(fn ($date) => match ($granularity) {
            'week' => [
                'key' => $driver === 'sqlite' ? $date->format('Y').'-W'.str_pad((string) $this->sqliteWeek($date), 2, '0', STR_PAD_LEFT) : $date->format('o-\WW'),
                'label' => 'Wk '.$date->format('W, M j'),
            ],
            'month' => ['key' => $date->format('Y-m'), 'label' => $date->format('M Y')],
            'year' => ['key' => $date->format('Y'), 'label' => $date->format('Y')],
            default => ['key' => $date->format('Y-m-d'), 'label' => $date->format('M j')],
        });
    }

    /**
     * Equivalent of SQLite/C strftime('%W'): weeks start on Monday and days
     * before the year's first Monday are week 00.
     */
    private function sqliteWeek(CarbonInterface $date): int
    {
        return intdiv(($date->dayOfYear - 1) + 7 - ((int) $date->format('N') - 1), 7);
    }
}
