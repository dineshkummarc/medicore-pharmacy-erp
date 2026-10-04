<x-layouts.app :title="'Return '.$return->return_number">
    <x-page-header :title="'Return '.$return->return_number" :back="route('returns.index')"
                   :description="'Against '.$return->sale->invoice_number.' · '.$return->returned_at->format('d M Y, H:i').' · by '.$return->user?->name" />
    <div class="grid gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Item</th><th class="text-right">Qty</th><th>Stock</th><th class="text-right">Value</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($return->items as $item)
                        <tr>
                            <td class="font-medium text-ink">{{ $item->saleItem?->medicine_name }}<span class="block text-xs font-normal text-slate-500">Batch {{ $item->saleItem?->batch_number }}</span></td>
                            <td class="tabular text-right">{{ $item->quantity }}</td>
                            <td>@if ($item->restocked)<x-badge tone="green">Restocked</x-badge>@else<x-badge tone="gray">Not restocked</x-badge>@endif</td>
                            <td class="tabular text-right font-medium">{{ money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-5 py-4 text-sm text-slate-600"><span class="font-medium text-ink">Reason:</span> {{ $return->reason }}</p>
        </section>
        <section class="card p-5">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Value before tax</dt><dd class="tabular">{{ money($return->subtotal) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular">{{ money($return->tax_total) }}</dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-semibold"><dt>Return value</dt><dd class="tabular">{{ money($return->total) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Cleared from balance due</dt><dd class="tabular">{{ money($return->credit_amount) }}</dd></div>
                <div class="flex justify-between font-medium"><dt>Refunded</dt><dd class="tabular">{{ money($return->refund_amount) }} @if($return->refundPayment) <span class="text-xs font-normal text-slate-500">via {{ $return->refundPayment->method?->name }}</span>@endif</dd></div>
            </dl>
            @can('sales.view')<a href="{{ route('sales.show', $return->sale_id) }}" class="btn-secondary mt-5 w-full">View original invoice</a>@endcan
        </section>
    </div>
</x-layouts.app>
