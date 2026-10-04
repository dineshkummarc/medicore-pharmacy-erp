<x-layouts.app title="New return">
    <x-page-header title="Process a return" description="Find the original invoice, then choose what is coming back." :back="route('returns.index')" />

    <form method="GET" action="{{ route('returns.create') }}" class="card mb-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="invoice" class="field-label">Invoice number</label>
            <input id="invoice" name="invoice" value="{{ $invoice }}" required autofocus placeholder="{{ settings('invoice_prefix') }}000123" class="field-input font-mono">
        </div>
        <button class="btn-secondary"><x-icon name="search" class="size-4" /> Find invoice</button>
    </form>

    @if ($invoice !== '' && ! $sale)
        <div class="card"><x-empty icon="search" title="Invoice not found" :description="'No invoice matches “'.$invoice.'”.'" /></div>
    @elseif ($sale)
        @php $returnable = $sale->items->filter(fn ($i) => $i->returnableQuantity() > 0); @endphp
        @if ($returnable->isEmpty())
            <div class="card"><x-empty icon="check-circle" title="Fully returned" description="Every item on {{ $sale->invoice_number }} has already been returned." /></div>
        @else
            <form method="POST" action="{{ route('returns.store', $sale) }}" class="grid gap-6 xl:grid-cols-3">
                @csrf
                <section class="card overflow-hidden xl:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="font-mono text-sm font-semibold">{{ $sale->invoice_number }}</h2>
                            <p class="text-xs text-slate-500">{{ $sale->sold_at->format('d M Y, H:i') }} · {{ $sale->customer?->name ?? 'Walk-in' }} · Paid {{ money($sale->paid_amount) }} of {{ money($sale->total) }}</p>
                        </div>
                        <a href="{{ route('sales.show', $sale) }}" class="text-sm link">View invoice</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>Item</th><th class="text-right">Sold</th><th class="text-right">Returnable</th><th class="w-28">Return qty</th><th>Restock</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach ($returnable->values() as $i => $item)
                                <tr>
                                    <td><span class="font-medium text-ink">{{ $item->medicine_name }}</span>
                                        <span class="block text-xs text-slate-500">Batch {{ $item->batch_number }} · {{ money($item->line_total / $item->quantity) }} each (after discount & tax)</span>
                                        <input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}"></td>
                                    <td class="tabular text-right">{{ $item->quantity }}</td>
                                    <td class="tabular text-right">{{ $item->returnableQuantity() }}</td>
                                    <td><input type="number" name="items[{{ $i }}][quantity]" min="0" max="{{ $item->returnableQuantity() }}" value="{{ old('items.'.$i.'.quantity', 0) }}"
                                               class="field-input tabular py-1.5" aria-label="Return quantity for {{ $item->medicine_name }}"></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $i }}][restock]" value="0">
                                        <label class="flex items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" name="items[{{ $i }}][restock]" value="1" @checked(old('items.'.$i.'.restock', '1') === '1') class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                            Back to stock
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Untick “Back to stock” for damaged or opened packs. They are recorded as returned but not resold.</p>
                </section>

                <section class="card space-y-4 p-5">
                    <x-textarea name="reason" label="Reason for return" required rows="3" maxlength="500" />
                    <x-select name="refund_method_id" label="Refund paid via" :options="$methods->pluck('name', 'id')" placeholder="— Select —"
                              :hint="$sale->dueAmount() > 0 ? 'This invoice has '.money($sale->dueAmount()).' unpaid. The return clears that first, and only the remainder is refunded.' : 'The return value is refunded to the customer.'" />
                    <button class="btn-primary w-full">Process return</button>
                </section>
            </form>
        @endif
    @endif
</x-layouts.app>
