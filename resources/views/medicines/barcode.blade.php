{{-- Printable shelf labels. Barcode rendered as Code 128 SVG (no external service). --}}
@php
    $code = $medicine->barcode ?: $medicine->sku;
    $svg = \App\Support\Barcode::code128Svg($code);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Labels · {{ $medicine->name }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 10mm; }</style>
</head>
<body class="bg-white p-6">
    <div class="no-print mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold">Shelf labels · {{ $medicine->displayName() }}</h1>
            <p class="text-sm text-slate-500">{{ $copies }} labels on A4. Code: {{ $code }}</p>
        </div>
        <button onclick="window.print()" class="btn-primary">Print</button>
    </div>
    <div class="grid grid-cols-3 gap-3">
        @for ($i = 0; $i < $copies; $i++)
            <div class="break-inside-avoid rounded border border-slate-300 p-2 text-center">
                <p class="truncate text-[11px] font-semibold">{{ $medicine->displayName() }}</p>
                <div class="mx-auto mt-1 h-12 w-full [&>svg]:h-full [&>svg]:w-full">{!! $svg !!}</div>
                <p class="font-mono text-[10px] tracking-wider">{{ $code }}</p>
                <p class="text-[11px] font-semibold">{{ money($medicine->selling_price) }}</p>
            </div>
        @endfor
    </div>
</body>
</html>
