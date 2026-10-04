<x-layouts.app :title="'Invoice '.$sale->invoice_number">
    <x-page-header :title="'Invoice '.$sale->invoice_number" :back="route('sales.index')" :description="$sale->sold_at->format('l, d M Y \a\t H:i').' · '.($sale->customer?->name ?? 'Walk-in customer')">
        <x-slot:actions>
            <x-badge :tone="$sale->payment_status->tone()" dot>{{ $sale->payment_status->label() }}</x-badge>
            @if ($sale->status !== \App\Enums\SaleStatus::Completed)<x-badge :tone="$sale->status->tone()">{{ $sale->status->label() }}</x-badge>@endif
            <a href="{{ route('sales.print', $sale) }}" target="_blank" rel="noopener" class="btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
            <a href="{{ route('sales.pdf', $sale) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> PDF</a>
            @if ($sale->status !== \App\Enums\SaleStatus::Returned)
                @can('returns.create')<a href="{{ route('returns.create', ['invoice' => $sale->invoice_number]) }}" class="btn-secondary"><x-icon name="return" class="size-4" /> Return items</a>@endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card p-6 sm:p-8 xl:col-span-2">
            @include('invoices._document', ['sale' => $sale])
        </div>
        <div class="space-y-6">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Payments</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($sale->payments as $p)
                        <li class="flex justify-between gap-3 px-5 py-2.5">
                            <span>{{ $p->type->label() }} · {{ $p->method?->name }}<span class="block text-xs text-slate-500">{{ $p->paid_at->format('d M Y, H:i') }}</span></span>
                            <span class="tabular font-medium {{ $p->direction === 'out' ? 'text-rose-600' : '' }}">{{ $p->direction === 'out' ? '−' : '' }}{{ money($p->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">Sold on credit, no payment yet.</li>
                    @endforelse
                </ul>
                @if ($sale->dueAmount() > 0 && $sale->customer)
                    <div class="border-t border-slate-100 bg-amber-50 px-5 py-3 text-sm text-amber-900">
                        {{ money($sale->dueAmount()) }} outstanding.
                        @can('customers.payments')<a href="{{ route('customers.show', $sale->customer_id) }}" class="font-medium underline">Collect from customer</a>@endcan
                    </div>
                @endif
            </section>
            @if ($sale->returns->isNotEmpty())
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Returns</h2></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($sale->returns as $r)
                            <li class="px-5 py-2.5">
                                <div class="flex justify-between"><a href="{{ route('returns.show', $r) }}" class="link font-mono text-[13px]">{{ $r->return_number }}</a><span class="tabular font-medium">{{ money($r->total) }}</span></div>
                                <p class="text-xs text-slate-500">{{ $r->returned_at->format('d M Y') }} · {{ $r->user?->name }} · {{ $r->reason }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
