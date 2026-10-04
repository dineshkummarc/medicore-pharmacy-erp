<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\StoreReturnRequest;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleReturnController extends Controller
{
    use HandlesTableQueries;

    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $term = $request->string('q')->limit(80)->value();

        $returns = SaleReturn::query()
            ->with(['sale', 'customer', 'user'])
            ->withCount('items')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('return_number', 'like', $like)
                    ->orWhereHas('sale', fn ($s) => $s->where('invoice_number', 'like', $like)));
            })
            ->when($from, fn ($q) => $q->where('returned_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('returned_at', '<=', $to))
            ->latest('returned_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('returns.index', ['returns' => $returns, 'filters' => $request->only(['q', 'from', 'to'])]);
    }

    /**
     * Step 1: find the original invoice. Step 2: pick items to return.
     */
    public function create(Request $request): View
    {
        $invoice = $request->string('invoice')->trim()->limit(40)->value();
        $sale = null;

        if ($invoice !== '') {
            $sale = Sale::query()->where('invoice_number', $invoice)->first()
                ?? Sale::query()->where('invoice_number', 'like', '%'.addcslashes($invoice, '%_\\'))->latest('id')->first();
            $sale?->load(['items', 'customer']);
        }

        return view('returns.create', [
            'invoice' => $invoice,
            'sale' => $sale,
            'methods' => PaymentMethod::query()->active()->get(),
        ]);
    }

    public function store(StoreReturnRequest $request, Sale $sale, ReturnService $returns): RedirectResponse
    {
        $return = $returns->create($sale, $request->validated(), $request->user());

        return redirect()->route('returns.show', $return)->with('success', "Return {$return->return_number} processed.");
    }

    public function show(SaleReturn $saleReturn): View
    {
        return view('returns.show', [
            'return' => $saleReturn->load(['sale', 'customer', 'user', 'items.saleItem', 'refundPayment.method']),
        ]);
    }
}
