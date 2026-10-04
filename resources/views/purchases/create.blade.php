<x-layouts.app title="New purchase">
    <x-page-header title="New purchase" description="Enter the supplier's delivery. Stock, batches and the supplier balance update when you save." :back="route('purchases.index')" />

    <form method="POST" action="{{ route('purchases.store') }}" x-data="purchaseForm"
          data-lookup="{{ route('medicines.lookup') }}" data-currency="{{ settings('currency_symbol') }}"
          data-old='@json(array_values(old('items', [])))' data-paid="{{ old('paid_amount') }}" class="space-y-6">
        @csrf
        <section class="card p-6">
            <div class="grid gap-4 md:grid-cols-4">
                <x-select name="supplier_id" label="Supplier" :options="$suppliers->pluck('name', 'id')" :value="$selectedSupplier" placeholder="Select supplier…" required class="md:col-span-2" />
                <x-input name="supplier_invoice_no" label="Supplier invoice no." maxlength="80" />
                <x-input name="purchase_date" type="date" label="Purchase date" :value="today()->toDateString()" :max="today()->toDateString()" required />
            </div>
            @if ($suppliers->isEmpty())
                <p class="mt-3 text-sm text-amber-700">No suppliers yet. <a href="{{ route('suppliers.create') }}" class="underline">Add one first.</a></p>
            @endif
        </section>

        <section class="card">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-semibold">Items</h2>
                <div class="relative w-full sm:max-w-sm" @click.outside="results = []">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <label for="purchase-lookup" class="sr-only">Add medicine</label>
                    <input id="purchase-lookup" type="search" x-model="query" @input="onInput" @keydown.enter.prevent="results[0] && add(results[0])"
                           placeholder="Add medicine by name, SKU or barcode…" class="field-input pl-9" autocomplete="off">
                    <ul x-cloak x-show="results.length" class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-pop">
                        <template x-for="m in results" :key="m.id">
                            <li><button type="button" @click="add(m)" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50">
                                <span><span class="font-medium" x-text="m.name"></span><span class="block text-xs text-slate-500" x-text="m.sku + ' · ' + (m.generic_name || '')"></span></span>
                                <span class="text-xs text-slate-500" x-text="m.stock + ' in stock'"></span>
                            </button></li>
                        </template>
                    </ul>
                </div>
            </div>

            @error('items')<p class="field-error px-5 pt-3">{{ $message }}</p>@enderror

            <div x-show="!items.length" class="px-6 py-12 text-center text-sm text-slate-500">Search above to add the medicines on this delivery.</div>

            <div x-cloak x-show="items.length" class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th class="min-w-44">Medicine</th><th class="min-w-32">Batch no.</th><th class="min-w-36">Expiry</th><th class="min-w-28">Qty</th><th class="min-w-28">Unit cost</th><th class="min-w-28">Sell price</th><th class="min-w-28">Discount</th><th class="min-w-24">Tax %</th><th class="text-right">Line total</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, i) in items" :key="i">
                            <tr>
                                <td>
                                    <span class="font-medium text-ink" x-text="item._name"></span>
                                    <input type="hidden" :name="`items[${i}][medicine_id]`" :value="item.medicine_id">
                                    <input type="hidden" :name="`items[${i}][_name]`" :value="item._name">
                                </td>
                                <td><input data-batch-input :name="`items[${i}][batch_number]`" x-model="item.batch_number" required maxlength="60" class="field-input py-1.5 font-mono text-xs" aria-label="Batch number"></td>
                                <td><input type="date" :name="`items[${i}][expiry_date]`" x-model="item.expiry_date" required min="{{ today()->addDay()->toDateString() }}" class="field-input py-1.5" aria-label="Expiry date"></td>
                                <td><input type="number" min="1" :name="`items[${i}][quantity]`" style="min-width: 5.5rem" x-model.number="item.quantity" required class="field-input py-1.5" aria-label="Quantity"></td>
                                <td><input type="number" step="0.01" min="0" :name="`items[${i}][unit_cost]`" style="min-width: 5.5rem" x-model="item.unit_cost" required class="field-input py-1.5" aria-label="Unit cost"></td>
                                <td><input type="number" step="0.01" min="0" :name="`items[${i}][selling_price]`" style="min-width: 5.5rem" x-model="item.selling_price" class="field-input py-1.5" aria-label="Selling price"></td>
                                <td><input type="number" step="0.01" min="0" :name="`items[${i}][discount]`" style="min-width: 5.5rem" x-model="item.discount" class="field-input py-1.5" aria-label="Discount amount"></td>
                                <td><input type="number" step="0.01" min="0" max="100" :name="`items[${i}][tax_rate]`" style="min-width: 5.5rem" x-model="item.tax_rate" class="field-input py-1.5" aria-label="Tax rate"></td>
                                <td class="tabular text-right font-medium" x-text="fmt(line(item).total)"></td>
                                <td><button type="button" @click="remove(i)" class="btn-ghost btn-sm text-rose-600" aria-label="Remove line"><x-icon name="trash" class="size-4" /></button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            @if ($errors->has('items.*'))
                <ul class="space-y-1 border-t border-rose-100 bg-rose-50 px-5 py-3 text-xs text-rose-700">
                    @foreach (collect($errors->get('items.*'))->flatten()->unique() as $message)<li>{{ $message }}</li>@endforeach
                </ul>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card p-6 lg:col-span-2">
                <x-textarea name="notes" label="Notes" rows="4" maxlength="2000" />
            </section>
            <section class="card space-y-4 p-6">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular" x-text="fmt(totals.subtotal)"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular" x-text="'−' + fmt(totals.discount)"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="tabular" x-text="fmt(totals.tax)"></dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular" x-text="fmt(totals.total)"></dd></div>
                </dl>
                <div>
                    <label for="f-paid_amount" class="field-label">Paid now</label>
                    <input id="f-paid_amount" type="number" step="0.01" min="0" name="paid_amount" x-model="paid" class="field-input @error('paid_amount') has-error @enderror" placeholder="0.00">
                    @error('paid_amount')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-select name="payment_method_id" label="Paid via" :options="$methods->pluck('name', 'id')" placeholder="—" />
                <p class="text-sm text-slate-500">Added to supplier balance: <strong class="tabular text-ink" x-text="fmt(due)"></strong></p>
                <button type="submit" class="btn-primary w-full" :disabled="!items.length">Save purchase & receive stock</button>
            </section>
        </div>
    </form>
</x-layouts.app>
