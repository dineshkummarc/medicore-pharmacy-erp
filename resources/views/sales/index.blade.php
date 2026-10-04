<x-layouts.app title="Invoices">
    <x-page-header title="Invoices" description="Every completed sale with its invoice, payments and returns.">
        <x-slot:actions>
            @can('sales.create')<a href="{{ route('pos.index') }}" class="btn-primary"><x-icon name="cart" class="size-4" /> New sale</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <div class="card px-5 py-4"><p class="text-xs font-medium text-slate-500">Invoices (filtered)</p><p class="tabular mt-1 text-xl font-semibold">{{ number_format($summary->count) }}</p></div>
        <div class="card px-5 py-4"><p class="text-xs font-medium text-slate-500">Gross sales</p><p class="tabular mt-1 text-xl font-semibold">{{ money($summary->total) }}</p></div>
        <div class="card px-5 py-4"><p class="text-xs font-medium text-slate-500">Net of returns</p><p class="tabular mt-1 text-xl font-semibold">{{ money($summary->total - $summary->returned) }}</p></div>
    </div>

    <div class="card overflow-hidden">
        <x-filters placeholder="Invoice no., customer name or phone…">
            @if (request('customer'))<input type="hidden" name="customer" value="{{ request('customer') }}">@endif
            <x-filter-select name="payment" :options="['paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid']" placeholder="Any payment" />
            <x-filter-select name="status" :options="collect(\App\Enums\SaleStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])" placeholder="Any status" />
            <x-filter-select name="method" :options="$methods->pluck('name', 'id')" placeholder="Any method" />
            <x-filter-select name="user" :options="$staff->pluck('name', 'id')" placeholder="Any cashier" />
            <x-date-range />
        </x-filters>
        @if ($sales->isEmpty())
            <x-empty icon="document" title="No invoices found" description="Try widening the date range or clearing filters." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><x-sort key="invoice" label="Invoice" /><x-sort key="date" label="Date" /><th>Customer</th><th class="hidden md:table-cell">Cashier</th><th class="text-right">Items</th><x-sort key="total" label="Total" align="right" /><th>Payment</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($sales as $sale)
                        <tr>
                            <td><a href="{{ route('sales.show', $sale) }}" class="link font-mono text-[13px]">{{ $sale->invoice_number }}</a>
                                @if ($sale->status !== \App\Enums\SaleStatus::Completed)<x-badge :tone="$sale->status->tone()" class="ml-1">{{ $sale->status->label() }}</x-badge>@endif</td>
                            <td class="whitespace-nowrap text-slate-500">{{ $sale->sold_at->format('d M Y, H:i') }}</td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                            <td class="hidden md:table-cell text-slate-500">{{ $sale->user?->name }}</td>
                            <td class="tabular text-right">{{ $sale->items_count }}</td>
                            <td class="tabular text-right font-medium">{{ money($sale->netTotal()) }}
                                @if ($sale->returned_amount > 0)<span class="block text-[11px] text-slate-400 line-through">{{ money($sale->total) }}</span>@endif</td>
                            <td><x-badge :tone="$sale->payment_status->tone()" dot>{{ $sale->payment_status->label() }}</x-badge></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('sales.print', $sale) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm" aria-label="Print {{ $sale->invoice_number }}"><x-icon name="printer" class="size-4" /></a>
                                <a href="{{ route('sales.pdf', $sale) }}" class="btn-ghost btn-sm" aria-label="Download PDF"><x-icon name="download" class="size-4" /></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $sales->links() }}
        @endif
    </div>
</x-layouts.app>
