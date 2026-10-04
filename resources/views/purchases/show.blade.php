<x-layouts.app :title="'Purchase '.$purchase->reference_no">
    <x-page-header :title="'Purchase '.$purchase->reference_no" :back="route('purchases.index')"
                   :description="$purchase->supplier?->name.' · '.$purchase->purchase_date->format('d M Y').($purchase->supplier_invoice_no ? ' · Supplier invoice '.$purchase->supplier_invoice_no : '')">
        <x-slot:actions>
            @if ($purchase->isCancelled())
                <x-badge tone="gray">Cancelled</x-badge>
            @else
                <x-badge :tone="$purchase->payment_status->tone()" dot>{{ $purchase->payment_status->label() }}</x-badge>
                @can('purchases.cancel')
                    <a href="{{ route('purchases.edit', $purchase) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit details</a>
                    @if ($cancellable)
                        <x-confirm :action="route('purchases.cancel', $purchase)" title="Cancel this purchase?" button="Cancel purchase" :reason="true"
                                   message="All stock from this purchase is removed and the supplier balance is reversed. Amounts already paid remain as a credit with the supplier.">
                            <button type="button" class="btn-ghost text-rose-700 hover:bg-rose-50">Cancel purchase</button>
                        </x-confirm>
                    @else
                        <span class="text-xs text-slate-500" title="Some stock from this purchase has been sold or adjusted">Not cancellable: stock already used</span>
                    @endif
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($purchase->isCancelled())
        <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            Cancelled {{ $purchase->cancelled_at->format('d M Y, H:i') }} by {{ $purchase->canceller?->name }}: <em>{{ $purchase->cancel_reason }}</em>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-right">Qty</th><th class="text-right">Unit cost</th><th class="text-right">Discount</th><th class="text-right">Tax</th><th class="text-right">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td><a href="{{ route('medicines.show', $item->medicine_id) }}" class="font-medium text-ink hover:text-brand-700">{{ $item->medicine?->displayName() }}</a>
                                <span class="block text-xs text-slate-500">Sells at {{ money($item->selling_price) }}</span></td>
                            <td class="font-mono text-xs">{{ $item->batch_number }}</td>
                            <td class="whitespace-nowrap">{{ $item->expiry_date->format('d M Y') }}</td>
                            <td class="tabular text-right">{{ number_format($item->quantity) }}
                                @if ($item->batch && ! $purchase->isCancelled())<span class="block text-[11px] text-slate-400">{{ number_format($item->batch->quantity) }} left</span>@endif</td>
                            <td class="tabular text-right">{{ money($item->unit_cost) }}</td>
                            <td class="tabular text-right">{{ money($item->discount) }}</td>
                            <td class="tabular text-right">{{ money($item->tax_amount) }}</td>
                            <td class="tabular text-right font-medium">{{ money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($purchase->notes)<p class="whitespace-pre-line border-t border-slate-100 px-5 py-4 text-sm text-slate-600">{{ $purchase->notes }}</p>@endif
        </section>

        <div class="space-y-6">
            <section class="card p-5">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular">{{ money($purchase->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular">−{{ money($purchase->discount_total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular">{{ money($purchase->tax_total) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular">{{ money($purchase->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd class="tabular">{{ money($purchase->paid_amount) }}</dd></div>
                    @unless ($purchase->isCancelled())
                        <div class="flex justify-between font-medium text-amber-700"><dt>Due</dt><dd class="tabular">{{ money($purchase->dueAmount()) }}</dd></div>
                    @endunless
                </dl>
                <p class="mt-4 text-xs text-slate-500">Recorded by {{ $purchase->user?->name }} on {{ $purchase->created_at->format('d M Y, H:i') }}</p>
            </section>
            <section class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Payments</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($purchase->payments as $p)
                        <li class="flex justify-between px-5 py-2.5"><span>{{ $p->method?->name }}<span class="block text-xs text-slate-500">{{ $p->paid_at->format('d M Y') }} {{ $p->reference }}</span></span><span class="tabular font-medium">{{ money($p->amount) }}</span></li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">No payments yet.</li>
                    @endforelse
                </ul>
                @if (! $purchase->isCancelled() && $purchase->dueAmount() > 0)
                    @can('suppliers.payments')
                        <div class="border-t border-slate-100 p-4"><a href="{{ route('suppliers.show', $purchase->supplier_id) }}" class="btn-secondary w-full">Pay supplier</a></div>
                    @endcan
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
