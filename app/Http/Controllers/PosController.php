<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Batch;
use App\Models\Medicine;
use App\Models\PaymentMethod;
use App\Services\SaleService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('pos.index', [
            'methods' => PaymentMethod::query()->active()->get(['id', 'name', 'code']),
            'config' => [
                'taxEnabled' => app(SettingsService::class)->bool('tax_enabled'),
                'currency' => settings('currency_symbol', '$'),
                'canDiscount' => $user->can('sales.discount'),
                'maxDiscount' => $user->isAdmin() ? 100 : (float) settings('max_discount_percent', 0),
                'canCredit' => $user->can('sales.credit'),
                'canCreateCustomer' => $user->can('customers.manage'),
                'expiryAlertDays' => (int) settings('expiry_alert_days', 90),
            ],
        ]);
    }

    /**
     * Medicine search by name / generic / SKU / exact barcode. Only returns
     * what the POS needs; never purchase costs.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->limit(80)->value();

        if ($term === '') {
            return response()->json([]);
        }

        $medicines = Medicine::query()
            ->active()
            ->search($term)
            ->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 ELSE 1 END', [$term, strtoupper($term)])
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'strength', 'generic_name', 'sku', 'barcode', 'unit', 'selling_price', 'tax_rate', 'requires_prescription']);

        // One aggregate query for sellable stock and nearest expiry.
        $stock = Batch::query()
            ->sellable()
            ->whereIn('medicine_id', $medicines->pluck('id'))
            ->groupBy('medicine_id')
            ->select('medicine_id', DB::raw('SUM(quantity) as sellable'), DB::raw('MIN(expiry_date) as next_expiry'))
            ->get()
            ->keyBy('medicine_id');

        return response()->json($medicines->map(fn (Medicine $m) => [
            'id' => $m->id,
            'name' => $m->displayName(),
            'generic_name' => $m->generic_name,
            'sku' => $m->sku,
            'barcode' => $m->barcode,
            'unit' => $m->unit,
            'price' => $m->selling_price,
            'tax_rate' => $m->tax_rate,
            'rx' => $m->requires_prescription,
            'stock' => (int) ($stock[$m->id]->sellable ?? 0),
            'next_expiry' => isset($stock[$m->id]) ? substr((string) $stock[$m->id]->next_expiry, 0, 10) : null,
        ]));
    }

    public function store(StoreSaleRequest $request): JsonResponse|RedirectResponse
    {
        $sale = $this->sales->create($request->validated(), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total' => $sale->total,
                'paid' => $sale->paid_amount,
                'due' => $sale->dueAmount(),
                'invoice_url' => route('sales.show', $sale),
                'print_url' => route('sales.print', $sale),
            ], 201);
        }

        return redirect()->route('sales.show', $sale)->with('success', "Sale {$sale->invoice_number} completed.");
    }
}
