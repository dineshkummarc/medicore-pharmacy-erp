<x-layouts.app title="Purchases">
    <x-page-header title="Purchases" description="Stock received from suppliers. Every line creates a traceable batch.">
        <x-slot:actions>
            @can('purchases.create')<a href="{{ route('purchases.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New purchase</a>@endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-hidden">
        <x-filters placeholder="Reference or supplier invoice…">
            <x-filter-select name="supplier" :options="$suppliers->pluck('name', 'id')" placeholder="All suppliers" />
            <x-filter-select name="payment" :options="['paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid']" placeholder="Any payment" />
            <x-filter-select name="status" :options="['received' => 'Received', 'cancelled' => 'Cancelled']" placeholder="Any status" />
            <x-date-range />
        </x-filters>
        @if ($purchases->isEmpty())
            <x-empty icon="inbox" title="No purchases found" description="Record a purchase when a delivery arrives to add stock.">
                @can('purchases.create')<a href="{{ route('purchases.create') }}" class="btn-primary">New purchase</a>@endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><x-sort key="reference" label="Reference" /><x-sort key="date" label="Date" /><th>Supplier</th><th class="text-right">Items</th><x-sort key="total" label="Total" align="right" /><th class="text-right">Due</th><th>Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($purchases as $p)
                        <tr class="{{ $p->isCancelled() ? 'opacity-60' : '' }}">
                            <td><a href="{{ route('purchases.show', $p) }}" class="link font-mono text-[13px]">{{ $p->reference_no }}</a><span class="block text-xs text-slate-500">{{ $p->supplier_invoice_no }}</span></td>
                            <td class="whitespace-nowrap text-slate-500">{{ $p->purchase_date->format('d M Y') }}</td>
                            <td>{{ $p->supplier?->name }}</td>
                            <td class="tabular text-right">{{ $p->items_count }}</td>
                            <td class="tabular text-right font-medium">{{ money($p->total) }}</td>
                            <td class="tabular text-right">{{ $p->isCancelled() ? '—' : money($p->dueAmount()) }}</td>
                            <td>
                                @if ($p->isCancelled())<x-badge>Cancelled</x-badge>
                                @else<x-badge :tone="$p->payment_status->tone()" dot>{{ $p->payment_status->label() }}</x-badge>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $purchases->links() }}
        @endif
    </div>
</x-layouts.app>
