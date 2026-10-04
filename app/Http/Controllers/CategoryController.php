<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ActivityLogger;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        return view('catalog.index', [
            'type' => 'categories',
            'title' => 'Categories',
            'items' => Category::query()
                ->withCount('medicines')
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.addcslashes($request->string('q')->limit(80)->value(), '%_\\').'%'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::query()->create($this->validated($request));
        $this->logger->log('category.created', "Category {$category->name} created", $category);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->fill($this->validated($request, $category))->save();
        $this->logger->log('category.updated', "Category {$category->name} updated", $category, $this->logger->changes($category));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();
        $this->logger->log('category.archived', "Category {$category->name} archived", $category);

        return back()->with('success', 'Category archived. Medicines keep their history.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', TenantRule::unique('categories', 'name')->ignore($category?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
