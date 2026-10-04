<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CustomerController extends Controller implements HasMiddleware
{
    use HandlesTableQueries;

    public function __construct(private readonly ActivityLogger $logger) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:customers.view', only: ['index', 'show']),
            new Middleware('can:customers.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $term = $request->string('q')->limit(80)->value();

        $query = Customer::query()
            ->withCount('sales')
            ->withSum('sales as lifetime_value', 'total')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('balance') === 'due', fn ($q) => $q->where('balance', '>', 0));

        $this->applySort($query, $request, [
            'name' => 'name',
            'balance' => 'balance',
            'created' => 'created_at',
        ], 'name', 'asc');

        return view('customers.index', [
            'customers' => $query->paginate($this->perPage($request))->withQueryString(),
            'filters' => $request->only(['q', 'status', 'balance']),
            'totalDue' => Customer::query()->where('balance', '>', 0)->sum('balance'),
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer(['is_active' => true])]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());
        $this->logger->log('customer.created', "Customer {$customer->name} created", $customer);

        return redirect()->route('customers.show', $customer)->with('success', 'Customer created.');
    }

    /**
     * Inline creation from the POS screen.
     */
    public function quickStore(CustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create($request->validated());
        $this->logger->log('customer.created', "Customer {$customer->name} created from POS", $customer);

        return response()->json(['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone, 'balance' => '0.00'], 201);
    }

    public function show(Request $request, Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer,
            'sales' => $customer->sales()->with('user')->latest('sold_at')->paginate(10, ['*'], 'sales_page')->withQueryString(),
            'payments' => $customer->payments()->with(['method', 'sale'])->latest('paid_at')->limit(10)->get(),
            'returns' => $customer->returns()->with('sale')->latest('returned_at')->limit(10)->get(),
            'stats' => [
                'count' => $customer->sales()->count(),
                'lifetime' => $customer->sales()->sum('total'),
                'returned' => $customer->sales()->sum('returned_amount'),
                'last' => $customer->sales()->max('sold_at'),
            ],
            'methods' => PaymentMethod::query()->active()->get(),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->fill($request->validated())->save();

        if ($customer->wasChanged()) {
            $this->logger->log('customer.updated', "Customer {$customer->name} updated", $customer, $this->logger->changes($customer));
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if (Money::toCents($customer->balance) > 0) {
            return back()->with('error', 'This customer still owes '.money($customer->balance).'. Settle the balance before archiving.');
        }

        $customer->delete();
        $this->logger->log('customer.archived', "Customer {$customer->name} archived", $customer);

        return redirect()->route('customers.index')->with('success', 'Customer archived. Their invoices are kept.');
    }

    public function collect(PaymentRequest $request, Customer $customer, PaymentService $payments): RedirectResponse
    {
        $data = $request->validated();
        $payments->collectFromCustomer($customer, $data['amount'], (int) $data['payment_method_id'], $request->user(), $data['reference'] ?? null, $data['note'] ?? null);

        return back()->with('success', 'Payment of '.money($data['amount']).' recorded.');
    }

    /**
     * Customer search for the POS screen.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = $request->string('q')->limit(80)->value();
        $like = '%'.addcslashes($term, '%_\\').'%';

        $customers = Customer::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'balance']);

        return response()->json($customers);
    }
}
