<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" :back="route('suppliers.index')"
                   :description="collect([$supplier->contact_person, $supplier->phone, $supplier->email])->filter()->implode(' · ') ?: null">
        <x-slot:actions>
            @if ($supplier->balance > 0)
                @can('suppliers.payments')
                    <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'collect')"><x-icon name="banknotes" class="size-4" /> Record payment</button>
                @endcan
            @endif
            @can('purchases.create')<a href="{{ route('purchases.create', ['supplier' => $supplier->id]) }}" class="btn-secondary"><x-icon name="inbox" class="size-4" /> New purchase</a>@endcan
            @can('suppliers.manage')
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn-secondary"><x-icon name="pencil" class="size-4" /> Edit</a>
                <x-confirm :action="route('suppliers.destroy', $supplier)" method="DELETE" :title="'Archive '.$supplier->name.'?'" button="Archive" message="Purchase history is kept.">
                    <button type="button" class="btn-ghost text-rose-700 hover:bg-rose-50" aria-label="Archive"><x-icon name="archive" class="size-4" /></button>
                </x-confirm>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Payable balance" :value="money($supplier->balance)" icon="banknotes" :tone="$supplier->balance > 0 ? 'amber' : 'slate'" :hint="$supplier->balance < 0 ? 'Supplier owes you (credit)' : null" />
        <x-stat label="Total purchased" :value="money($stats['total'])" icon="inbox" :hint="$stats['count'].' received purchases'" />
        <x-stat label="Last purchase" :value="$stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->format('d M Y') : '—'" icon="calendar" tone="violet" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Purchase history</h2></div>
            @if ($purchases->isEmpty())
                <x-empty icon="inbox" title="No purchases yet" />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Reference</th><th>Date</th><th>Status</th><th class="text-right">Total</th><th class="text-right">Due</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($purchases as $p)
                            <tr class="{{ $p->isCancelled() ? 'opacity-60' : '' }}">
                                <td><a href="{{ route('purchases.show', $p) }}" class="link font-mono text-[13px]">{{ $p->reference_no }}</a><span class="block text-xs text-slate-500">{{ $p->supplier_invoice_no }}</span></td>
                                <td class="text-slate-500">{{ $p->purchase_date->format('d M Y') }}</td>
                                <td>@if ($p->isCancelled())<x-badge>Cancelled</x-badge>@else<x-badge :tone="$p->payment_status->tone()" dot>{{ $p->payment_status->label() }}</x-badge>@endif</td>
                                <td class="tabular text-right">{{ money($p->total) }}</td>
                                <td class="tabular text-right">{{ $p->isCancelled() ? '—' : money($p->dueAmount()) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $purchases->links() }}
            @endif
        </section>
        <div class="space-y-6">
            <section class="card p-5 text-sm">
                <h2 class="font-semibold">Details</h2>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Address</dt><dd class="text-right">{{ $supplier->address ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Tax no.</dt><dd>{{ $supplier->tax_number ?: '—' }}</dd></div>
                </dl>
                @if ($supplier->notes)<p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-slate-600">{{ $supplier->notes }}</p>@endif
            </section>
            <section class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Products supplied</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($medicines as $m)
                        <li class="flex justify-between px-5 py-2.5"><a href="{{ route('medicines.show', $m) }}" class="hover:text-brand-700">{{ $m->displayName() }}</a><span class="tabular text-slate-500">{{ number_format($m->stock_quantity) }} in stock</span></li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">No medicines list this supplier as preferred.</li>
                    @endforelse
                </ul>
            </section>
            <section class="card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Recent payments</h2></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($payments as $p)
                        <li class="flex justify-between gap-3 px-5 py-2.5"><span>{{ $p->method?->name }}<span class="block text-xs text-slate-500">{{ $p->paid_at->format('d M Y') }} @if($p->purchase) · {{ $p->purchase->reference_no }} @endif</span></span><span class="tabular font-medium">{{ money($p->amount) }}</span></li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">No payments recorded.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    @can('suppliers.payments')
        @include('partials.payment-modal', ['action' => route('suppliers.payments', $supplier), 'title' => 'Pay '.$supplier->name, 'balance' => $supplier->balance, 'hint' => 'Applied to the oldest unpaid purchases first.'])
    @endcan
</x-layouts.app>
