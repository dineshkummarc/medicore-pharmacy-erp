<x-modal name="collect" :title="$title" :open="$errors->has('amount') || $errors->has('payment_method_id')">
    <form method="POST" action="{{ $action }}" class="space-y-4">
        @csrf
        <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">Outstanding: <strong class="tabular">{{ money($balance) }}</strong><span class="block text-xs text-slate-500">{{ $hint }}</span></div>
        <x-input name="amount" type="number" step="0.01" min="0.01" :max="$balance" label="Amount" :prefix="settings('currency_symbol')" :value="$balance" required id="pay-amount" />
        <x-select name="payment_method_id" label="Method" :options="$methods->pluck('name', 'id')" required id="pay-method" />
        <x-input name="reference" label="Reference" maxlength="120" placeholder="Cheque / transaction no." id="pay-ref" />
        <x-input name="note" label="Note" maxlength="500" id="pay-note" />
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secondary" @click="$dispatch('close-modal', 'collect')">Cancel</button>
            <button class="btn-primary">Record payment</button>
        </div>
    </form>
</x-modal>
