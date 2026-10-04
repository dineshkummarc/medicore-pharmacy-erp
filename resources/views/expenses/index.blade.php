<x-layouts.app title="Expenses">
    <x-page-header title="Expenses" description="Running costs of the pharmacy, included in profit reports.">
        <x-slot:actions>
            @can('expenses.manage')
                <a href="{{ route('expense-categories.index') }}" class="btn-secondary"><x-icon name="tag" class="size-4" /> Categories</a>
                <a href="{{ route('expenses.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Record expense</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-hidden">
        <x-filters placeholder="Description or reference…">
            <x-filter-select name="category" :options="$categories->pluck('name', 'id')" placeholder="All categories" />
            <x-filter-select name="method" :options="$methods->pluck('name', 'id')" placeholder="Any method" />
            <x-date-range />
        </x-filters>
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-2.5 text-sm">
            <span class="text-slate-500">Total for current filters</span>
            <span class="tabular font-semibold">{{ money($total) }}</span>
        </div>
        @if ($expenses->isEmpty())
            <x-empty icon="banknotes" title="No expenses found" description="Record rent, utilities, salaries and other costs." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><x-sort key="date" label="Date" /><th>Description</th><th>Category</th><th class="hidden md:table-cell">Method</th><th class="hidden lg:table-cell">Recorded by</th><x-sort key="amount" label="Amount" align="right" /><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($expenses as $e)
                        <tr>
                            <td class="whitespace-nowrap text-slate-500">{{ $e->expense_date->format('d M Y') }}</td>
                            <td class="max-w-xs"><span class="text-ink">{{ $e->description }}</span>@if($e->reference)<span class="block text-xs text-slate-500">Ref {{ $e->reference }}</span>@endif</td>
                            <td><x-badge>{{ $e->category?->name }}</x-badge></td>
                            <td class="hidden md:table-cell text-slate-500">{{ $e->method?->name }}</td>
                            <td class="hidden lg:table-cell text-slate-500">{{ $e->user?->name }}</td>
                            <td class="tabular text-right font-medium">{{ money($e->amount) }}</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($e->receipt_path)<a href="{{ route('expenses.receipt', $e) }}" class="btn-ghost btn-sm" title="Download receipt"><x-icon name="download" class="size-4" /></a>@endif
                                @can('expenses.manage')
                                    <a href="{{ route('expenses.edit', $e) }}" class="btn-ghost btn-sm" aria-label="Edit"><x-icon name="pencil" class="size-4" /></a>
                                    <x-confirm :action="route('expenses.destroy', $e)" method="DELETE" title="Delete this expense?" button="Delete" message="It will be removed from reports. The deletion is recorded in the audit log.">
                                        <button type="button" class="btn-ghost btn-sm text-rose-600" aria-label="Delete"><x-icon name="trash" class="size-4" /></button>
                                    </x-confirm>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $expenses->links() }}
        @endif
    </div>
</x-layouts.app>
