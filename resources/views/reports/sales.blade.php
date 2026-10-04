<x-layouts.app title="Sales report">
    <x-page-header title="Sales" :description="$from->format('d M Y').' – '.$to->format('d M Y')">
        <x-slot:actions><a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> CSV</a></x-slot:actions>
    </x-page-header>
    @include('reports._nav')
    @component('reports._range', ['from' => $from, 'to' => $to])
        <div><label for="r-group" class="field-label text-xs">Group by</label>
            <select id="r-group" name="group" class="field-input py-1.5 pr-8">
                @foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $k => $v)<option value="{{ $k }}" @selected($granularity === $k)>{{ $v }}</option>@endforeach
            </select></div>
    @endcomponent

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Net sales" :value="money($summary['net'])" icon="banknotes" />
        <x-stat label="Invoices" :value="number_format($summary['invoices'])" icon="document" tone="blue" :hint="'avg '.money($summary['average'])" />
        <x-stat label="Discounts" :value="money($summary['discounts'])" icon="tag" tone="amber" />
        <x-stat label="Tax collected" :value="money($summary['tax'])" icon="receipt" tone="slate" />
    </div>

    <section class="card mt-6 p-5">
        <h2 class="text-sm font-semibold">Net sales by {{ $granularity }}</h2>
        <div class="mt-4 h-72">
            <canvas x-data="{{ $trend->count() > 40 ? 'lineChart' : 'barChart' }}" role="img" aria-label="Net sales by {{ $granularity }}"
                    data-chart="{{ json_encode(['labels' => $trend->pluck('label'), 'values' => $trend->pluck('total'), 'currency' => settings('currency_symbol')]) }}"></canvas>
        </div>
    </section>

    <section class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Period</th><th class="text-right">Invoices</th><th class="text-right">Net sales</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($trend->reverse() as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="tabular text-right">{{ number_format($row['invoices']) }}</td><td class="tabular text-right font-medium">{{ money($row['total']) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr class="border-t-2 border-slate-200 font-semibold"><td class="px-5 py-3">Total</td><td class="tabular px-4 py-3 text-right">{{ number_format($trend->sum('invoices')) }}</td><td class="tabular px-5 py-3 text-right">{{ money($trend->sum('total')) }}</td></tr></tfoot>
            </table>
        </div>
    </section>
</x-layouts.app>
