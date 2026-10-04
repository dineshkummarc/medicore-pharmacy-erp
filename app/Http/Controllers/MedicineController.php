<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\MedicineRequest;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicineController extends Controller implements HasMiddleware
{
    use HandlesTableQueries;

    public function __construct(private readonly ActivityLogger $logger) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:medicines.view', only: ['index', 'show']),
            new Middleware('can:medicines.create', only: ['create', 'store']),
            new Middleware('can:medicines.update', only: ['edit', 'update']),
            new Middleware('can:medicines.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request)->with(['category', 'manufacturer']);

        $this->applySort($query, $request, [
            'name' => 'name',
            'sku' => 'sku',
            'stock' => 'stock_quantity',
            'price' => 'selling_price',
            'created' => 'created_at',
        ], 'name', 'asc');

        return view('medicines.index', [
            'medicines' => $query->paginate($this->perPage($request))->withQueryString(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'category', 'status', 'stock', 'archived']),
        ]);
    }

    public function create(): View
    {
        return view('medicines.form', ['medicine' => new Medicine([
            'unit' => 'tablet',
            'is_active' => true,
            'tax_rate' => settings('default_tax_rate', 0),
            'min_stock_level' => 0,
        ])] + $this->formOptions());
    }

    public function store(MedicineRequest $request): RedirectResponse
    {
        $medicine = Medicine::query()->create($request->validated());
        $this->logger->log('medicine.created', "Medicine {$medicine->name} created", $medicine, ['sku' => $medicine->sku]);

        $redirect = Gate::allows('inventory.adjust')
            ? redirect()->route('batches.create', $medicine)->with('success', 'Medicine created. Add opening stock now, or receive it through a purchase.')
            : redirect()->route('medicines.show', $medicine)->with('success', 'Medicine created.');

        return $redirect;
    }

    public function show(Medicine $medicine): View
    {
        $medicine->load(['category', 'manufacturer', 'supplier']);

        return view('medicines.show', [
            'medicine' => $medicine,
            'batches' => $medicine->batches()->orderByRaw('quantity > 0 desc')->orderBy('expiry_date')->get(),
            'movements' => $medicine->stockMovements()->with(['user', 'batch'])->latest('id')->limit(15)->get(),
            'sellable' => (int) $medicine->batches()->sellable()->sum('quantity'),
            'soldLast30' => (int) $medicine->saleItems()
                ->whereHas('sale', fn ($q) => $q->where('sold_at', '>=', now()->subDays(30)))
                ->sum('quantity'),
        ]);
    }

    public function edit(Medicine $medicine): View
    {
        return view('medicines.form', ['medicine' => $medicine] + $this->formOptions());
    }

    public function update(MedicineRequest $request, Medicine $medicine): RedirectResponse
    {
        $medicine->fill($request->validated())->save();

        if ($medicine->wasChanged()) {
            $this->logger->log('medicine.updated', "Medicine {$medicine->name} updated", $medicine, $this->logger->changes($medicine));
        }

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine updated.');
    }

    /**
     * Archive (soft delete). History, batches and invoices remain intact.
     */
    public function destroy(Medicine $medicine): RedirectResponse
    {
        $medicine->delete();
        $this->logger->log('medicine.archived', "Medicine {$medicine->name} archived", $medicine);

        return redirect()->route('medicines.index')->with('success', "{$medicine->name} archived. It can be restored from the archived filter.");
    }

    public function restore(Medicine $medicine): RedirectResponse
    {
        $medicine->restore();
        $this->logger->log('medicine.restored', "Medicine {$medicine->name} restored", $medicine);

        return redirect()->route('medicines.show', $medicine)->with('success', 'Medicine restored.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'archive'])],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        Gate::authorize($validated['action'] === 'archive' ? 'medicines.delete' : 'medicines.update');

        // Global tenant scope guarantees only this pharmacy's rows are touched.
        $medicines = Medicine::query()->whereIn('id', $validated['ids'])->get();

        foreach ($medicines as $medicine) {
            match ($validated['action']) {
                'activate' => $medicine->forceFill(['is_active' => true])->save(),
                'deactivate' => $medicine->forceFill(['is_active' => false])->save(),
                'archive' => $medicine->delete(),
            };
        }

        $this->logger->log('medicine.bulk_'.$validated['action'], ucfirst($validated['action'])." {$medicines->count()} medicines", null, [
            'ids' => $medicines->pluck('id')->all(),
        ]);

        return back()->with('success', "{$medicines->count()} medicine(s) updated.");
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)->with(['category', 'manufacturer'])->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SKU', 'Barcode', 'Name', 'Generic', 'Strength', 'Category', 'Manufacturer', 'Unit', 'Purchase price', 'Selling price', 'Tax %', 'Stock', 'Reorder level', 'Status']);

            $query->chunk(500, function ($medicines) use ($out) {
                foreach ($medicines as $m) {
                    fputcsv($out, array_map([self::class, 'csvSafe'], [
                        $m->sku, $m->barcode, $m->name, $m->generic_name, $m->strength,
                        $m->category?->name, $m->manufacturer?->name, $m->unit,
                        $m->purchase_price, $m->selling_price, $m->tax_rate,
                        $m->stock_quantity, $m->reorderLevel(), $m->is_active ? 'Active' : 'Inactive',
                    ]));
                }
            });

            fclose($out);
        }, 'medicines-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function barcode(Medicine $medicine): View
    {
        abort_unless($medicine->barcode || $medicine->sku, 404);

        return view('medicines.barcode', ['medicine' => $medicine, 'copies' => 24]);
    }

    /**
     * Lightweight JSON search used by the purchase form.
     */
    public function lookup(Request $request): JsonResponse
    {
        $medicines = Medicine::query()
            ->active()
            ->search($request->string('q')->limit(80)->value())
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'strength', 'generic_name', 'sku', 'barcode', 'unit', 'purchase_price', 'selling_price', 'tax_rate', 'stock_quantity']);

        return response()->json($medicines->map(fn (Medicine $m) => [
            'id' => $m->id,
            'name' => $m->displayName(),
            'generic_name' => $m->generic_name,
            'sku' => $m->sku,
            'unit' => $m->unit,
            'purchase_price' => $m->purchase_price,
            'selling_price' => $m->selling_price,
            'tax_rate' => $m->tax_rate,
            'stock' => $m->stock_quantity,
        ]));
    }

    private function filteredQuery(Request $request)
    {
        $query = Medicine::query()->search($request->string('q')->limit(80)->value());

        if ($request->query('archived') === '1' && $request->user()->can('medicines.delete')) {
            $query->onlyTrashed();
        }

        if ($category = $request->integer('category')) {
            $query->where('category_id', $category);
        }

        match ($request->query('status')) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        match ($request->query('stock')) {
            'low' => $query->lowStock()->where('stock_quantity', '>', 0),
            'out' => $query->where('stock_quantity', '<=', 0),
            'in' => $query->where('stock_quantity', '>', 0),
            'expiring' => $query->whereHas('batches', fn ($b) => $b->inStock()->expiringWithin((int) settings('expiry_alert_days', 90))),
            'expired' => $query->whereHas('batches', fn ($b) => $b->inStock()->expired()),
            default => null,
        };

        return $query;
    }

    /**
     * Prevent CSV/formula injection when the file is opened in a spreadsheet.
     */
    public static function csvSafe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'manufacturers' => Manufacturer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => config('pharmacy.units'),
        ];
    }
}
