<x-layouts.app :title="'Edit '.$purchase->reference_no">
    <x-page-header :title="'Edit '.$purchase->reference_no" :back="route('purchases.show', $purchase)"
                   description="Only header details can change. To correct quantities or prices, cancel the purchase and enter it again." />
    <form method="POST" action="{{ route('purchases.update', $purchase) }}" class="card max-w-2xl space-y-4 p-6">
        @csrf @method('PUT')
        <x-input name="supplier_invoice_no" label="Supplier invoice no." :value="$purchase->supplier_invoice_no" maxlength="80" />
        <x-input name="purchase_date" type="date" label="Purchase date" :value="$purchase->purchase_date" :max="today()->toDateString()" required />
        <x-textarea name="notes" label="Notes" :value="$purchase->notes" maxlength="2000" />
        <div class="flex justify-end gap-2"><a href="{{ route('purchases.show', $purchase) }}" class="btn-secondary">Cancel</a><button class="btn-primary">Save</button></div>
    </form>
</x-layouts.app>
