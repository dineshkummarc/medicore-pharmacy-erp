@php $editing = $customer->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit customer' : 'Add customer'">
    <x-page-header :title="$editing ? 'Edit '.$customer->name : 'Add customer'" :back="$editing ? route('customers.show', $customer) : route('customers.index')" />
    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="card max-w-3xl p-6">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="name" label="Full name" :value="$customer->name" required maxlength="150" class="sm:col-span-2" />
            <x-input name="phone" type="tel" label="Phone" :value="$customer->phone" maxlength="30" />
            <x-input name="email" type="email" label="Email" :value="$customer->email" />
            <x-input name="date_of_birth" type="date" label="Date of birth" :value="$customer->date_of_birth" :max="today()->subDay()->toDateString()" hint="Optional, helps with dosage checks." />
            <x-input name="address" label="Address" :value="$customer->address" maxlength="500" />
            <x-textarea name="notes" label="Notes" :value="$customer->notes" class="sm:col-span-2" hint="E.g. allergies or regular prescriptions. Visible to staff only." maxlength="2000" />
            <x-toggle name="is_active" label="Active" :checked="$customer->is_active ?? true" class="sm:col-span-2" />
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Create customer' }}</button>
        </div>
    </form>
</x-layouts.app>
