<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseController extends Controller implements HasMiddleware
{
    use HandlesTableQueries;

    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly ActivityLogger $logger,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:purchases.view', only: ['index', 'show']),
            new Middleware('can:purchases.create', only: ['create', 'store']),
            new Middleware('can:purchases.cancel', only: ['edit', 'update']),
        ];
    }

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(PurchaseStatus::class)],
            'payment' => ['nullable', Rule::enum(PaymentStatus::class)],
        ]);
        [$from, $to] = $this->dateRange($request);
        $term = $request->string('q')->limit(80)->value();

        $query = Purchase::query()
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference_no', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('supplier_invoice_no', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($request->integer('supplier'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('payment'), fn ($q, $s) => $q->where('payment_status', $s))
            ->when($from, fn ($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('purchase_date', '<=', $to));

        $this->applySort($query, $request, ['date' => 'purchase_date', 'total' => 'total', 'reference' => 'reference_no'], 'date');

        return view('purchases.index', [
            'purchases' => $query->paginate($this->perPage($request))->withQueryString(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'supplier', 'status', 'payment', 'from', 'to']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('purchases.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::query()->active()->get(),
            'selectedSupplier' => $request->integer('supplier') ?: null,
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchases->create($request->validated(), $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', "Purchase {$purchase->reference_no} recorded and stock updated.");
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['supplier', 'user', 'canceller', 'items.medicine', 'items.batch', 'payments.method']);

        return view('purchases.show', [
            'purchase' => $purchase,
            'cancellable' => ! $purchase->isCancelled()
                && $purchase->batches()->whereColumn('quantity', '!=', 'quantity_received')->doesntExist(),
        ]);
    }

    /**
     * Only header fields are editable; line items are immutable once stock
     * has been received (cancel and re-enter instead).
     */
    public function edit(Purchase $purchase): View|RedirectResponse
    {
        if ($purchase->isCancelled()) {
            return redirect()->route('purchases.show', $purchase)->with('error', 'Cancelled purchases cannot be edited.');
        }

        return view('purchases.edit', ['purchase' => $purchase]);
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        abort_if($purchase->isCancelled(), 422, 'Cancelled purchases cannot be edited.');

        $data = $request->validate([
            'supplier_invoice_no' => ['nullable', 'string', 'max:80'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchase->forceFill($data)->save();

        if ($purchase->wasChanged()) {
            $this->logger->log('purchase.updated', "Purchase {$purchase->reference_no} updated", $purchase, $this->logger->changes($purchase));
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase updated.');
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $this->purchases->cancel($purchase, $data['reason'], $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase cancelled and stock reversed.');
    }
}
