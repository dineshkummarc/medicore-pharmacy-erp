<x-layouts.app title="Returns">
    <x-page-header title="Returns & refunds" description="Reversal documents against original invoices. Completed sales are never deleted.">
        <x-slot:actions>
            @can('returns.create')<a href="{{ route('returns.create') }}" class="btn-primary"><x-icon name="return" class="size-4" /> New return</a>@endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card overflow-hidden">
        <x-filters placeholder="Return or invoice number…"><x-date-range /></x-filters>
        @if ($returns->isEmpty())
            <x-empty icon="return" title="No returns" description="Processed returns will be listed here." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Return</th><th>Invoice</th><th>Date</th><th>Customer</th><th class="hidden lg:table-cell">Reason</th><th class="text-right">Value</th><th class="text-right">Refunded</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($returns as $r)
                        <tr>
                            <td><a href="{{ route('returns.show', $r) }}" class="link font-mono text-[13px]">{{ $r->return_number }}</a></td>
                            <td class="font-mono text-[13px]">@can('sales.view')<a href="{{ route('sales.show', $r->sale_id) }}" class="hover:text-brand-700">{{ $r->sale?->invoice_number }}</a>@else{{ $r->sale?->invoice_number }}@endcan</td>
                            <td class="whitespace-nowrap text-slate-500">{{ $r->returned_at->format('d M Y, H:i') }}</td>
                            <td>{{ $r->customer?->name ?? 'Walk-in' }}</td>
                            <td class="hidden lg:table-cell max-w-xs truncate text-slate-500">{{ $r->reason }}</td>
                            <td class="tabular text-right font-medium">{{ money($r->total) }}</td>
                            <td class="tabular text-right">{{ money($r->refund_amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $returns->links() }}
        @endif
    </div>
</x-layouts.app>
