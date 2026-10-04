<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', TenantRule::exists('customers', true)],
            'payment_method_id' => ['required', 'integer', TenantRule::exists('payment_methods')->where('is_active', true)],
            'amount_received' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.medicine_id' => ['required', 'integer', TenantRule::exists('medicines', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'The cart is empty.'];
    }
}
