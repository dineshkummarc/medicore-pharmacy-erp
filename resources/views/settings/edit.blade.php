@php $s = $settings; @endphp
<x-layouts.app title="Settings">
    <x-page-header title="Store settings" description="Business details printed on invoices, plus tax, numbering and alert thresholds." />
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <section class="card p-6">
            <h2 class="text-base font-semibold">Business</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <x-input name="store_name" label="Store name" :value="$s['store_name']" required maxlength="120" />
                <x-input name="store_tagline" label="Tagline" :value="$s['store_tagline']" maxlength="160" />
                <x-input name="store_phone" label="Phone" :value="$s['store_phone']" maxlength="40" />
                <x-input name="store_email" type="email" label="Email" :value="$s['store_email']" />
                <x-textarea name="store_address" label="Address" :value="$s['store_address']" rows="2" maxlength="500" class="md:col-span-2" />
                <x-input name="license_number" label="Pharmacy licence no." :value="$s['license_number']" maxlength="80" />
                <x-input name="tax_number" label="Tax registration no." :value="$s['tax_number']" maxlength="80" />
                <div class="md:col-span-2">
                    <span class="field-label">Logo</span>
                    <div class="flex items-center gap-4">
                        @if ($s['store_logo'])
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($s['store_logo']) }}" alt="Current logo" class="size-14 rounded-lg border border-slate-200 object-contain">
                        @endif
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" aria-label="Upload logo" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                        @if ($s['store_logo'])
                            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Remove</label>
                        @endif
                    </div>
                    @error('logo')<p class="field-error">{{ $message }}</p>@else<p class="field-hint">PNG, JPG or WebP, up to 1 MB.</p>@enderror
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card p-6">
                <h2 class="text-base font-semibold">Currency & tax</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-input name="currency_symbol" label="Currency symbol" :value="$s['currency_symbol']" required maxlength="5" />
                    <x-input name="currency_code" label="Currency code" :value="$s['currency_code']" required maxlength="3" class="uppercase" />
                    <x-toggle name="tax_enabled" label="Charge tax on sales" :checked="(bool) $s['tax_enabled']" description="Uses each medicine's tax rate." class="sm:col-span-2" />
                    <x-input name="tax_label" label="Tax label" :value="$s['tax_label']" required maxlength="20" hint="e.g. VAT, GST, Sales tax" />
                    <x-input name="default_tax_rate" type="number" step="0.01" min="0" max="100" label="Default rate for new medicines (%)" :value="$s['default_tax_rate']" required />
                </div>
            </section>
            <section class="card p-6">
                <h2 class="text-base font-semibold">Inventory & sales rules</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-input name="low_stock_threshold" type="number" min="0" label="Default reorder level" :value="$s['low_stock_threshold']" required hint="Used when a medicine has none." />
                    <x-input name="expiry_alert_days" type="number" min="1" max="730" label="Expiry alert window (days)" :value="$s['expiry_alert_days']" required />
                    <x-input name="max_discount_percent" type="number" step="0.5" min="0" max="100" label="Max staff discount (%)" :value="$s['max_discount_percent']" required hint="Admins are not limited." />
                </div>
            </section>
        </div>

        <section class="card p-6">
            <h2 class="text-base font-semibold">Documents</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <x-input name="invoice_prefix" label="Invoice prefix" :value="$s['invoice_prefix']" required maxlength="10" class="font-mono" />
                <x-input name="purchase_prefix" label="Purchase prefix" :value="$s['purchase_prefix']" required maxlength="10" class="font-mono" />
                <x-input name="return_prefix" label="Return prefix" :value="$s['return_prefix']" required maxlength="10" class="font-mono" />
                <x-textarea name="invoice_footer" label="Invoice footer" :value="$s['invoice_footer']" rows="2" maxlength="500" class="md:col-span-3" hint="Return policy, thank-you note or legal text." />
            </div>
            <p class="mt-3 text-xs text-slate-500">Numbers continue from the existing sequence when a prefix changes, so they stay unique.</p>
        </section>

        <div class="flex justify-end"><button class="btn-primary">Save settings</button></div>
    </form>

    <section class="card mt-6 p-6">
        <h2 class="text-base font-semibold">Payment methods</h2>
        <p class="mt-1 text-sm text-slate-500">Methods offered at the POS and for expenses. Methods with transactions can only be deactivated.</p>
        <ul class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
            @foreach ($methods as $method)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <form method="POST" action="{{ route('payment-methods.update', $method) }}" class="flex flex-1 flex-wrap items-center gap-3">
                        @csrf @method('PUT')
                        <label class="sr-only" for="pm-{{ $method->id }}">Name</label>
                        <input id="pm-{{ $method->id }}" name="name" value="{{ $method->name }}" class="field-input max-w-xs py-1.5" required maxlength="60">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($method->is_active) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Active
                        </label>
                        <button class="btn-secondary btn-sm">Save</button>
                    </form>
                    <x-confirm :action="route('payment-methods.destroy', $method)" method="DELETE" :title="'Remove '.$method->name.'?'" button="Remove">
                        <button type="button" class="btn-ghost btn-sm text-rose-600" aria-label="Remove {{ $method->name }}"><x-icon name="trash" class="size-4" /></button>
                    </x-confirm>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('payment-methods.store') }}" class="mt-4 flex gap-2">
            @csrf
            <label class="sr-only" for="pm-new">New method</label>
            <input id="pm-new" name="name" placeholder="e.g. Mobile wallet" required maxlength="60" class="field-input max-w-xs">
            <button class="btn-secondary"><x-icon name="plus" class="size-4" /> Add method</button>
        </form>
    </section>
</x-layouts.app>
