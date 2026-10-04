<x-layouts.app title="Profit & loss">
    <x-page-header title="Profit & loss" :description="$from->format('d M Y').' – '.$to->format('d M Y').' · estimate based on batch costs'" />
    @include('reports._nav')
    @include('reports._range')

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-1">
            <h2 class="text-sm font-semibold">Statement</h2>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex justify-between"><dt>Net sales (incl. tax)</dt><dd class="tabular">{{ money($summary['net']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>less tax collected</dt><dd class="tabular">−{{ money($summary['net'] - $pnl['revenue']) }}</dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-medium"><dt>Revenue</dt><dd class="tabular">{{ money($pnl['revenue']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>Cost of goods sold</dt><dd class="tabular">−{{ money($pnl['cogs']) }}</dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 font-medium"><dt>Gross profit <span class="font-normal text-slate-500">({{ number_format($pnl['gross_margin'], 1) }}%)</span></dt><dd class="tabular">{{ money($pnl['gross_profit']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>Operating expenses</dt><dd class="tabular">−{{ money($pnl['expenses']) }}</dd></div>
                <div class="flex justify-between text-slate-500"><dt>Stock write-offs & adjustments</dt><dd class="tabular">{{ $pnl['inventory_losses'] >= 0 ? '−' : '+' }}{{ money(abs($pnl['inventory_losses'])) }}</dd></div>
                <div class="flex justify-between border-t-2 border-slate-200 pt-3 text-base font-semibold {{ $pnl['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}"><dt>Net profit</dt><dd class="tabular">{{ money($pnl['net_profit']) }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-slate-500">Revenue and cost exclude returned units. Tax is shown for reference and excluded from profit.</p>
        </section>
        <section class="card p-5 lg:col-span-2">
            <h2 class="text-sm font-semibold">Net sales trend</h2>
            <div class="mt-4 h-80"><canvas x-data="lineChart" role="img" aria-label="Net sales trend" data-chart="{{ json_encode(['labels' => $trend->pluck('label'), 'values' => $trend->pluck('total'), 'currency' => settings('currency_symbol')]) }}"></canvas></div>
        </section>
    </div>
</x-layouts.app>
