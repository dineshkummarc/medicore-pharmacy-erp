<x-layouts.app title="Opening stock">
    <x-page-header title="Add opening stock" :description="$medicine->displayName()" :back="route('medicines.show', $medicine)" />
    <form method="POST" action="{{ route('batches.store', $medicine) }}" class="card max-w-2xl p-6">
        @csrf
        <p class="rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800">
            Use this for stock you already hold when you start using the system. New deliveries should be entered as a <a href="{{ route('purchases.create') }}" class="font-semibold underline">purchase</a> so supplier balances stay correct.
        </p>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <x-input name="batch_number" label="Batch number" required maxlength="60" class="font-mono" />
            <x-input name="expiry_date" type="date" label="Expiry date" required :min="today()->addDay()->toDateString()" />
            <x-input name="quantity" type="number" min="1" :label="'Quantity ('.Str::plural($medicine->unit).')'" required />
            <x-input name="purchase_price" type="number" step="0.01" min="0" label="Cost per unit" :prefix="settings('currency_symbol')" :value="$medicine->purchase_price" required />
            <x-input name="selling_price" type="number" step="0.01" min="0" label="Selling price" :prefix="settings('currency_symbol')" :value="$medicine->selling_price" />
            <x-input name="note" label="Note" maxlength="255" placeholder="Opening stock" />
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('medicines.show', $medicine) }}" class="btn-secondary">Skip for now</a>
            <button class="btn-primary">Add stock</button>
        </div>
    </form>
</x-layouts.app>
