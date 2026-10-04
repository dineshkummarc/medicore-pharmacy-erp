@php $editing = $expense->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit expense' : 'Record expense'">
    <x-page-header :title="$editing ? 'Edit expense' : 'Record expense'" :back="route('expenses.index')" />
    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" class="card max-w-2xl p-6">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="amount" type="number" step="0.01" min="0.01" label="Amount" :prefix="settings('currency_symbol')" :value="$expense->amount" required />
            <x-input name="expense_date" type="date" label="Date" :value="$expense->expense_date" :max="today()->toDateString()" required />
            <x-select name="expense_category_id" label="Category" :options="$categories->pluck('name', 'id')" :value="$expense->expense_category_id" placeholder="Select…" required />
            <x-select name="payment_method_id" label="Paid via" :options="$methods->pluck('name', 'id')" :value="$expense->payment_method_id" placeholder="Select…" required />
            <x-input name="description" label="Description" :value="$expense->description" required maxlength="500" class="sm:col-span-2" />
            <x-input name="reference" label="Reference / bill no." :value="$expense->reference" maxlength="120" />
            <div>
                <label for="f-receipt" class="field-label">Receipt</label>
                <input id="f-receipt" type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                @error('receipt')<p class="field-error">{{ $message }}</p>@else<p class="field-hint">PDF or image, up to 4 MB.{{ $expense->receipt_path ? ' Uploading replaces the current file.' : '' }}</p>@enderror
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2"><a href="{{ route('expenses.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">{{ $editing ? 'Save changes' : 'Record expense' }}</button></div>
    </form>
</x-layouts.app>
