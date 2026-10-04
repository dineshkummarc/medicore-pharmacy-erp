@php
    $reports = [
        'reports.index' => 'Overview', 'reports.sales' => 'Sales', 'reports.medicines' => 'By medicine', 'reports.categories' => 'By category',
        'reports.staff' => 'By staff', 'reports.customers' => 'Customers', 'reports.purchases' => 'Purchases', 'reports.inventory' => 'Inventory value',
        'reports.low-stock' => 'Low stock', 'reports.expiry' => 'Expiry', 'reports.expenses' => 'Expenses', 'reports.payments' => 'Payment methods',
    ];
    if (auth()->user()->can('reports.financial')) { $reports['reports.profit'] = 'Profit & loss'; }
@endphp
<nav class="no-print -mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0 [mask-image:linear-gradient(to_right,black_92%,transparent)]" aria-label="Reports">
    <ul class="flex gap-1 border-b border-slate-200">
        @foreach ($reports as $route => $label)
            <li><a href="{{ route($route, request()->only(['from', 'to', 'range'])) }}" @if(request()->routeIs($route)) aria-current="page" @endif
                   class="block whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-ink' }}">{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>
