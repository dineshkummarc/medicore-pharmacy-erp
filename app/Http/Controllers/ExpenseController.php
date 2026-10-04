<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesTableQueries;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller implements HasMiddleware
{
    use HandlesTableQueries;

    /** Receipts live on the private disk and are only served through receipt(). */
    private const DISK = 'local';

    public function __construct(private readonly ActivityLogger $logger) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:expenses.view', only: ['index']),
            new Middleware('can:expenses.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $term = $request->string('q')->limit(80)->value();

        $query = Expense::query()
            ->with(['category', 'method', 'user'])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.addcslashes($term, '%_\\').'%')
                ->orWhere('reference', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->integer('method'), fn ($q, $id) => $q->where('payment_method_id', $id))
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to));

        $total = (clone $query)->sum('amount');
        $this->applySort($query, $request, ['date' => 'expense_date', 'amount' => 'amount'], 'date');

        return view('expenses.index', [
            'expenses' => $query->paginate($this->perPage($request))->withQueryString(),
            'total' => $total,
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::query()->orderBy('sort_order')->get(['id', 'name']),
            'filters' => $request->only(['q', 'category', 'method', 'from', 'to']),
        ]);
    }

    public function create(): View
    {
        return view('expenses.form', ['expense' => new Expense(['expense_date' => today()])] + $this->options());
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense($request->safe()->except('receipt'));
        $expense->user_id = $request->user()->id;
        $expense->receipt_path = $this->storeReceipt($request);
        $expense->save();

        $this->logger->log('expense.created', 'Expense of '.money($expense->amount).' recorded', $expense, ['amount' => $expense->amount]);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.form', ['expense' => $expense] + $this->options());
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->fill($request->safe()->except('receipt'));

        if ($path = $this->storeReceipt($request)) {
            if ($expense->receipt_path) {
                Storage::disk(self::DISK)->delete($expense->receipt_path);
            }
            $expense->receipt_path = $path;
        }

        $expense->save();
        $this->logger->log('expense.updated', 'Expense updated', $expense, $this->logger->changes($expense));

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();
        $this->logger->log('expense.deleted', 'Expense of '.money($expense->amount).' deleted', $expense, ['amount' => $expense->amount]);

        return back()->with('success', 'Expense deleted.');
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->receipt_path && Storage::disk(self::DISK)->exists($expense->receipt_path), 404);

        return Storage::disk(self::DISK)->download($expense->receipt_path, 'receipt-'.$expense->id.'.'.pathinfo($expense->receipt_path, PATHINFO_EXTENSION));
    }

    private function storeReceipt(ExpenseRequest $request): ?string
    {
        if (! $request->hasFile('receipt')) {
            return null;
        }

        // Random server-side name; the client's filename is never used.
        return $request->file('receipt')->store('receipts/'.$request->user()->pharmacy_id, self::DISK);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::query()->active()->get(['id', 'name']),
        ];
    }
}
