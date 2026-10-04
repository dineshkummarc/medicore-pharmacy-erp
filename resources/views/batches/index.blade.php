<x-layouts.app title="Batches & expiry">
    <x-page-header title="Batches & expiry" description="Batch-level stock. Expired batches cannot be sold and can be written off.">
        <x-slot:actions>
            @can('reports.view')<a href="{{ route('reports.expiry') }}" class="btn-secondary"><x-icon name="clock" class="size-4" /> Expiry report</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <x-filters placeholder="Search medicine…">
            <x-filter-select name="expiry" :options="['expired' => 'Expired', 'soon' => 'Expiring ≤ '.$alertDays.' days', 'ok' => 'In date']" placeholder="Any expiry" />
            <x-filter-select name="stock" :options="['all' => 'Include empty batches']" placeholder="In stock only" />
        </x-filters>

        @if ($batches->isEmpty())
            <x-empty icon="cube" title="No batches match" description="Batches are created when stock is received." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Medicine</th><th>Batch</th><x-sort key="expiry" label="Expiry" /><x-sort key="quantity" label="Qty" align="right" /><th class="hidden md:table-cell">Supplier</th>@can('reports.financial')<th class="text-right">Cost value</th>@endcan<th>Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($batches as $batch)
                        @php $days = $batch->daysToExpiry(); @endphp
                        <tr>
                            <td><a href="{{ route('medicines.show', $batch->medicine_id) }}" class="font-medium text-ink hover:text-brand-700">{{ $batch->medicine?->displayName() }}</a></td>
                            <td class="font-mono text-xs">{{ $batch->batch_number }}</td>
                            <td class="whitespace-nowrap">{{ $batch->expiry_date->format('d M Y') }}</td>
                            <td class="tabular text-right font-medium">{{ number_format($batch->quantity) }}</td>
                            <td class="hidden md:table-cell text-slate-500">{{ $batch->supplier?->name ?? '—' }}</td>
                            @can('reports.financial')<td class="tabular text-right">{{ money($batch->quantity * $batch->purchase_price) }}</td>@endcan
                            <td>
                                @if ($batch->isExpired())
                                    <x-badge tone="red" dot>Expired {{ abs($days) }}d ago</x-badge>
                                @elseif ($days <= $alertDays)
                                    <x-badge tone="amber" dot>{{ $days }} days left</x-badge>
                                @else
                                    <x-badge tone="green" dot>In date</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $batches->links() }}
        @endif
    </div>
</x-layouts.app>
