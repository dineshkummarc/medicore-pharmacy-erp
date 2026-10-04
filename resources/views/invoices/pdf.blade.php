{{-- DomPDF layout: plain CSS only, no external resources. --}}
@php $s = settings(); $logo = $s['store_logo'] ? storage_path('app/public/'.$s['store_logo']) : null; @endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #0f1a24; margin: 0; }
    .muted { color: #64748b; }
    .right { text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    .items th { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 6px 4px; text-align: left; }
    .items td { border-bottom: 1px solid #f1f5f9; padding: 7px 4px; vertical-align: top; }
    .totals td { padding: 3px 4px; }
    .grand td { border-top: 2px solid #e2e8f0; font-size: 13px; font-weight: bold; padding-top: 6px; }
    .brand { color: #136660; }
    .footer { margin-top: 28px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 9px; color: #64748b; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 60%; vertical-align: top;">
            @if ($logo && is_file($logo))<img src="{{ $logo }}" style="height: 48px; margin-bottom: 6px;">@endif
            <div style="font-size: 16px; font-weight: bold;">{{ $s['store_name'] }}</div>
            <div class="muted">{{ $s['store_tagline'] }}</div>
            <div style="margin-top: 6px;">{!! nl2br(e($s['store_address'])) !!}</div>
            <div>{{ collect([$s['store_phone'], $s['store_email']])->filter()->implode(' · ') }}</div>
            <div class="muted">{{ collect([$s['license_number'] ? 'Licence '.$s['license_number'] : null, $s['tax_number'] ? 'Tax no. '.$s['tax_number'] : null])->filter()->implode(' · ') }}</div>
        </td>
        <td class="right" style="vertical-align: top;">
            <div class="brand" style="font-size: 9px; font-weight: bold; letter-spacing: .15em;">TAX INVOICE</div>
            <div style="font-size: 15px; font-weight: bold;">{{ $sale->invoice_number }}</div>
            <div>{{ $sale->sold_at->format('d M Y, H:i') }}</div>
            <div class="muted" style="margin-top: 10px; font-size: 9px;">BILLED TO</div>
            <div style="font-weight: bold;">{{ $sale->customer?->name ?? 'Walk-in customer' }}</div>
            <div>{{ $sale->customer?->phone }}</div>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 22px;">
    <thead><tr><th>#</th><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Disc.</th>@if($sale->tax_total > 0)<th class="right">{{ $s['tax_label'] }}</th>@endif<th class="right">Amount</th></tr></thead>
    <tbody>
    @foreach ($sale->items as $item)
        <tr>
            <td class="muted">{{ $loop->iteration }}</td>
            <td><strong>{{ $item->medicine_name }}</strong><br><span class="muted">Batch {{ $item->batch_number }} · Exp {{ $item->expiry_date?->format('m/Y') }}</span>
                @if ($item->returned_quantity > 0)<br><span style="color:#b45309;">{{ $item->returned_quantity }} returned</span>@endif</td>
            <td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ money($item->unit_price) }}</td>
            <td class="right">{{ $item->discount_amount > 0 ? '-'.money($item->discount_amount) : '-' }}</td>
            @if ($sale->tax_total > 0)<td class="right">{{ money($item->tax_amount) }}</td>@endif
            <td class="right"><strong>{{ money($item->line_total) }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

<table style="margin-top: 14px;">
    <tr><td style="width: 55%;"></td><td>
        <table class="totals">
            <tr><td class="muted">Subtotal</td><td class="right">{{ money($sale->subtotal) }}</td></tr>
            @if ($sale->discount_total > 0)<tr><td class="muted">Discount</td><td class="right">-{{ money($sale->discount_total) }}</td></tr>@endif
            @if ($sale->tax_total > 0)<tr><td class="muted">{{ $s['tax_label'] }}</td><td class="right">{{ money($sale->tax_total) }}</td></tr>@endif
            <tr class="grand"><td>Total</td><td class="right">{{ money($sale->total) }}</td></tr>
            @if ($sale->returned_amount > 0)<tr><td style="color:#b45309;">Returned</td><td class="right" style="color:#b45309;">-{{ money($sale->returned_amount) }}</td></tr>@endif
            <tr><td class="muted">Paid</td><td class="right">{{ money($sale->paid_amount) }}</td></tr>
            @if ($sale->dueAmount() > 0)<tr><td style="color:#be123c; font-weight:bold;">Balance due</td><td class="right" style="color:#be123c; font-weight:bold;">{{ money($sale->dueAmount()) }}</td></tr>@endif
        </table>
    </td></tr>
</table>

<div class="footer">
    Payment: {{ $sale->payments->where('type', \App\Enums\PaymentType::Sale)->first()?->method?->name ?? 'Credit' }} · Served by {{ $sale->user?->name }}
    @if ($s['invoice_footer'])<br>{{ $s['invoice_footer'] }}@endif
</div>
</body>
</html>
