<x-layouts.app title="Dashboard">
    <x-page-header title="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}"
                   description="Here's how {{ settings('store_name') }} is doing today, {{ now()->format('l, j F') }}.">
        <x-slot:actions>
            @can('reports.view')<a href="{{ route('reports.index') }}" class="btn-secondary"><x-icon name="chart" class="size-4" /> Reports</a>@endcan
            @can('purchases.create')<a href="{{ route('purchases.create') }}" class="btn-secondary"><x-icon name="inbox" class="size-4" /> Receive stock</a>@endcan
        </x-slot:actions>
    </x-page-header>

    {{-- KPI row --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Today's revenue" :value="money($today['net'])" icon="banknotes"
                :hint="$today['invoices'].' '.Str::plural('sale', $today['invoices']).' · avg '.money($today['average'])" :href="auth()->user()->can('sales.view') ? route('sales.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) : null" />
        <x-stat label="Revenue this month" :value="money($month['net'])" icon="trend-up" tone="blue"
                :change="$monthChange" hint="vs last month" />
        @if ($pnl)
            <x-stat label="Est. net profit (month)" :value="money($pnl['net_profit'])" icon="chart" tone="{{ $pnl['net_profit'] >= 0 ? 'emerald' : 'rose' }}"
                    :hint="number_format($pnl['gross_margin'], 1).'% gross margin · '.money($expensesMonth).' expenses'" :href="route('reports.profit')" />
        @else
            <x-stat label="Units sold (month)" :value="number_format($month['units'])" icon="pill" tone="violet" :hint="$month['invoices'].' invoices'" />
        @endif
        <x-stat label="Customer dues" :value="money($receivable)" icon="users" tone="amber"
                :hint="$payable > 0 ? money($payable).' owed to suppliers' : 'Outstanding balances'" :href="auth()->user()->can('customers.view') ? route('customers.index', ['balance' => 'due']) : null" />
    </div>

    {{-- Inventory health --}}
    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $health = [
                ['Medicines', number_format($counts['active']), 'pill', 'slate', route('medicines.index'), $counts['medicines'] - $counts['active'] > 0 ? ($counts['medicines'] - $counts['active']).' inactive' : 'All active'],
                ['Low stock', number_format($counts['low']), 'warning', $counts['low'] ? 'amber' : 'slate', route('medicines.index', ['stock' => 'low']), $counts['out'].' out of stock'],
                ['Expiring ≤ '.$alertDays.'d', number_format($counts['expiring']), 'clock', $counts['expiring'] ? 'amber' : 'slate', route('batches.index', ['expiry' => 'soon']), 'batches in stock'],
                ['Expired in stock', number_format($counts['expired']), 'x-circle', $counts['expired'] ? 'rose' : 'slate', route('batches.index', ['expiry' => 'expired']), $counts['expired'] ? 'Write off or return' : 'Nothing expired'],
            ];
            $canInventory = auth()->user()->can('inventory.view');
        @endphp
        @foreach ($health as [$label, $value, $icon, $tone, $href, $hint])
            <a href="{{ $canInventory || $loop->first ? $href : '#' }}" class="card flex items-center gap-4 p-4 transition hover:border-slate-300">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ ['slate' => 'bg-slate-100 text-slate-500', 'amber' => 'bg-amber-50 text-amber-600', 'rose' => 'bg-rose-50 text-rose-600'][$tone] }}">
                    <x-icon :name="$icon" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="tabular text-xl font-semibold leading-tight">{{ $value }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $label }} · {{ $hint }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Sales trend --}}
        <section class="card p-5 xl:col-span-2" aria-labelledby="trend-title">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="trend-title" class="text-sm font-semibold text-ink">Net sales · last 30 days</h2>
                    <p class="tabular mt-1 text-2xl font-semibold tracking-tight">{{ money($trend->sum('total')) }}</p>
                </div>
                <p class="text-xs text-slate-500">{{ number_format($trend->sum('invoices')) }} invoices</p>
            </div>
            <div class="mt-4 h-64">
                <canvas x-data="lineChart" role="img" aria-label="Line chart of daily net sales over the last 30 days"
                        data-chart="{{ json_encode(['labels' => $trend->pluck('label'), 'values' => $trend->pluck('total'), 'currency' => settings('currency_symbol'), 'label' => 'Net sales']) }}"></canvas>
            </div>
            <details class="mt-3 text-xs text-slate-500">
                <summary class="cursor-pointer select-none hover:text-slate-700">View as table</summary>
                <div class="mt-2 max-h-56 overflow-y-auto">
                    <table class="w-full text-left">
                        <thead><tr class="text-slate-400"><th class="py-1 font-medium">Day</th><th class="py-1 text-right font-medium">Invoices</th><th class="py-1 text-right font-medium">Net sales</th></tr></thead>
                        <tbody class="tabular">
                            @foreach ($trend->reverse() as $day)
                                <tr class="border-t border-slate-100"><td class="py-1">{{ $day['label'] }}</td><td class="py-1 text-right">{{ $day['invoices'] }}</td><td class="py-1 text-right">{{ money($day['total']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        {{-- Top medicines --}}
        <section class="card p-5" aria-labelledby="top-title">
            <h2 id="top-title" class="text-sm font-semibold text-ink">Top sellers · 30 days</h2>
            <p class="mt-1 text-xs text-slate-500">By revenue, net of returns</p>
            <x-bar-list class="mt-5" :rows="$topMedicines->map(fn ($m) => [
                'label' => trim($m->name.' '.$m->strength),
                'value' => (float) $m->revenue,
                'display' => money($m->revenue),
                'meta' => number_format($m->units).' units sold',
            ])->all()" />
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Recent sales --}}
        <section class="card overflow-hidden xl:col-span-2" aria-labelledby="recent-title">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 id="recent-title" class="text-sm font-semibold">Recent sales</h2>
                @can('sales.view')<a href="{{ route('sales.index') }}" class="text-sm link">View all</a>@endcan
            </div>
            @if ($recentSales->isEmpty())
                <x-empty icon="receipt" title="No sales yet" description="Completed sales from the POS will appear here." />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Invoice</th><th>Customer</th><th class="hidden sm:table-cell">Cashier</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentSales as $sale)
                                <tr>
                                    <td>
                                        @can('sales.view')<a href="{{ route('sales.show', $sale) }}" class="link font-mono text-[13px]">{{ $sale->invoice_number }}</a>@else<span class="font-mono text-[13px]">{{ $sale->invoice_number }}</span>@endcan
                                        <p class="text-xs text-slate-500">{{ $sale->sold_at->diffForHumans() }}</p>
                                    </td>
                                    <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                                    <td class="hidden sm:table-cell text-slate-500">{{ $sale->user?->name }}</td>
                                    <td><x-badge :tone="$sale->payment_status->tone()" dot>{{ $sale->payment_status->label() }}</x-badge></td>
                                    <td class="tabular text-right font-medium text-ink">{{ money($sale->netTotal()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Payment mix --}}
        <section class="card p-5" aria-labelledby="mix-title">
            <h2 id="mix-title" class="text-sm font-semibold">Collections by method · this month</h2>
            <p class="mt-1 text-xs text-slate-500">Money received from sales and customer payments</p>
            @php $mixTotal = max(0.01, (float) $paymentMix->sum('received')); @endphp
            <x-bar-list class="mt-5" :rows="$paymentMix->filter(fn ($m) => $m->received > 0)->map(fn ($m) => [
                'label' => $m->name,
                'value' => (float) $m->received,
                'display' => money($m->received),
                'meta' => number_format($m->received / $mixTotal * 100, 1).'% of collections',
            ])->values()->all()" empty="No payments received this month." />
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        {{-- Low stock --}}
        <section class="card overflow-hidden" aria-labelledby="low-title">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 id="low-title" class="flex items-center gap-2 text-sm font-semibold"><x-icon name="warning" class="size-4 text-amber-500" /> Low stock</h2>
                <a href="{{ route('medicines.index', ['stock' => 'low']) }}" class="text-sm link">All</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($lowStock as $m)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <a href="{{ route('medicines.show', $m) }}" class="min-w-0 truncate text-sm text-slate-700 hover:text-ink">{{ $m->displayName() }}</a>
                        <span class="shrink-0 text-right">
                            <x-badge :tone="$m->stock_quantity <= 0 ? 'red' : 'amber'">{{ $m->stock_quantity <= 0 ? 'Out of stock' : number_format($m->stock_quantity).' left' }}</x-badge>
                            <span class="mt-0.5 block text-[11px] text-slate-400">reorder at {{ $m->reorderLevel() }}</span>
                        </span>
                    </li>
                @empty
                    <li><x-empty icon="check-circle" title="Stock levels healthy" description="No medicine is below its reorder level." /></li>
                @endforelse
            </ul>
        </section>

        {{-- Expiring --}}
        <section class="card overflow-hidden" aria-labelledby="exp-title">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 id="exp-title" class="flex items-center gap-2 text-sm font-semibold"><x-icon name="clock" class="size-4 text-amber-500" /> Expiring soon</h2>
                <a href="{{ route('reports.expiry') }}" class="text-sm link">Report</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($expiringBatches as $b)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-slate-700">{{ $b->medicine->displayName() }}</p>
                            <p class="text-xs text-slate-500">Batch {{ $b->batch_number }} · {{ number_format($b->quantity) }} units</p>
                        </div>
                        <span class="shrink-0 text-right">
                            <x-badge :tone="$b->daysToExpiry() <= 30 ? 'red' : 'amber'">{{ $b->daysToExpiry() }} days</x-badge>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ $b->expiry_date->format('d M Y') }}</span>
                        </span>
                    </li>
                @empty
                    <li><x-empty icon="check-circle" title="Nothing expiring" description="No stocked batch expires within {{ $alertDays }} days." /></li>
                @endforelse
            </ul>
        </section>

        {{-- Recent purchases / inventory value --}}
        <section class="card overflow-hidden lg:col-span-2 xl:col-span-1" aria-labelledby="pur-title">
            @if ($inventory)
                <div class="grid grid-cols-2 divide-x divide-slate-100 border-b border-slate-100">
                    <div class="p-5">
                        <p class="text-xs font-medium text-slate-500">Inventory at cost</p>
                        <p class="tabular mt-1 text-lg font-semibold">{{ money($inventory['cost']) }}</p>
                    </div>
                    <div class="p-5">
                        <p class="text-xs font-medium text-slate-500">At retail</p>
                        <p class="tabular mt-1 text-lg font-semibold">{{ money($inventory['retail']) }}</p>
                    </div>
                </div>
            @endif
            <div class="flex items-center justify-between px-5 py-4">
                <h2 id="pur-title" class="text-sm font-semibold">Recent purchases</h2>
                @can('purchases.view')<a href="{{ route('purchases.index') }}" class="text-sm link">All</a>@endcan
            </div>
            <ul class="divide-y divide-slate-100 border-t border-slate-100">
                @forelse ($recentPurchases as $p)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('purchases.show', $p) }}" class="link font-mono text-[13px]">{{ $p->reference_no }}</a>
                            <p class="truncate text-xs text-slate-500">{{ $p->supplier?->name }} · {{ $p->purchase_date->format('d M') }}</p>
                        </div>
                        <span class="tabular shrink-0 text-sm font-medium">{{ money($p->total) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No purchases to show.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
