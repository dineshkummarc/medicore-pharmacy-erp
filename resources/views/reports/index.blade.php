<x-layouts.app title="Reports">
    <x-page-header title="Reports" :description="$from->format('d M Y').' – '.$to->format('d M Y')" />
    @include('reports._nav')
    @include('reports._range')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Net sales" :value="money($summary['net'])" icon="banknotes" :hint="$summary['invoices'].' invoices'" />
        <x-stat label="Average sale" :value="money($summary['average'])" icon="receipt" tone="blue" />
        <x-stat label="Units sold" :value="number_format($summary['units'])" icon="pill" tone="violet" />
        <x-stat label="Returns" :value="money($summary['returned'])" icon="return" tone="amber" :hint="money($summary['discounts']).' discounts given'" />
    </div>

    @if ($pnl)
        <section class="card mt-6 p-6">
            <h2 class="text-sm font-semibold">Profit snapshot</h2>
            <dl class="mt-4 grid gap-6 sm:grid-cols-3 lg:grid-cols-5">
                @foreach (['Revenue (ex. tax)' => $pnl['revenue'], 'Cost of goods' => $pnl['cogs'], 'Gross profit' => $pnl['gross_profit'], 'Expenses + losses' => $pnl['expenses'] + $pnl['inventory_losses'], 'Net profit' => $pnl['net_profit']] as $label => $value)
                    <div><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="tabular mt-1 text-lg font-semibold {{ $label === 'Net profit' ? ($value >= 0 ? 'text-emerald-700' : 'text-rose-700') : '' }}">{{ money($value) }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['reports.sales', 'Sales over time', 'Daily, weekly, monthly or annual sales', 'trend-up'],
            ['reports.medicines', 'Sales by medicine', 'Best and worst sellers', 'pill'],
            ['reports.categories', 'Sales by category', 'Which therapeutic areas drive revenue', 'tag'],
            ['reports.staff', 'Sales by staff', 'Invoices and discounts per cashier', 'user'],
            ['reports.customers', 'Customer purchases', 'Top customers and outstanding balances', 'users'],
            ['reports.purchases', 'Purchases by supplier', 'What you bought and what you owe', 'truck'],
            ['reports.inventory', 'Inventory valuation', 'Stock at cost and retail value', 'cube'],
            ['reports.low-stock', 'Low stock', 'Medicines to reorder', 'warning'],
            ['reports.expiry', 'Expiry', 'Expired and soon-to-expire batches', 'clock'],
            ['reports.expenses', 'Expenses', 'Spending by category', 'banknotes'],
            ['reports.payments', 'Payment methods', 'Cash, card and transfer totals', 'card'],
        ] as [$route, $title, $desc, $icon])
            <a href="{{ route($route, request()->only(['from', 'to', 'range'])) }}" class="card group flex items-start gap-4 p-5 transition hover:border-brand-300">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$icon" /></span>
                <span><span class="block font-medium text-ink group-hover:text-brand-700">{{ $title }}</span><span class="block text-sm text-slate-500">{{ $desc }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
