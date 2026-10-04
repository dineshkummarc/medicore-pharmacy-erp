<x-layouts.app :title="$customer->name">
    <x-page-header :title="$customer->name" :back="route('customers.index')"
                   :description="collect([$customer->phone, $customer->email])->filter()->implode(' · ') ?: 'No contact details'">
        <x-slot:actions>
            @if ($customer->balance > 0)
                @can('customers.payments')
                    <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'collect')"><x-icon name="banknotes" class="size-4" /> Collect payment</button>
                @endcan
            @endif
            @can('customers.manage')
                <a href="{{ route('customers.edit', $customer) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</a>
                <x-confirm :action="route('customers.destroy', $customer)" method="DELETE" :title="'Archive '.$customer->name.'?'" button="Archive" message="Their invoices and history are kept.">
                    <button type="button" class="btn-ghost text-rose-700 hover:bg-rose-50"><x-icon name="archive" class="size-4" /></button>
                </x-confirm>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Balance due" :value="money($customer->balance)" icon="banknotes" :tone="$customer->balance > 0 ? 'amber' : 'slate'" />
        <x-stat label="Lifetime purchases" :value="money($stats['lifetime'] - $stats['returned'])" icon="trend-up" hint="net of returns" />
        <x-stat label="Invoices" :value="number_format($stats['count'])" icon="document" tone="blue" />
        <x-stat label="Last purchase" :value="$stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->format('d M Y') : '—'" icon="calendar" tone="violet"
                :hint="$stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->diffForHumans() : null" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Purchase history</h2></div>
            @if ($sales->isEmpty())
                <x-empty icon="receipt" title="No purchases yet" />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Due</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $sale)
                            <tr>
                                <td><a href="{{ route('sales.show', $sale) }}" class="link font-mono text-[13px]">{{ $sale->invoice_number }}</a></td>
                                <td class="text-slate-500">{{ $sale->sold_at->format('d M Y, H:i') }}</td>
                                <td><x-badge :tone="$sale->payment_status->tone()" dot>{{ $sale->payment_status->label() }}</x-badge>
                                    @if ($sale->status !== \App\Enums\SaleStatus::Completed)<x-badge :tone="$sale->status->tone()">{{ $sale->status->label() }}</x-badge>@endif</td>
                                <td class="tabular text-right">{{ money($sale->netTotal()) }}</td>
                                <td class="tabular text-right {{ $sale->dueAmount() > 0 ? 'font-medium text-amber-700' : 'text-slate-400' }}">{{ money($sale->dueAmount()) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $sales->links() }}
            @endif
        </section>

        <div class="space-y-6">
            <section class="card p-5">
                <h2 class="text-sm font-semibold">Details</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Address</dt><dd class="text-right">{{ $customer->address ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Date of birth</dt><dd>{{ $customer->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Customer since</dt><dd>{{ $customer->created_at->format('d M Y') }}</dd></div>
                </dl>
                @if ($customer->notes)<p class="mt-3 whitespace-pre-line rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $customer->notes }}</p>@endif
            </section>
            <section class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Recent payments</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($payments as $p)
                        <li class="flex justify-between gap-3 px-5 py-2.5">
                            <span><span class="block">{{ $p->type->label() }} · {{ $p->method?->name }}</span><span class="text-xs text-slate-500">{{ $p->paid_at->format('d M Y') }} @if($p->sale) · {{ $p->sale->invoice_number }} @endif</span></span>
                            <span class="tabular font-medium {{ $p->direction === 'out' ? 'text-rose-600' : '' }}">{{ $p->direction === 'out' ? '−' : '' }}{{ money($p->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">No payments recorded.</li>
                    @endforelse
                </ul>
            </section>
            @if ($returns->isNotEmpty())
                <section class="card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Returns</h2></div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($returns as $r)
                            <li class="flex justify-between px-5 py-2.5"><a href="{{ route('returns.show', $r) }}" class="link font-mono text-[13px]">{{ $r->return_number }}</a><span class="tabular">{{ money($r->total) }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    @can('customers.payments')
        @include('partials.payment-modal', ['action' => route('customers.payments', $customer), 'title' => 'Collect payment from '.$customer->name, 'balance' => $customer->balance, 'hint' => 'Applied to the oldest unpaid invoices first.'])
    @endcan
</x-layouts.app>
