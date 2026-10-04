{{-- Generic tabular report. $columns: label => type (text|muted|number|money|date). --}}
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :description="isset($from) ? $from->format('d M Y').' – '.$to->format('d M Y') : 'Current stock position'">
        <x-slot:actions><a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> CSV</a></x-slot:actions>
    </x-page-header>
    @include('reports._nav')
    @unless ($noRange ?? false) @include('reports._range') @endunless

    @if (! empty($stats))
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($stats as $label => $value)
                <div class="card px-5 py-4"><p class="text-xs font-medium text-slate-500">{{ $label }}</p><p class="tabular mt-1 text-xl font-semibold">{{ $value }}</p></div>
            @endforeach
        </div>
    @endif

    @if (! empty($chart) && count($chart['labels']))
        <section class="card mb-6 p-5">
            <div class="h-64"><canvas x-data="barChart" role="img" aria-label="{{ $title }} chart" data-chart="{{ json_encode($chart + ['currency' => settings('currency_symbol')]) }}"></canvas></div>
        </section>
    @endif

    <section class="card overflow-hidden">
        @if ($rows->isEmpty())
            <x-empty icon="chart" title="No data" description="Nothing matches this report for the selected period." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr>@foreach ($columns as $label => $type)<th class="{{ in_array($type, ['number', 'money']) ? 'text-right' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <tr>
                            @foreach (array_values($columns) as $i => $type)
                                @php $cell = array_values((array) $row)[$i] ?? null; @endphp
                                <td class="{{ match ($type) { 'number', 'money' => 'tabular text-right', 'muted' => 'text-slate-500', default => 'font-medium text-ink' } }}">
                                    @switch($type)
                                        @case('money') {{ money($cell) }} @break
                                        @case('number') {{ number_format((float) $cell) }} @break
                                        @case('date') {{ $cell ? \Illuminate\Support\Carbon::parse($cell)->format('d M Y') : '—' }} @break
                                        @default {{ $cell ?? '—' }}
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                    @if (! empty($totals) && $rows->currentPage() === $rows->lastPage())
                        <tfoot><tr class="border-t-2 border-slate-200 font-semibold">
                            @foreach (array_values($columns) as $i => $type)
                                <td class="px-4 py-3 first:pl-5 last:pr-5 {{ in_array($type, ['number', 'money']) ? 'tabular text-right' : '' }}">
                                    @php $t = $totals[$i] ?? ''; @endphp
                                    {{ $t === '' ? '' : ($type === 'money' && is_numeric($t) ? money($t) : ($type === 'number' && is_numeric($t) ? number_format($t) : $t)) }}
                                </td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
            {{ $rows->links() }}
        @endif
    </section>
    @isset($note)<p class="mt-3 text-xs text-slate-500">{{ $note }}</p>@endisset
</x-layouts.app>
