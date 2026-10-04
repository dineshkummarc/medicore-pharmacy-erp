<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    use HandlesTableQueries;

    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
            'payment' => ['nullable', Rule::enum(PaymentStatus::class)],
        ]);
        [$from, $to] = $this->dateRange($request);
        $term = $request->string('q')->limit(80)->value();

        $query = Sale::query()
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('phone', 'like', $like)));
            })
            ->when($request->integer('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->integer('customer'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('payment'), fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->integer('method'), fn ($q, $id) => $q->whereHas('payments', fn ($p) => $p->where('payment_method_id', $id)))
            ->when($from, fn ($q) => $q->where('sold_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('sold_at', '<=', $to));

        $summary = (clone $query)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(returned_amount),0) as returned')->first();

        $query->with(['customer', 'user'])->withCount('items');
        $this->applySort($query, $request, ['date' => 'sold_at', 'total' => 'total', 'invoice' => 'invoice_number'], 'date');

        return view('sales.index', [
            'sales' => $query->paginate($this->perPage($request))->withQueryString(),
            'summary' => $summary,
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::query()->orderBy('sort_order')->get(['id', 'name']),
            'filters' => $request->only(['q', 'user', 'status', 'payment', 'method', 'from', 'to', 'customer']),
        ]);
    }

    public function show(Sale $sale): View
    {
        return view('sales.show', ['sale' => $this->loadInvoice($sale)]);
    }

    public function print(Sale $sale): View
    {
        return view('invoices.print', ['sale' => $this->loadInvoice($sale)]);
    }

    public function pdf(Sale $sale): Response
    {
        $pdf = Pdf::loadView('invoices.pdf', ['sale' => $this->loadInvoice($sale)])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);

        return $pdf->download("{$sale->invoice_number}.pdf");
    }

    private function loadInvoice(Sale $sale): Sale
    {
        return $sale->load(['customer', 'user', 'items', 'payments.method', 'returns.user', 'returns.items']);
    }
}
