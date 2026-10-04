{{-- Invoice body shared by the screen and print views (Tailwind). --}}
@php $s = settings(); @endphp
<div class="flex flex-col justify-between gap-6 sm:flex-row">
    <div class="flex items-start gap-4">
        @if ($s['store_logo'])
            <img src="{{ \Illuminate\Support\Facades\Storage::url($s['store_logo']) }}" alt="" class="size-14 rounded-lg object-contain">
        @endif
        <div>
            <p class="text-lg font-semibold text-ink">{{ $s['store_name'] }}</p>
            @if ($s['store_tagline'])<p class="text-sm text-slate-500">{{ $s['store_tagline'] }}</p>@endif
            <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $s['store_address'] }}</p>
            <p class="text-sm text-slate-600">{{ collect([$s['store_phone'], $s['store_email']])->filter()->implode(' · ') }}</p>
            @if ($s['license_number'] || $s['tax_number'])
                <p class="mt-1 text-xs text-slate-500">{{ collect([$s['license_number'] ? 'Licence '.$s['license_number'] : null, $s['tax_number'] ? 'Tax no. '.$s['tax_number'] : null])->filter()->implode(' · ') }}</p>
            @endif
        </div>
    </div>
    <div class="sm:text-right">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-700">Tax invoice</p>
        <p class="mt-1 font-mono text-lg font-semibold">{{ $sale->invoice_number }}</p>
        <p class="text-sm text-slate-600">{{ $sale->sold_at->format('d M Y, H:i') }}</p>
        <p class="mt-3 text-xs uppercase tracking-wide text-slate-400">Billed to</p>
        <p class="text-sm font-medium">{{ $sale->customer?->name ?? 'Walk-in customer' }}</p>
        @if ($sale->customer?->phone)<p class="text-sm text-slate-600">{{ $sale->customer->phone }}</p>@endif
    </div>
</div>

<div class="mt-8 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b-2 border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                <th class="py-2 pr-3 font-semibold">#</th><th class="py-2 pr-3 font-semibold">Item</th><th class="py-2 pr-3 text-right font-semibold">Qty</th>
                <th class="py-2 pr-3 text-right font-semibold">Price</th><th class="py-2 pr-3 text-right font-semibold">Disc.</th>
                @if ($sale->tax_total > 0)<th class="py-2 pr-3 text-right font-semibold">{{ $s['tax_label'] }}</th>@endif
                <th class="py-2 text-right font-semibold">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr class="border-b border-slate-100 align-top">
                    <td class="py-2.5 pr-3 text-slate-400">{{ $loop->iteration }}</td>
                    <td class="py-2.5 pr-3">
                        <span class="font-medium text-ink">{{ $item->medicine_name }}</span>
                        <span class="block text-xs text-slate-500">Batch {{ $item->batch_number }} · Exp {{ $item->expiry_date?->format('m/Y') }}</span>
                        @if ($item->returned_quantity > 0)<span class="block text-xs font-medium text-amber-700">{{ $item->returned_quantity }} returned</span>@endif
                    </td>
                    <td class="tabular py-2.5 pr-3 text-right">{{ $item->quantity }}</td>
                    <td class="tabular py-2.5 pr-3 text-right">{{ money($item->unit_price) }}</td>
                    <td class="tabular py-2.5 pr-3 text-right">{{ $item->discount_amount > 0 ? '−'.money($item->discount_amount) : '—' }}</td>
                    @if ($sale->tax_total > 0)<td class="tabular py-2.5 pr-3 text-right">{{ money($item->tax_amount) }}</td>@endif
                    <td class="tabular py-2.5 text-right font-medium">{{ money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-6 flex justify-end">
    <dl class="w-full max-w-xs space-y-1.5 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular">{{ money($sale->subtotal) }}</dd></div>
        @if ($sale->discount_total > 0)<div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular">−{{ money($sale->discount_total) }}</dd></div>@endif
        @if ($sale->tax_total > 0)<div class="flex justify-between"><dt class="text-slate-500">{{ $s['tax_label'] }}</dt><dd class="tabular">{{ money($sale->tax_total) }}</dd></div>@endif
        <div class="flex justify-between border-t-2 border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular">{{ money($sale->total) }}</dd></div>
        @if ($sale->returned_amount > 0)
            <div class="flex justify-between text-amber-700"><dt>Returned</dt><dd class="tabular">−{{ money($sale->returned_amount) }}</dd></div>
        @endif
        <div class="flex justify-between"><dt class="text-slate-500">Paid ({{ $sale->payments->where('type', \App\Enums\PaymentType::Sale)->first()?->method?->name ?? '—' }})</dt><dd class="tabular">{{ money($sale->paid_amount) }}</dd></div>
        @if ($sale->dueAmount() > 0)
            <div class="flex justify-between font-semibold text-rose-700"><dt>Balance due</dt><dd class="tabular">{{ money($sale->dueAmount()) }}</dd></div>
        @endif
    </dl>
</div>

<div class="mt-8 border-t border-slate-100 pt-4 text-xs text-slate-500">
    <p>Served by {{ $sale->user?->name }}. @if ($sale->notes) Note: {{ $sale->notes }} @endif</p>
    @if ($s['invoice_footer'])<p class="mt-1">{{ $s['invoice_footer'] }}</p>@endif
</div>
