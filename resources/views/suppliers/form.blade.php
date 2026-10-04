@php $editing = $supplier->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit supplier' : 'Add supplier'">
    <x-page-header :title="$editing ? 'Edit '.$supplier->name : 'Add supplier'" :back="$editing ? route('suppliers.show', $supplier) : route('suppliers.index')" />
    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card max-w-3xl p-6">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="name" label="Company name" :value="$supplier->name" required maxlength="150" class="sm:col-span-2" />
            <x-input name="contact_person" label="Contact person" :value="$supplier->contact_person" maxlength="120" />
            <x-input name="phone" type="tel" label="Phone" :value="$supplier->phone" maxlength="30" />
            <x-input name="email" type="email" label="Email" :value="$supplier->email" />
            <x-input name="tax_number" label="Tax / registration no." :value="$supplier->tax_number" maxlength="60" />
            <x-input name="address" label="Address" :value="$supplier->address" maxlength="500" class="sm:col-span-2" />
            <x-textarea name="notes" label="Notes" :value="$supplier->notes" class="sm:col-span-2" maxlength="2000" />
            <x-toggle name="is_active" label="Active" :checked="$supplier->is_active ?? true" class="sm:col-span-2" />
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Create supplier' }}</button>
        </div>
    </form>
</x-layouts.app>
