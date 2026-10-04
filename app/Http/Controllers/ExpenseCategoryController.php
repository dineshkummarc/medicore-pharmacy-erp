<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Services\ActivityLogger;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        return view('catalog.index', [
            'type' => 'expense-categories',
            'title' => 'Expense categories',
            'items' => ExpenseCategory::query()->withCount('expenses')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = ExpenseCategory::query()->create($this->validated($request));
        $this->logger->log('expense_category.created', "Expense category {$category->name} created", $category);

        return back()->with('success', 'Expense category added.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->fill($this->validated($request, $expenseCategory))->save();

        return back()->with('success', 'Expense category updated.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->delete();
        $this->logger->log('expense_category.archived', "Expense category {$expenseCategory->name} archived", $expenseCategory);

        return back()->with('success', 'Expense category archived.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', TenantRule::unique('expense_categories', 'name')->ignore($category?->id)->whereNull('deleted_at')],
            'is_active' => ['boolean'],
        ]);
    }
}
