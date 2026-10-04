<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('returns.create');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'refund_method_id' => ['nullable', 'integer', TenantRule::exists('payment_methods')->where('is_active', true)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'items.*.restock' => ['nullable', 'boolean'],
        ];
    }
}
