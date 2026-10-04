<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->invoice_number }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 14mm; }</style>
</head>
<body class="bg-slate-100 print:bg-white">
    <div class="no-print sticky top-0 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
        <a href="{{ route('sales.show', $sale) }}" class="text-sm font-medium text-slate-600 hover:text-ink">← Back to invoice</a>
        <button onclick="window.print()" class="btn-primary">Print</button>
    </div>
    <main class="mx-auto my-6 max-w-3xl bg-white p-10 shadow-card print:my-0 print:max-w-none print:p-0 print:shadow-none">
        @include('invoices._document', ['sale' => $sale])
    </main>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
