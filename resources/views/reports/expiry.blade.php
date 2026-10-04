<x-layouts.app title="Expiry report">
    <x-page-header title="Expiry report" :description="$scope === 'expired' ? 'Batches past their expiry date still held in stock.' : 'In-stock batches expiring within '.$days.' days.'">
        <x-slot:actions><a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> CSV</a></x-slot:actions>
    </x-page-header>
    @include('reports._nav')
    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
        <div class="flex gap-1.5">
            <a href="{{ route('reports.expiry', ['scope' => 'soon', 'days' => $days]) }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $scope === 'soon' ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-600' }}">Expiring soon</a>
            <a href="{{ route('reports.expiry', ['scope' => 'expired']) }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ $scope === 'expired' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600' }}">Already expired</a>
        </div>
        @if ($scope === 'soon')
            <input type="hidden" name="scope" value="soon">
            <div><label for="days" class="field-label text-xs">Within (days)</label><input id="days" type="number" name="days" min="1" max="730" value="{{ $days }}" class="field-input w-28 py-1.5"></div>
            <button class="btn-secondary">Apply</button>
        @endif
        <p class="ml-auto text-sm text-slate-600">Stock value at cost: <strong class="tabular">{{ money($value) }}</strong></p>
    </form>
    <section class="card overflow-hidden">
        @if ($batches->isEmpty())
            <x-empty icon="check-circle" title="Nothing to report" :description="$scope === 'expired' ? 'No expired stock on the shelves.' : 'No stock expires in this window.'" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="text-right">Days</th><th class="text-right">Qty</th><th class="text-right">Cost value</th><th class="hidden md:table-cell">Supplier</th>@can('inventory.adjust')<th></th>@endcan</tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($batches as $b)
                        @php $d = $b->daysToExpiry(); @endphp
                        <tr>
                            <td><a href="{{ route('medicines.show', $b->medicine_id) }}" class="font-medium text-ink hover:text-brand-700">{{ $b->medicine?->displayName() }}</a></td>
                            <td class="font-mono text-xs">{{ $b->batch_number }}</td>
                            <td class="whitespace-nowrap">{{ $b->expiry_date->format('d M Y') }}</td>
                            <td class="text-right"><x-badge :tone="$d <= 0 ? 'red' : ($d <= 30 ? 'red' : 'amber')">{{ $d <= 0 ? abs($d).'d ago' : $d.'d' }}</x-badge></td>
                            <td class="tabular text-right">{{ number_format($b->quantity) }}</td>
                            <td class="tabular text-right">{{ money($b->quantity * $b->purchase_price) }}</td>
                            <td class="hidden md:table-cell text-slate-500">{{ $b->supplier?->name ?? '—' }}</td>
                            @can('inventory.adjust')
                                <td class="text-right">
                                    @if ($b->isExpired())
                                        <x-confirm :action="route('batches.write-off', $b)" title="Write off expired stock?" button="Write off" :message="'Remove '.$b->quantity.' units of batch '.$b->batch_number.'.'">
                                            <button type="button" class="btn-ghost btn-sm text-rose-700">Write off</button>
                                        </x-confirm>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $batches->links() }}
        @endif
    </section>
</x-layouts.app>
