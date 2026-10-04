<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers" description="Distributors and wholesalers, with what you owe each one.">
        <x-slot:actions>
            @can('suppliers.manage')<a href="{{ route('suppliers.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add supplier</a>@endcan
        </x-slot:actions>
    </x-page-header>
    @if ($totalDue > 0)
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-card">
            <x-icon name="info" class="size-5 text-brand-600" /> You owe suppliers <strong class="tabular">{{ money($totalDue) }}</strong> in total.
        </div>
    @endif
    <div class="card overflow-hidden">
        <x-filters placeholder="Name, contact, phone, email…">
            <x-filter-select name="balance" :options="['due' => 'Has balance due']" placeholder="Any balance" />
            <x-filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" placeholder="Any status" />
        </x-filters>
        @if ($suppliers->isEmpty())
            <x-empty icon="truck" title="No suppliers found" description="Add a supplier before recording purchases.">
                @can('suppliers.manage')<a href="{{ route('suppliers.create') }}" class="btn-primary">Add supplier</a>@endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><x-sort key="name" label="Supplier" /><th class="hidden md:table-cell">Contact</th><th class="text-right">Products</th><th class="text-right">Purchases</th><x-sort key="balance" label="Payable" align="right" /><th><span class="sr-only">View</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($suppliers as $s)
                        <tr>
                            <td><a href="{{ route('suppliers.show', $s) }}" class="font-medium text-ink hover:text-brand-700">{{ $s->name }}</a>
                                @unless ($s->is_active)<x-badge class="ml-1">Inactive</x-badge>@endunless</td>
                            <td class="hidden md:table-cell text-slate-500">{{ $s->contact_person ?: '—' }}<span class="block text-xs">{{ $s->phone }}</span></td>
                            <td class="tabular text-right">{{ $s->medicines_count }}</td>
                            <td class="tabular text-right">{{ $s->purchases_count }}</td>
                            <td class="tabular text-right font-medium {{ $s->balance > 0 ? 'text-amber-700' : ($s->balance < 0 ? 'text-emerald-700' : 'text-slate-400') }}">
                                {{ money($s->balance) }}@if($s->balance < 0)<span class="block text-[11px] font-normal">credit</span>@endif
                            </td>
                            <td class="text-right"><a href="{{ route('suppliers.show', $s) }}" class="btn-ghost btn-sm" aria-label="View {{ $s->name }}"><x-icon name="chevron-right" class="size-4" /></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $suppliers->links() }}
        @endif
    </div>
</x-layouts.app>
