@props(['title' => null, 'wide' => false])
@php
    $user = auth()->user();
    $storeName = settings('store_name', config('app.name'));
    $logo = settings('store_logo');
    $nav = [
        'Overview' => [
            ['Dashboard', 'dashboard', 'dashboard', 'dashboard.view', 'dashboard'],
            ['Point of sale', 'pos.index', 'cart', 'sales.create', 'pos.*'],
        ],
        'Inventory' => [
            ['Medicines', 'medicines.index', 'pill', 'medicines.view', 'medicines.*'],
            ['Batches & expiry', 'batches.index', 'cube', 'inventory.view', 'batches.*'],
            ['Stock movements', 'stock-movements.index', 'arrows', 'inventory.view', 'stock-movements.*'],
            ['Categories', 'categories.index', 'tag', 'catalog.manage', 'categories.*'],
            ['Manufacturers', 'manufacturers.index', 'building', 'catalog.manage', 'manufacturers.*'],
        ],
        'Sales' => [
            ['Invoices', 'sales.index', 'document', 'sales.view', 'sales.*'],
            ['Returns', 'returns.index', 'return', 'returns.view', 'returns.*'],
            ['Customers', 'customers.index', 'users', 'customers.view', 'customers.*'],
        ],
        'Purchasing' => [
            ['Purchases', 'purchases.index', 'inbox', 'purchases.view', 'purchases.*'],
            ['Suppliers', 'suppliers.index', 'truck', 'suppliers.view', 'suppliers.*'],
        ],
        'Finance' => [
            ['Expenses', 'expenses.index', 'banknotes', 'expenses.view', 'expenses.*'],
            ['Reports', 'reports.index', 'chart', 'reports.view', 'reports.*'],
        ],
        'Administration' => [
            ['Staff', 'staff.index', 'user', 'staff.manage', 'staff.*'],
            ['Roles & permissions', 'roles.index', 'shield', 'roles.manage', 'roles.*'],
            ['Settings', 'settings.edit', 'settings', 'settings.manage', 'settings.*'],
            ['Audit log', 'activity-log.index', 'clipboard', 'audit.view', 'activity-log.*'],
        ],
    ];
    $alerts = $user->can('inventory.view')
        ? \App\Models\Medicine::query()->active()->lowStock()->count()
            + \App\Models\Batch::query()->inStock()->where(fn ($q) => $q->expired()->orWhere(fn ($w) => $w->expiringWithin((int) settings('expiry_alert_days', 90))))->count()
        : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ $storeName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-pop">Skip to content</a>

{{-- Mobile backdrop --}}
<div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

{{-- Sidebar --}}
<aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200/80 bg-white transition-transform duration-200 lg:translate-x-0"
       :class="sidebar && 'translate-x-0'" aria-label="Main navigation">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-5">
        @if ($logo)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt="" class="size-9 rounded-lg object-contain">
        @else
            <span class="grid size-9 place-items-center rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm">
                <x-icon name="cross" class="size-5" stroke="1.8" />
            </span>
        @endif
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-ink">{{ $storeName }}</p>
            <p class="text-[11px] text-slate-500">Pharmacy ERP</p>
        </div>
        <button type="button" class="btn-ghost -mr-2 ml-auto p-1.5 lg:hidden" @click="sidebar = false" aria-label="Close menu">
            <x-icon name="x" />
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-6">
        @foreach ($nav as $section => $links)
            @php $visible = array_filter($links, fn ($l) => $user->can($l[3])); @endphp
            @if ($visible)
                <p class="nav-section">{{ $section }}</p>
                <ul class="space-y-0.5">
                    @foreach ($visible as [$label, $route, $icon, $permission, $pattern])
                        @php $active = request()->routeIs($pattern); @endphp
                        <li>
                            <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
                               class="group flex items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors
                                      {{ $active ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-50 hover:text-ink' }}">
                                <x-icon :name="$icon" class="size-[18px] {{ $active ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-500' }}" />
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-slate-100 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="grid size-8 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">{{ $user->initials() }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[13px] font-medium text-ink">{{ $user->name }}</p>
                <p class="truncate text-[11px] text-slate-500">{{ $user->role?->name }}</p>
            </div>
        </div>
    </div>
</aside>

<div class="lg:pl-64">
    {{-- Top bar --}}
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200/80 bg-white/85 px-4 backdrop-blur sm:px-6 no-print">
        <button type="button" class="btn-ghost -ml-1 p-2 lg:hidden" @click="sidebar = true" aria-label="Open menu">
            <x-icon name="menu" />
        </button>

        @can('medicines.view')
            <form action="{{ route('medicines.index') }}" method="GET" class="relative hidden max-w-md flex-1 sm:block" role="search">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <label for="global-search" class="sr-only">Search medicines</label>
                <input id="global-search" type="search" name="q" placeholder="Search medicines, generic name, SKU or barcode…"
                       class="w-full rounded-lg border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20">
            </form>
        @endcan

        <div class="ml-auto flex items-center gap-2">
            @if ($alerts > 0)
                <a href="{{ route('reports.expiry') }}" class="btn-ghost relative p-2" title="{{ $alerts }} stock alerts">
                    <x-icon name="warning" class="text-amber-500" />
                    <span class="absolute -right-0.5 -top-0.5 grid min-w-[18px] place-items-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold leading-[18px] text-white">{{ $alerts > 99 ? '99+' : $alerts }}</span>
                    <span class="sr-only">{{ $alerts }} stock alerts</span>
                </a>
            @endif

            @can('sales.create')
                <a href="{{ route('pos.index') }}" class="btn-primary hidden sm:inline-flex">
                    <x-icon name="cart" class="size-4" /> New sale
                </a>
            @endcan

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg p-1 pr-2 hover:bg-slate-100" :aria-expanded="open" aria-haspopup="menu">
                    <span class="grid size-8 place-items-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ $user->initials() }}</span>
                    <x-icon name="chevron-down" class="size-4 text-slate-400" />
                </button>
                <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-pop" role="menu">
                    <div class="border-b border-slate-100 px-3 py-2">
                        <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                        <x-icon name="user" class="size-4 text-slate-400" /> Profile & password
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                            <x-icon name="logout" class="size-4 text-slate-400" /> Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main id="main" class="{{ $wide ? 'px-4 py-5 sm:px-6' : 'mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8' }}">
        {{ $slot }}
    </main>
</div>

<x-toasts />
</body>
</html>
