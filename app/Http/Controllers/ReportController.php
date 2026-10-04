<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Medicine;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->reports->salesSummary($from, $to),
            'pnl' => $request->user()->can('reports.financial') ? $this->reports->profitAndLoss($from, $to) : null,
        ]);
    }

    public function sales(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $granularity = in_array($request->query('group'), ['day', 'week', 'month', 'year'], true) ? $request->query('group') : $this->defaultGranularity($from, $to);
        $trend = $this->reports->salesTrend($from, $to, $granularity);

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-'.$granularity, ['Period', 'Invoices', 'Net sales'], $trend->map(fn ($r) => [$r['label'], $r['invoices'], $r['total']]));
        }

        return view('reports.sales', [
            'from' => $from, 'to' => $to, 'granularity' => $granularity,
            'trend' => $trend,
            'summary' => $this->reports->salesSummary($from, $to),
        ]);
    }

    public function medicines(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->salesByMedicine($from, $to);
        $financial = $request->user()->can('reports.financial');

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-by-medicine', array_merge(['SKU', 'Medicine', 'Units', 'Revenue'], $financial ? ['Profit'] : []),
                $rows->map(fn ($r) => array_merge([$r->sku, trim($r->name.' '.$r->strength), (int) $r->units, round((float) $r->revenue, 2)], $financial ? [round((float) $r->profit, 2)] : [])));
        }

        return view('reports.table', [
            'title' => 'Sales by medicine',
            'from' => $from, 'to' => $to,
            'columns' => array_merge(['Medicine' => 'text', 'SKU' => 'muted', 'Units sold' => 'number', 'Revenue' => 'money'], $financial ? ['Gross profit' => 'money'] : []),
            'rows' => $this->paginate($rows->map(fn ($r) => array_merge([trim($r->name.' '.$r->strength), $r->sku, (int) $r->units, $r->revenue], $financial ? [$r->profit] : [])), $request),
            'totals' => array_merge(['Total', '', $rows->sum('units'), $rows->sum('revenue')], $financial ? [$rows->sum('profit')] : []),
        ]);
    }

    public function categories(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->salesByCategory($from, $to);
        $financial = $request->user()->can('reports.financial');

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-by-category', ['Category', 'Units', 'Revenue'], $rows->map(fn ($r) => [$r->name, (int) $r->units, round((float) $r->revenue, 2)]));
        }

        return view('reports.table', [
            'title' => 'Sales by category',
            'from' => $from, 'to' => $to,
            'columns' => array_merge(['Category' => 'text', 'Units sold' => 'number', 'Revenue' => 'money'], $financial ? ['Gross profit' => 'money'] : []),
            'rows' => $this->paginate($rows->map(fn ($r) => array_merge([$r->name, (int) $r->units, $r->revenue], $financial ? [$r->profit] : [])), $request),
            'totals' => array_merge(['Total', $rows->sum('units'), $rows->sum('revenue')], $financial ? [$rows->sum('profit')] : []),
            'chart' => ['labels' => $rows->pluck('name')->take(8)->values(), 'values' => $rows->pluck('revenue')->take(8)->map(fn ($v) => round((float) $v, 2))->values()],
        ]);
    }

    public function staff(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->salesByStaff($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv('sales-by-staff', ['Staff', 'Invoices', 'Net sales', 'Discounts', 'Average'], $rows->map(fn ($r) => [$r->name, $r->invoices, round((float) $r->net, 2), round((float) $r->discounts, 2), round((float) $r->average, 2)]));
        }

        return view('reports.table', [
            'title' => 'Sales by staff',
            'from' => $from, 'to' => $to,
            'columns' => ['Staff member' => 'text', 'Invoices' => 'number', 'Net sales' => 'money', 'Discounts given' => 'money', 'Average sale' => 'money'],
            'rows' => $this->paginate($rows->map(fn ($r) => [$r->name, (int) $r->invoices, $r->net, $r->discounts, $r->average]), $request),
            'totals' => ['Total', $rows->sum('invoices'), $rows->sum('net'), $rows->sum('discounts'), ''],
        ]);
    }

    public function customers(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->salesByCustomer($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv('customer-purchases', ['Customer', 'Phone', 'Invoices', 'Net purchases', 'Outstanding', 'Last purchase'], $rows->map(fn ($r) => [$r->name, $r->phone, $r->invoices, round((float) $r->net, 2), $r->balance, $r->last_purchase]));
        }

        return view('reports.table', [
            'title' => 'Customer purchases',
            'from' => $from, 'to' => $to,
            'columns' => ['Customer' => 'text', 'Phone' => 'muted', 'Invoices' => 'number', 'Net purchases' => 'money', 'Outstanding' => 'money', 'Last purchase' => 'date'],
            'rows' => $this->paginate($rows->map(fn ($r) => [$r->name, $r->phone, (int) $r->invoices, $r->net, $r->balance, $r->last_purchase]), $request),
            'totals' => ['Total', '', $rows->sum('invoices'), $rows->sum('net'), '', ''],
            'note' => 'Walk-in sales without a customer are not included.',
        ]);
    }

    public function purchases(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $data = $this->reports->purchases($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv('purchases-by-supplier', ['Supplier', 'Purchases', 'Total', 'Paid', 'Current balance'], $data['suppliers']->map(fn ($r) => [$r->name, $r->count, round((float) $r->total, 2), round((float) $r->paid, 2), $r->balance]));
        }

        return view('reports.table', [
            'title' => 'Purchases by supplier',
            'from' => $from, 'to' => $to,
            'stats' => [
                'Purchases' => number_format((int) $data['summary']->count),
                'Total purchased' => money($data['summary']->total),
                'Paid' => money($data['summary']->paid),
                'Discounts received' => money($data['summary']->discounts),
            ],
            'columns' => ['Supplier' => 'text', 'Purchases' => 'number', 'Total' => 'money', 'Paid at purchase/after' => 'money', 'Current balance' => 'money'],
            'rows' => $this->paginate($data['suppliers']->map(fn ($r) => [$r->name, (int) $r->count, $r->total, $r->paid, $r->balance]), $request),
            'totals' => ['Total', $data['suppliers']->sum('count'), $data['suppliers']->sum('total'), $data['suppliers']->sum('paid'), ''],
        ]);
    }

    public function suppliers(Request $request): View|StreamedResponse
    {
        return $this->purchases($request);
    }

    public function inventory(Request $request): View|StreamedResponse
    {
        $financial = $request->user()->can('reports.financial');
        $query = Medicine::query()
            ->with('category')
            ->where('stock_quantity', '>', 0)
            ->withSum(['batches as stock_cost' => fn ($q) => $q->where('quantity', '>', 0)], DB::raw('quantity * purchase_price'))
            ->orderBy('name');

        if ($request->query('export') === 'csv') {
            return $this->csv('inventory-valuation', ['SKU', 'Medicine', 'Category', 'Stock', 'Cost value', 'Retail value'],
                $query->get()->map(fn ($m) => [$m->sku, $m->displayName(), $m->category?->name, $m->stock_quantity, round((float) $m->stock_cost, 2), round($m->stock_quantity * (float) $m->selling_price, 2)]));
        }

        $page = $query->paginate(25)->withQueryString();

        return view('reports.table', [
            'title' => 'Inventory valuation',
            'stats' => $financial ? (function () {
                $v = $this->reports->inventoryValuation();

                return [
                    'Units on hand' => number_format($v['units']),
                    'Cost value' => money($v['cost']),
                    'Retail value' => money($v['retail']),
                    'Expired stock (cost)' => money($v['expired_cost']),
                ];
            })() : null,
            'columns' => array_merge(['Medicine' => 'text', 'SKU' => 'muted', 'Category' => 'muted', 'Stock' => 'number'], $financial ? ['Cost value' => 'money'] : [], ['Retail value' => 'money']),
            'rows' => $page->through(fn ($m) => array_merge([$m->displayName(), $m->sku, $m->category?->name, $m->stock_quantity], $financial ? [$m->stock_cost] : [], [$m->stock_quantity * (float) $m->selling_price])),
            'noRange' => true,
        ]);
    }

    public function lowStock(Request $request): View|StreamedResponse
    {
        $query = Medicine::query()->active()->lowStock()->with(['category', 'supplier'])->orderBy('stock_quantity');

        if ($request->query('export') === 'csv') {
            return $this->csv('low-stock', ['SKU', 'Medicine', 'Stock', 'Reorder level', 'Supplier'], $query->get()->map(fn ($m) => [$m->sku, $m->displayName(), $m->stock_quantity, $m->reorderLevel(), $m->supplier?->name]));
        }

        $page = $query->paginate(25)->withQueryString();

        return view('reports.table', [
            'title' => 'Low-stock medicines',
            'columns' => ['Medicine' => 'text', 'SKU' => 'muted', 'Category' => 'muted', 'In stock' => 'number', 'Reorder level' => 'number', 'Preferred supplier' => 'muted'],
            'rows' => $page->through(fn ($m) => [$m->displayName(), $m->sku, $m->category?->name, $m->stock_quantity, $m->reorderLevel(), $m->supplier?->name]),
            'noRange' => true,
            'note' => 'Reorder level is the medicine\'s own minimum, or the store-wide threshold ('.settings('low_stock_threshold').') when none is set.',
        ]);
    }

    public function expiry(Request $request): View|StreamedResponse
    {
        $days = max(1, min(730, (int) $request->query('days', settings('expiry_alert_days', 90))));
        $scope = $request->query('scope') === 'expired' ? 'expired' : 'soon';

        $query = Batch::query()
            ->with(['medicine', 'supplier'])
            ->inStock()
            ->when($scope === 'expired', fn ($q) => $q->expired(), fn ($q) => $q->expiringWithin($days))
            ->orderBy('expiry_date');

        $value = (clone $query)->reorder()->toBase()->selectRaw('COALESCE(SUM(quantity * purchase_price),0) as v')->value('v');

        if ($request->query('export') === 'csv') {
            return $this->csv('expiry-'.$scope, ['Medicine', 'Batch', 'Expiry', 'Days left', 'Quantity', 'Cost value', 'Supplier'],
                $query->get()->map(fn ($b) => [$b->medicine->displayName(), $b->batch_number, $b->expiry_date->toDateString(), $b->daysToExpiry(), $b->quantity, round($b->quantity * (float) $b->purchase_price, 2), $b->supplier?->name]));
        }

        $page = $query->paginate(25)->withQueryString();

        return view('reports.expiry', ['batches' => $page, 'days' => $days, 'scope' => $scope, 'value' => (float) $value]);
    }

    public function expenses(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->expensesByCategory($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv('expenses-by-category', ['Category', 'Entries', 'Total'], $rows->map(fn ($r) => [$r->name, $r->count, round((float) $r->total, 2)]));
        }

        return view('reports.table', [
            'title' => 'Expenses by category',
            'from' => $from, 'to' => $to,
            'columns' => ['Category' => 'text', 'Entries' => 'number', 'Total' => 'money'],
            'rows' => $this->paginate($rows->map(fn ($r) => [$r->name, (int) $r->count, $r->total]), $request),
            'totals' => ['Total', $rows->sum('count'), $rows->sum('total')],
            'chart' => ['labels' => $rows->pluck('name')->values(), 'values' => $rows->pluck('total')->map(fn ($v) => round((float) $v, 2))->values()],
        ]);
    }

    public function payments(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $rows = $this->reports->paymentMethods($from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv('payment-methods', ['Method', 'Received', 'Refunded', 'Paid to suppliers', 'Transactions'], $rows->map(fn ($r) => [$r->name, round((float) $r->received, 2), round((float) $r->refunded, 2), round((float) $r->supplier_paid, 2), $r->transactions]));
        }

        return view('reports.table', [
            'title' => 'Payment-method summary',
            'from' => $from, 'to' => $to,
            'columns' => ['Method' => 'text', 'Received' => 'money', 'Refunded' => 'money', 'Paid to suppliers' => 'money', 'Net in' => 'money', 'Transactions' => 'number'],
            'rows' => $this->paginate($rows->map(fn ($r) => [$r->name, $r->received, $r->refunded, $r->supplier_paid, (float) $r->received - (float) $r->refunded - (float) $r->supplier_paid, (int) $r->transactions]), $request),
            'totals' => ['Total', $rows->sum('received'), $rows->sum('refunded'), $rows->sum('supplier_paid'), $rows->sum('received') - $rows->sum('refunded') - $rows->sum('supplier_paid'), $rows->sum('transactions')],
            'chart' => ['labels' => $rows->pluck('name')->values(), 'values' => $rows->pluck('received')->map(fn ($v) => round((float) $v, 2))->values()],
        ]);
    }

    public function profit(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $granularity = $this->defaultGranularity($from, $to);

        return view('reports.profit', [
            'from' => $from, 'to' => $to,
            'pnl' => $this->reports->profitAndLoss($from, $to),
            'summary' => $this->reports->salesSummary($from, $to),
            'trend' => $this->reports->salesTrend($from, $to, $granularity),
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $preset = $request->query('range');
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(), $today],
            'last7' => [$today->subDays(6), $today],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last30' => [$today->subDays(29), $today],
            'year' => [$today->startOfYear(), $today],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            default => [
                $this->parseDate($request->query('from')) ?? $today->startOfMonth(),
                $this->parseDate($request->query('to')) ?? $today,
            ],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        // Cap ranges at 5 years to keep queries bounded.
        if ($from->diffInDays($to) > 366 * 5) {
            $from = $to->subYears(5);
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function defaultGranularity(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $days = $from->diffInDays($to);

        return match (true) {
            $days <= 45 => 'day',
            $days <= 180 => 'week',
            $days <= 1100 => 'month',
            default => 'year',
        };
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator($rows->forPage($page, 25)->values(), $rows->count(), 25, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * @param  list<string>  $headers
     */
    private function csv(string $name, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, array_map([MedicineController::class, 'csvSafe'], (array) $row));
            }

            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
