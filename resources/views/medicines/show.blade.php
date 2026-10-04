<x-layouts.app :title="$medicine->name">
    <x-page-header :title="$medicine->displayName()" :back="route('medicines.index')"
                   :description="collect([$medicine->generic_name, $medicine->manufacturer?->name, $medicine->category?->name])->filter()->implode(' · ') ?: null">
        <x-slot:actions>
            @if ($medicine->trashed())
                <x-badge tone="gray">Archived</x-badge>
                @can('medicines.delete')
                    <form method="POST" action="{{ route('medicines.restore', $medicine) }}">@csrf<button class="btn-secondary"><x-icon name="refresh" class="size-4" /> Restore</button></form>
                @endcan
            @else
                <a href="{{ route('medicines.barcode', $medicine) }}" class="btn-secondary" target="_blank" rel="noopener"><x-icon name="barcode" class="size-4" /> Labels</a>
                @can('inventory.adjust')
                    <a href="{{ route('batches.create', $medicine) }}" class="btn-secondary"><x-icon name="plus" class="size-4" /> Opening stock</a>
                @endcan
                @can('medicines.update')
                    <a href="{{ route('medicines.edit', $medicine) }}" class="btn-primary"><x-icon name="pencil" class="size-4" /> Edit</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="On hand" :value="number_format($medicine->stock_quantity).' '.Str::plural($medicine->unit, $medicine->stock_quantity)" icon="cube"
                :tone="$medicine->stock_quantity <= 0 ? 'rose' : ($medicine->isLowStock() ? 'amber' : 'brand')" :hint="'Reorder level '.$medicine->reorderLevel()" />
        <x-stat label="Sellable (not expired)" :value="number_format($sellable)" icon="check-circle" tone="emerald"
                :hint="$medicine->stock_quantity - $sellable > 0 ? number_format($medicine->stock_quantity - $sellable).' expired units held' : 'All stock is in date'" />
        <x-stat label="Selling price" :value="money($medicine->selling_price)" icon="tag" tone="blue"
                :hint="auth()->user()->can('reports.financial') ? 'Cost '.money($medicine->purchase_price).' · '.($medicine->selling_price > 0 ? number_format(($medicine->selling_price - $medicine->purchase_price) / $medicine->selling_price * 100, 1) : 0).'% margin' : 'per '.$medicine->unit" />
        <x-stat label="Sold · last 30 days" :value="number_format($soldLast30)" icon="trend-up" tone="violet" hint="units, before returns" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2" aria-labelledby="batches-title">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 id="batches-title" class="text-sm font-semibold">Batches</h2>
                <span class="text-xs text-slate-500">Sold first-expiry-first-out</span>
            </div>
            @if ($batches->isEmpty())
                <x-empty icon="cube" title="No stock received yet" description="Receive stock through a purchase or add opening stock." />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Batch</th><th>Expiry</th><th class="text-right">Qty</th><th class="text-right">Received</th>@can('reports.financial')<th class="text-right">Cost</th>@endcan @can('inventory.adjust')<th><span class="sr-only">Actions</span></th>@endcan</tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($batches as $batch)
                            @php $days = $batch->daysToExpiry(); @endphp
                            <tr class="{{ $batch->quantity <= 0 ? 'opacity-60' : '' }}">
                                <td><span class="font-mono text-[13px]">{{ $batch->batch_number }}</span><p class="text-xs text-slate-500">{{ $batch->received_at?->format('d M Y') }}</p></td>
                                <td>
                                    {{ $batch->expiry_date->format('d M Y') }}
                                    @if ($batch->quantity > 0)
                                        @if ($batch->isExpired())
                                            <x-badge tone="red" class="ml-1">Expired</x-badge>
                                        @elseif ($days <= (int) settings('expiry_alert_days', 90))
                                            <x-badge tone="amber" class="ml-1">{{ $days }}d left</x-badge>
                                        @endif
                                    @endif
                                </td>
                                <td class="tabular text-right font-medium">{{ number_format($batch->quantity) }}</td>
                                <td class="tabular text-right text-slate-500">{{ number_format($batch->quantity_received) }}</td>
                                @can('reports.financial')<td class="tabular text-right">{{ money($batch->purchase_price) }}</td>@endcan
                                @can('inventory.adjust')
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="btn-ghost btn-sm" @click="$dispatch('open-modal', 'adjust-{{ $batch->id }}')"><x-icon name="adjust" class="size-4" /> Adjust</button>
                                            @if ($batch->isExpired() && $batch->quantity > 0)
                                                <x-confirm :action="route('batches.write-off', $batch)" title="Write off expired stock?" button="Write off"
                                                           :message="'Remove all '.$batch->quantity.' units of batch '.$batch->batch_number.' from stock. This is recorded as an inventory loss.'">
                                                    <button type="button" class="btn-sm btn text-rose-700 hover:bg-rose-50">Write off</button>
                                                </x-confirm>
                                            @endif
                                        </div>
                                        <x-modal :name="'adjust-'.$batch->id" title="Adjust batch {{ $batch->batch_number }}">
                                            <form method="POST" action="{{ route('batches.adjust', $batch) }}" class="space-y-4 text-left" x-data="{ direction: 'remove' }">
                                                @csrf
                                                <p class="text-sm text-slate-600">Currently <strong>{{ $batch->quantity }}</strong> units. Use adjustments for counts, damage or breakage. Every adjustment is logged.</p>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm" :class="direction === 'add' ? 'border-brand-500 bg-brand-50' : 'border-slate-200'">
                                                        <input type="radio" name="direction" value="add" x-model="direction" class="text-brand-600 focus:ring-brand-500"> Add units
                                                    </label>
                                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm" :class="direction === 'remove' ? 'border-rose-400 bg-rose-50' : 'border-slate-200'">
                                                        <input type="radio" name="direction" value="remove" x-model="direction" class="text-rose-600 focus:ring-rose-500"> Remove units
                                                    </label>
                                                </div>
                                                <x-input name="quantity" type="number" min="1" label="Quantity" required id="adj-qty-{{ $batch->id }}" />
                                                <x-input name="reason" label="Reason" required minlength="3" maxlength="255" placeholder="e.g. Physical count correction, damaged pack" id="adj-reason-{{ $batch->id }}" />
                                                <div class="flex justify-end gap-2 pt-2">
                                                    <button type="button" class="btn-secondary" @click="$dispatch('close-modal', 'adjust-{{ $batch->id }}')">Cancel</button>
                                                    <button class="btn-primary">Save adjustment</button>
                                                </div>
                                            </form>
                                        </x-modal>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card p-5" aria-labelledby="details-title">
            <h2 id="details-title" class="text-sm font-semibold">Details</h2>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach ([
                    'SKU' => $medicine->sku,
                    'Barcode' => $medicine->barcode ?: '—',
                    'Unit' => ucfirst($medicine->unit),
                    settings('tax_label', 'Tax') => rtrim(rtrim($medicine->tax_rate, '0'), '.').'%',
                    'Preferred supplier' => $medicine->supplier?->name ?? '—',
                    'Prescription' => $medicine->requires_prescription ? 'Required' : 'Not required',
                    'Status' => $medicine->is_active ? 'Active' : 'Inactive',
                ] as $term => $detail)
                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 last:border-0">
                        <dt class="text-slate-500">{{ $term }}</dt>
                        <dd class="text-right font-medium text-ink">{{ $detail }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($medicine->description)
                <p class="mt-4 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $medicine->description }}</p>
            @endif
            @can('medicines.delete')
                @unless ($medicine->trashed())
                    <div class="mt-6 border-t border-slate-100 pt-4">
                        <x-confirm :action="route('medicines.destroy', $medicine)" method="DELETE" title="Archive {{ $medicine->name }}?" button="Archive"
                                   message="The medicine is hidden from the catalogue and POS. Its batches, invoices and stock history are kept and it can be restored.">
                            <button type="button" class="btn-ghost w-full text-rose-700 hover:bg-rose-50"><x-icon name="archive" class="size-4" /> Archive medicine</button>
                        </x-confirm>
                    </div>
                @endunless
            @endcan
        </section>
    </div>

    <section class="card mt-6 overflow-hidden" aria-labelledby="mov-title">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 id="mov-title" class="text-sm font-semibold">Recent stock movements</h2>
            @can('inventory.view')<a href="{{ route('stock-movements.index', ['medicine' => $medicine->id]) }}" class="text-sm link">Full history</a>@endcan
        </div>
        @include('stock-movements._table', ['movements' => $movements, 'showMedicine' => false])
    </section>
</x-layouts.app>
