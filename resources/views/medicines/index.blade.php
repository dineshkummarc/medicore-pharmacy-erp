<x-layouts.app title="Medicines">
    <x-page-header title="Medicines" description="Your catalogue with live stock levels, reorder alerts and expiry status.">
        <x-slot:actions>
            <a href="{{ route('medicines.export', request()->query()) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Export CSV</a>
            @can('medicines.create')
                <a href="{{ route('medicines.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add medicine</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @php $ids = $medicines->pluck('id')->all(); $canBulk = auth()->user()->can('medicines.update'); @endphp
    <div class="card overflow-hidden" x-data="bulkSelect">
        <x-filters placeholder="Name, generic, SKU or barcode…">
            <x-filter-select name="category" :options="$categories->pluck('name', 'id')" placeholder="All categories" />
            <x-filter-select name="stock" :options="['in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock', 'expiring' => 'Expiring soon', 'expired' => 'Has expired stock']" placeholder="Any stock" />
            <x-filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" placeholder="Any status" />
            @can('medicines.delete')
                <x-filter-select name="archived" :options="['1' => 'Archived only']" placeholder="Current" />
            @endcan
        </x-filters>

        @if ($canBulk)
            <form x-cloak x-show="selected.length" method="POST" action="{{ route('medicines.bulk') }}" class="flex flex-wrap items-center gap-3 border-b border-brand-100 bg-brand-50/60 px-5 py-2.5">
                @csrf
                <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <span class="text-sm font-medium text-brand-800"><span x-text="selected.length"></span> selected</span>
                <button name="action" value="activate" class="btn-secondary btn-sm">Activate</button>
                <button name="action" value="deactivate" class="btn-secondary btn-sm">Deactivate</button>
                @can('medicines.delete')
                    <button name="action" value="archive" class="btn-sm btn text-rose-700 hover:bg-rose-50" onclick="return confirm('Archive the selected medicines? Their history is kept.')">Archive</button>
                @endcan
                <button type="button" class="btn-ghost btn-sm ml-auto" @click="selected = []">Clear</button>
            </form>
        @endif

        @if ($medicines->isEmpty())
            <x-empty icon="pill" title="No medicines found" :description="request()->hasAny(['q', 'category', 'stock', 'status']) ? 'Try a different search or clear the filters.' : 'Add your first medicine to start tracking stock.'">
                @can('medicines.create')<a href="{{ route('medicines.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add medicine</a>@endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                    <tr>
                        @if ($canBulk)
                            <th class="w-10"><input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-label="Select all"
                                                    @change="toggleAll($event, @js($ids))" :checked="selected.length === {{ count($ids) }}"></th>
                        @endif
                        <x-sort key="name" label="Medicine" />
                        <x-sort key="sku" label="SKU" />
                        <th class="hidden lg:table-cell">Category</th>
                        <x-sort key="price" label="Price" align="right" />
                        <x-sort key="stock" label="Stock" align="right" />
                        <th>Status</th>
                        <th class="w-10"><span class="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($medicines as $medicine)
                        <tr>
                            @if ($canBulk)
                                <td><input type="checkbox" value="{{ $medicine->id }}" x-model.number="selected" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-label="Select {{ $medicine->name }}"></td>
                            @endif
                            <td class="max-w-xs">
                                <a href="{{ route('medicines.show', $medicine) }}" class="font-medium text-ink hover:text-brand-700">{{ $medicine->displayName() }}</a>
                                @if ($medicine->requires_prescription)<x-badge tone="violet" class="ml-1">Rx</x-badge>@endif
                                <p class="truncate text-xs text-slate-500">{{ $medicine->generic_name ?: '—' }} @if($medicine->manufacturer) · {{ $medicine->manufacturer->name }} @endif</p>
                            </td>
                            <td class="font-mono text-xs text-slate-500">{{ $medicine->sku }}</td>
                            <td class="hidden lg:table-cell text-slate-500">{{ $medicine->category?->name ?? '—' }}</td>
                            <td class="tabular text-right">{{ money($medicine->selling_price) }}<span class="block text-[11px] text-slate-400">per {{ $medicine->unit }}</span></td>
                            <td class="tabular text-right">
                                <span class="font-medium {{ $medicine->stock_quantity <= 0 ? 'text-rose-600' : ($medicine->isLowStock() ? 'text-amber-600' : 'text-ink') }}">{{ number_format($medicine->stock_quantity) }}</span>
                                @if ($medicine->stock_quantity <= 0)
                                    <span class="block text-[11px] text-rose-500">Out of stock</span>
                                @elseif ($medicine->isLowStock())
                                    <span class="block text-[11px] text-amber-600">Low · reorder {{ $medicine->reorderLevel() }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($medicine->trashed())
                                    <x-badge tone="gray">Archived</x-badge>
                                @else
                                    <x-badge :tone="$medicine->is_active ? 'green' : 'gray'" dot>{{ $medicine->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($medicine->trashed())
                                    <form method="POST" action="{{ route('medicines.restore', $medicine) }}">@csrf<button class="btn-ghost btn-sm" title="Restore"><x-icon name="refresh" class="size-4" /></button></form>
                                @else
                                    <a href="{{ route('medicines.show', $medicine) }}" class="btn-ghost btn-sm" aria-label="View {{ $medicine->name }}"><x-icon name="chevron-right" class="size-4" /></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $medicines->links() }}
        @endif
    </div>
</x-layouts.app>
