<x-layouts.app title="Customers">
    <x-page-header title="Customers" description="Customer records, purchase history and outstanding balances.">
        <x-slot:actions>
            @can('customers.manage')<a href="{{ route('customers.create') }}" class="btn-primary"><x-icon name="user-plus" class="size-4" /> Add customer</a>@endcan
        </x-slot:actions>
    </x-page-header>

    @if ($totalDue > 0)
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-icon name="warning" class="size-5 text-amber-500" />
            Customers owe <strong class="tabular">{{ money($totalDue) }}</strong> in total.
            <a href="{{ route('customers.index', ['balance' => 'due']) }}" class="ml-auto font-medium underline">Show customers with dues</a>
        </div>
    @endif

    <div class="card overflow-hidden">
        <x-filters placeholder="Name, phone or email…">
            <x-filter-select name="balance" :options="['due' => 'Has balance due']" placeholder="Any balance" />
            <x-filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" placeholder="Any status" />
        </x-filters>
        @if ($customers->isEmpty())
            <x-empty icon="users" title="No customers found" description="Customers can be added here or directly from the POS.">
                @can('customers.manage')<a href="{{ route('customers.create') }}" class="btn-primary">Add customer</a>@endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><x-sort key="name" label="Customer" /><th class="hidden md:table-cell">Contact</th><th class="text-right">Invoices</th><th class="hidden sm:table-cell text-right">Lifetime</th><x-sort key="balance" label="Balance due" align="right" /><th><span class="sr-only">View</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($customers as $c)
                        <tr>
                            <td>
                                <a href="{{ route('customers.show', $c) }}" class="font-medium text-ink hover:text-brand-700">{{ $c->name }}</a>
                                @unless ($c->is_active)<x-badge class="ml-1">Inactive</x-badge>@endunless
                                <p class="text-xs text-slate-500 md:hidden">{{ $c->phone }}</p>
                            </td>
                            <td class="hidden md:table-cell text-slate-500">{{ $c->phone ?: '—' }}<span class="block text-xs">{{ $c->email }}</span></td>
                            <td class="tabular text-right">{{ number_format($c->sales_count) }}</td>
                            <td class="hidden sm:table-cell tabular text-right">{{ money($c->lifetime_value) }}</td>
                            <td class="tabular text-right font-medium {{ $c->balance > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ money($c->balance) }}</td>
                            <td class="text-right"><a href="{{ route('customers.show', $c) }}" class="btn-ghost btn-sm" aria-label="View {{ $c->name }}"><x-icon name="chevron-right" class="size-4" /></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        @endif
    </div>
</x-layouts.app>
