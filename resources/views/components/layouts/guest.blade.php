@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ settings('store_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-20">
        <div class="mx-auto w-full max-w-sm">
            <div class="mb-10 flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm">
                    <x-icon name="cross" class="size-5" stroke="1.8" />
                </span>
                <div>
                    <p class="font-semibold text-ink">{{ settings('store_name', config('app.name')) }}</p>
                    <p class="text-xs text-slate-500">Pharmacy ERP</p>
                </div>
            </div>
            {{ $slot }}
        </div>
    </div>
    <div class="relative hidden overflow-hidden bg-brand-900 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(67,185,172,0.35),transparent_55%),radial-gradient(circle_at_80%_80%,rgba(21,126,118,0.5),transparent_50%)]"></div>
        <div class="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(white_1px,transparent_1px),linear-gradient(90deg,white_1px,transparent_1px)] [background-size:32px_32px]"></div>
        <div class="relative flex h-full flex-col justify-end p-14 text-white">
            <p class="text-sm font-medium uppercase tracking-widest text-brand-200">Run your pharmacy with confidence</p>
            <h2 class="mt-3 max-w-md text-3xl font-semibold leading-tight">Inventory, expiry, billing and reports, all in one place.</h2>
            <ul class="mt-8 space-y-3 text-sm text-brand-100">
                <li class="flex items-center gap-3"><x-icon name="check-circle" class="size-5 text-brand-300" /> Batch &amp; expiry tracking with FEFO dispensing</li>
                <li class="flex items-center gap-3"><x-icon name="check-circle" class="size-5 text-brand-300" /> Fast barcode-ready point of sale</li>
                <li class="flex items-center gap-3"><x-icon name="check-circle" class="size-5 text-brand-300" /> Role-based access and a full audit trail</li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
