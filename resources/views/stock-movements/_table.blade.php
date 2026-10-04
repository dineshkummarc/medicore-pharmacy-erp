@if ($movements->isEmpty())
    <x-empty icon="arrows" title="No stock movements" description="Every purchase, sale, return and adjustment is recorded here." />
@else
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>When</th>@if($showMedicine)<th>Medicine</th>@endif<th>Type</th><th>Batch</th><th class="text-right">Change</th><th class="text-right">Balance</th><th class="hidden md:table-cell">By</th><th class="hidden lg:table-cell">Note</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($movements as $m)
                <tr>
                    <td class="whitespace-nowrap text-slate-500">{{ $m->created_at->format('d M Y, H:i') }}</td>
                    @if ($showMedicine)
                        <td><a href="{{ route('medicines.show', $m->medicine_id) }}" class="font-medium text-ink hover:text-brand-700">{{ $m->medicine?->displayName() }}</a></td>
                    @endif
                    <td>
                        <x-badge :tone="$m->type->tone()">{{ $m->type->label() }}</x-badge>
                        @if ($m->reference_type === 'sale' && auth()->user()->can('sales.view'))
                            <a href="{{ route('sales.show', $m->reference_id) }}" class="ml-1 text-xs link">invoice</a>
                        @elseif ($m->reference_type === 'purchase' && auth()->user()->can('purchases.view'))
                            <a href="{{ route('purchases.show', $m->reference_id) }}" class="ml-1 text-xs link">purchase</a>
                        @elseif ($m->reference_type === 'sale_return' && auth()->user()->can('returns.view'))
                            <a href="{{ route('returns.show', $m->reference_id) }}" class="ml-1 text-xs link">return</a>
                        @endif
                    </td>
                    <td class="font-mono text-xs text-slate-500">{{ $m->batch?->batch_number ?? '—' }}</td>
                    <td class="tabular text-right font-medium {{ $m->quantity >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ number_format($m->quantity) }}</td>
                    <td class="tabular text-right">{{ number_format($m->balance_after) }}</td>
                    <td class="hidden md:table-cell text-slate-500">{{ $m->user?->name ?? 'System' }}</td>
                    <td class="hidden lg:table-cell max-w-xs truncate text-slate-500" title="{{ $m->note }}">{{ $m->note }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
