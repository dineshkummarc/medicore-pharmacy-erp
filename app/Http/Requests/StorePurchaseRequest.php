<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchases.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', TenantRule::exists('suppliers', true)],
            'supplier_invoice_no' => ['nullable', 'string', 'max:80'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'payment_method_id' => [Rule::requiredIf(fn () => (float) $this->input('paid_amount') > 0), 'nullable', 'integer', TenantRule::exists('payment_methods')->where('is_active', true)],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.medicine_id' => ['required', 'integer', TenantRule::exists('medicines', true)],
            'items.*.batch_number' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9.\/\-_]+$/'],
            'items.*.expiry_date' => ['required', 'date', 'after:purchase_date'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.medicine_id' => 'medicine',
            'items.*.batch_number' => 'batch number',
            'items.*.expiry_date' => 'expiry date',
            'items.*.quantity' => 'quantity',
            'items.*.unit_cost' => 'unit cost',
            'items.*.selling_price' => 'selling price',
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.expiry_date.after' => 'Expiry date must be after the purchase date.',
            'items.required' => 'Add at least one medicine to the purchase.',
        ];
    }
}
