<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\SupplierRequest;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SupplierController extends Controller implements HasMiddleware
{
    use HandlesTableQueries;

    public function __construct(private readonly ActivityLogger $logger) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:suppliers.view', only: ['index', 'show']),
            new Middleware('can:suppliers.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $term = $request->string('q')->limit(80)->value();

        $query = Supplier::query()
            ->withCount(['purchases', 'medicines'])
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('balance') === 'due', fn ($q) => $q->where('balance', '>', 0));

        $this->applySort($query, $request, ['name' => 'name', 'balance' => 'balance', 'created' => 'created_at'], 'name', 'asc');

        return view('suppliers.index', [
            'suppliers' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['q', 'status', 'balance']),
            'totalDue' => Supplier::query()->where('balance', '>', 0)->sum('balance'),
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated());
        $this->logger->log('supplier.created', "Supplier {$supplier->name} created", $supplier);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier): View
    {
        return view('suppliers.show', [
            'supplier' => $supplier,
            'purchases' => $supplier->purchases()->latest('purchase_date')->latest('id')->paginate(10)->withQueryString(),
            'payments' => $supplier->payments()->with(['method', 'purchase'])->latest('paid_at')->limit(10)->get(),
            'medicines' => $supplier->medicines()->orderBy('name')->limit(20)->get(['id', 'name', 'strength', 'stock_quantity']),
            'stats' => [
                'count' => $supplier->purchases()->where('status', 'received')->count(),
                'total' => $supplier->purchases()->where('status', 'received')->sum('total'),
                'last' => $supplier->purchases()->max('purchase_date'),
            ],
            'methods' => PaymentMethod::query()->active()->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->fill($request->validated())->save();

        if ($supplier->wasChanged()) {
            $this->logger->log('supplier.updated', "Supplier {$supplier->name} updated", $supplier, $this->logger->changes($supplier));
        }

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if (Money::toCents($supplier->balance) !== 0) {
            return back()->with('error', 'Settle the supplier balance of '.money($supplier->balance).' before archiving.');
        }

        $supplier->delete();
        $this->logger->log('supplier.archived', "Supplier {$supplier->name} archived", $supplier);

        return redirect()->route('suppliers.index')->with('success', 'Supplier archived. Purchase history is kept.');
    }

    public function pay(PaymentRequest $request, Supplier $supplier, PaymentService $payments): RedirectResponse
    {
        $data = $request->validated();
        $payments->paySupplier($supplier, $data['amount'], (int) $data['payment_method_id'], $request->user(), $data['reference'] ?? null, $data['note'] ?? null);

        return back()->with('success', 'Payment of '.money($data['amount']).' recorded.');
    }
}
