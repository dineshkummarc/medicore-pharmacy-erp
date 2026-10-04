@php $editing = $medicine->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$medicine->name : 'Add medicine'">
    <x-page-header :title="$editing ? 'Edit medicine' : 'Add medicine'" :back="$editing ? route('medicines.show', $medicine) : route('medicines.index')"
                   description="Stock is added through purchases or opening stock, never by editing a number here." />

    <form method="POST" action="{{ $editing ? route('medicines.update', $medicine) : route('medicines.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-base font-semibold">Product details</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-input name="name" label="Medicine / brand name" :value="$medicine->name" required maxlength="180" class="sm:col-span-2" />
                    <x-input name="generic_name" label="Generic name" :value="$medicine->generic_name" maxlength="180" />
                    <x-input name="strength" label="Strength" :value="$medicine->strength" placeholder="e.g. 500mg" maxlength="60" />
                    <x-select name="category_id" label="Category" :options="$categories->pluck('name', 'id')" :value="$medicine->category_id" placeholder="— None —" />
                    <x-select name="manufacturer_id" label="Brand / manufacturer" :options="$manufacturers->pluck('name', 'id')" :value="$medicine->manufacturer_id" placeholder="— None —" />
                    <x-select name="unit" label="Selling unit" :options="collect($units)->mapWithKeys(fn ($u) => [$u => ucfirst($u)])" :value="$medicine->unit" required />
                    <x-select name="supplier_id" label="Preferred supplier" :options="$suppliers->pluck('name', 'id')" :value="$medicine->supplier_id" placeholder="— None —" />
                    <x-textarea name="description" label="Description / notes" :value="$medicine->description" rows="3" class="sm:col-span-2" maxlength="2000" />
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold">Pricing</h2>
                <p class="mt-1 text-sm text-slate-500">Default prices. Each purchase updates them to the latest values.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <x-input name="purchase_price" type="number" step="0.01" min="0" label="Purchase price" :prefix="settings('currency_symbol')" :value="$medicine->purchase_price" required />
                    <x-input name="selling_price" type="number" step="0.01" min="0" label="Selling price" :prefix="settings('currency_symbol')" :value="$medicine->selling_price" required />
                    <x-input name="tax_rate" type="number" step="0.01" min="0" max="100" :label="settings('tax_label', 'Tax').' rate (%)'" :value="$medicine->tax_rate" required
                             :hint="settings()['tax_enabled'] ? null : 'Tax is currently disabled in settings.'" />
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="card p-6">
                <h2 class="text-base font-semibold">Identification</h2>
                <div class="mt-5 space-y-4">
                    <x-input name="sku" label="SKU / product code" :value="$medicine->sku" required maxlength="60" class="font-mono" hint="Letters, numbers, dot, dash, underscore." />
                    <x-input name="barcode" label="Barcode" :value="$medicine->barcode" maxlength="64" hint="Scan the pack barcode into this field." />
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold">Stock control</h2>
                <div class="mt-5 space-y-5">
                    <x-input name="min_stock_level" type="number" min="0" label="Reorder level" :value="$medicine->min_stock_level" required
                             :hint="'Alert when stock falls to this level. 0 uses the store default ('.settings('low_stock_threshold').').'" />
                    <x-toggle name="requires_prescription" label="Prescription required" description="Flagged at the POS for the pharmacist." :checked="$medicine->requires_prescription" />
                    <x-toggle name="is_active" label="Available for sale" description="Inactive medicines are hidden from the POS." :checked="$medicine->is_active" />
                </div>
            </section>

            <div class="flex gap-2">
                <a href="{{ $editing ? route('medicines.show', $medicine) : route('medicines.index') }}" class="btn-secondary flex-1">Cancel</a>
                <button type="submit" class="btn-primary flex-1">{{ $editing ? 'Save changes' : 'Create medicine' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>
