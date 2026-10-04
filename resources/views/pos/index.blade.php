<x-layouts.app title="Point of sale" :wide="true">
<div x-data="pos" @keydown.window="shortcuts($event)"
     data-config='@json($config)'
     data-methods='@json($methods)'
     data-routes="{{ json_encode(['search' => route('pos.search'), 'customers' => route('customers.lookup'), 'quickCustomer' => route('customers.quick'), 'store' => route('pos.store')]) }}"
     class="grid gap-5 xl:grid-cols-[1fr_380px]">

    {{-- Left: search + cart --}}
    <div class="min-w-0 space-y-5">
        <div class="card p-4">
            <div class="relative" @click.outside="results = []">
                <x-icon name="barcode" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                <label for="pos-search" class="sr-only">Search or scan medicine</label>
                <input id="pos-search" x-ref="search" type="search" x-model="query" @input="onSearchInput"
                       @keydown.enter.prevent="onSearchEnter" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.escape="results = []"
                       placeholder="Scan barcode or type medicine name, generic or SKU…" autocomplete="off"
                       class="w-full rounded-xl border-slate-200 bg-slate-50 py-3.5 pl-12 pr-24 text-base focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                       role="combobox" :aria-expanded="results.length > 0" aria-controls="pos-results">
                <span class="pointer-events-none absolute right-4 top-1/2 hidden -translate-y-1/2 rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-400 sm:block">F2</span>
                <div x-cloak x-show="searching" class="absolute right-14 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></div>

                <ul id="pos-results" x-cloak x-show="results.length" role="listbox"
                    class="absolute z-30 mt-2 max-h-[60vh] w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-pop">
                    <template x-for="(r, i) in results" :key="r.id">
                        <li role="option" :aria-selected="i === highlighted">
                            <button type="button" @click="add(r)" @mouseenter="highlighted = i"
                                    class="flex w-full items-center gap-4 rounded-lg px-3 py-2.5 text-left"
                                    :class="i === highlighted ? 'bg-brand-50' : ''">
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2">
                                        <span class="truncate font-medium text-ink" x-text="r.name"></span>
                                        <span x-show="r.rx" class="rounded bg-violet-50 px-1.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-600/15">Rx</span>
                                    </span>
                                    <span class="block truncate text-xs text-slate-500" x-text="[r.generic_name, r.sku].filter(Boolean).join(' · ')"></span>
                                </span>
                                <span class="text-right">
                                    <span class="tabular block font-semibold" x-text="fmt(Math.round(r.price * 100))"></span>
                                    <span class="block text-xs" :class="r.stock > 0 ? 'text-slate-500' : 'text-rose-600'" x-text="r.stock > 0 ? r.stock + ' sellable' : 'No sellable stock'"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <h2 class="text-sm font-semibold">Cart <span class="ml-1 text-slate-400" x-text="cart.length ? '(' + itemCount + ' units)' : ''"></span></h2>
                <button type="button" x-show="cart.length" @click="if (confirm('Clear the cart?')) clearCart()" class="btn-ghost btn-sm text-rose-600">Clear</button>
            </div>

            <div x-show="!cart.length" class="px-6 py-16 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-slate-100 text-slate-400"><x-icon name="cart" class="size-7" /></span>
                <p class="mt-4 text-sm font-medium text-ink">Cart is empty</p>
                <p class="mt-1 text-sm text-slate-500">Scan a barcode or search to add medicines. Expired batches are never sold.</p>
            </div>

            <ul x-cloak x-show="cart.length" class="divide-y divide-slate-100">
                <template x-for="(line, i) in cart" :key="line.id">
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-3 px-5 py-3.5 sm:flex-nowrap sm:gap-x-4">
                        <div class="min-w-0 basis-full sm:flex-1 sm:basis-auto">
                            <p class="truncate font-medium text-ink" x-text="line.name"></p>
                            <p class="text-xs text-slate-500">
                                <span x-text="fmt(Math.round(line.price * 100)) + ' / ' + line.unit"></span>
                                <span x-show="line.rx" class="ml-1 font-medium text-violet-700">· Prescription required</span>
                                <span x-show="expiresSoon(line)" class="ml-1 font-medium text-amber-700" x-text="'· Batch expires ' + line.next_expiry"></span>
                            </p>
                        </div>
                        <div class="flex items-center rounded-lg border border-slate-200">
                            <button type="button" class="px-2.5 py-1.5 text-slate-500 hover:text-ink" @click="line.qty > 1 ? line.qty-- : remove(i)" aria-label="Decrease quantity"><x-icon name="minus" class="size-4" /></button>
                            <input type="number" min="1" :max="line.stock" x-model.number="line.qty" @change="clampQty(line)" class="tabular w-14 border-0 p-0 text-center text-sm focus:ring-0" aria-label="Quantity">
                            <button type="button" class="px-2.5 py-1.5 text-slate-500 hover:text-ink" @click="line.qty < line.stock ? line.qty++ : toast('Only ' + line.stock + ' available.', 'error')" aria-label="Increase quantity"><x-icon name="plus" class="size-4" /></button>
                        </div>
                        <template x-if="config.canDiscount">
                            <label class="flex items-center gap-1 text-xs text-slate-500">
                                <span class="sr-only">Discount percent</span>
                                <input type="number" min="0" :max="config.maxDiscount" step="0.5" x-model.number="line.discount" @change="clampDiscount(line)"
                                       class="field-input tabular w-16 px-2 py-1.5 text-right text-xs"> %
                            </label>
                        </template>
                                                <p class="tabular ml-auto w-20 text-right font-semibold text-ink sm:w-24" x-text="fmt(lineCents(line).total)"></p>
                        <button type="button" @click="remove(i)" class="btn-ghost btn-sm text-slate-400 hover:text-rose-600" aria-label="Remove"><x-icon name="x" class="size-4" /></button>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Right: customer + payment --}}
    <aside class="space-y-5 xl:sticky xl:top-20 xl:self-start">
        <section class="card p-5">
            <h2 class="text-sm font-semibold">Customer</h2>
            <template x-if="customer">
                <div class="mt-3 flex items-center gap-3 rounded-lg bg-brand-50 p-3">
                    <span class="grid size-9 place-items-center rounded-full bg-brand-600 text-sm font-semibold text-white" x-text="customer.name.charAt(0)"></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium" x-text="customer.name"></p>
                        <p class="text-xs text-slate-600"><span x-text="customer.phone || ''"></span>
                            <span x-show="Number(customer.balance) > 0" class="font-medium text-amber-700" x-text="'· owes ' + fmt(Math.round(customer.balance * 100))"></span></p>
                    </div>
                    <button type="button" @click="customer = null" class="btn-ghost btn-sm" aria-label="Remove customer"><x-icon name="x" class="size-4" /></button>
                </div>
            </template>
            <div x-show="!customer" class="relative mt-3" @click.outside="customerOpen = false">
                <label for="pos-customer" class="sr-only">Find customer</label>
                <input id="pos-customer" type="search" x-model="customerQuery" @input.debounce.250ms="searchCustomers" placeholder="Walk-in · search name or phone"
                       class="field-input" autocomplete="off">
                <ul x-cloak x-show="customerOpen && customerResults.length" class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-1 shadow-pop">
                    <template x-for="c in customerResults" :key="c.id">
                        <li><button type="button" @click="pickCustomer(c)" class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50">
                            <span class="font-medium" x-text="c.name"></span> <span class="text-xs text-slate-500" x-text="c.phone || ''"></span>
                        </button></li>
                    </template>
                </ul>
                <button type="button" x-show="config.canCreateCustomer" @click="showNewCustomer = !showNewCustomer; newCustomer.name = customerQuery" class="mt-2 text-sm link">+ New customer</button>
                <div x-cloak x-show="showNewCustomer" class="mt-3 space-y-2 rounded-lg border border-slate-200 p-3">
                    <input type="text" x-model="newCustomer.name" placeholder="Full name" class="field-input" aria-label="New customer name">
                    <input type="tel" x-model="newCustomer.phone" placeholder="Phone" class="field-input" aria-label="New customer phone">
                    <button type="button" @click="createCustomer" class="btn-secondary w-full" :disabled="!newCustomer.name">Save customer</button>
                </div>
            </div>
        </section>

        <section class="card p-5">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular" x-text="fmt(totals.subtotal)"></dd></div>
                <div class="flex justify-between" x-show="totals.discount > 0"><dt class="text-slate-500">Discount</dt><dd class="tabular text-emerald-700" x-text="'−' + fmt(totals.discount)"></dd></div>
                <div class="flex justify-between" x-show="config.taxEnabled"><dt class="text-slate-500">{{ settings('tax_label', 'Tax') }}</dt><dd class="tabular" x-text="fmt(totals.tax)"></dd></div>
                <div class="flex items-baseline justify-between border-t border-slate-100 pt-3"><dt class="font-semibold">Total</dt><dd class="tabular text-2xl font-semibold tracking-tight" x-text="fmt(totals.total)"></dd></div>
            </dl>

            <fieldset class="mt-5">
                <legend class="field-label">Payment method</legend>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="m in methods" :key="m.id">
                        <label class="flex cursor-pointer items-center justify-center rounded-lg border px-3 py-2 text-sm font-medium transition"
                               :class="paymentMethodId === m.id ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'">
                            <input type="radio" class="sr-only" :value="m.id" x-model.number="paymentMethodId"><span x-text="m.name"></span>
                        </label>
                    </template>
                </div>
            </fieldset>

            <div class="mt-4">
                <label for="pos-received" class="field-label">Amount received</label>
                <input id="pos-received" type="number" step="0.01" min="0" x-model="amountReceived" :placeholder="(totals.total / 100).toFixed(2)" class="field-input tabular text-lg">
                <p class="mt-2 flex justify-between text-sm" x-show="changeCents > 0"><span class="text-slate-500">Change</span><span class="tabular font-semibold text-emerald-700" x-text="fmt(changeCents)"></span></p>
                <p class="mt-2 flex justify-between text-sm" x-show="dueCents > 0"><span class="text-slate-500">Balance due (credit)</span><span class="tabular font-semibold text-amber-700" x-text="fmt(dueCents)"></span></p>
            </div>

            <label for="pos-notes" class="sr-only">Notes</label>
            <input id="pos-notes" type="text" x-model="notes" maxlength="1000" placeholder="Note (optional, e.g. prescription ref.)" class="field-input mt-3 text-xs">

            <button type="button" @click="checkout" :disabled="!canSubmit" class="btn-primary mt-5 w-full py-3 text-base">
                <span x-show="!submitting">Complete sale</span>
                <span x-cloak x-show="submitting">Processing…</span>
            </button>
            <p class="mt-2 text-center text-xs text-slate-500" x-text="blockReason || 'Ctrl/⌘ + Enter to complete'"></p>
        </section>
    </aside>

    {{-- Success dialog --}}
    <div x-cloak x-show="completed" class="fixed inset-0 z-[70] grid place-items-center bg-slate-900/40 p-4 backdrop-blur-[2px]" role="dialog" aria-modal="true" aria-labelledby="sale-done">
        <div x-show="completed" x-trap.noscroll="completed" class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-pop" @keydown.enter.prevent="newSale">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-100 text-emerald-600"><x-icon name="check" class="size-7" stroke="2.5" /></span>
            <h2 id="sale-done" class="mt-4 text-lg font-semibold">Sale completed</h2>
            <p class="mt-1 font-mono text-sm text-slate-500" x-text="completed?.invoice_number"></p>
            <p class="tabular mt-4 text-3xl font-semibold" x-text="completed ? fmt(Math.round(completed.total * 100)) : ''"></p>
            <p class="mt-1 text-sm text-emerald-700" x-show="completed?.change > 0" x-text="'Change due: ' + fmt(completed?.change || 0)"></p>
            <p class="mt-1 text-sm text-amber-700" x-show="Number(completed?.due) > 0" x-text="'Added to customer balance: ' + fmt(Math.round((completed?.due || 0) * 100))"></p>
            <div class="mt-6 grid grid-cols-2 gap-2">
                <a :href="completed?.print_url" target="_blank" rel="noopener" class="btn-secondary"><x-icon name="printer" class="size-4" /> Print</a>
                <a :href="completed?.invoice_url" class="btn-secondary"><x-icon name="document" class="size-4" /> Invoice</a>
            </div>
            <button type="button" @click="newSale" class="btn-primary mt-2 w-full">New sale</button>
        </div>
    </div>
</div>
</x-layouts.app>
