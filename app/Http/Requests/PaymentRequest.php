<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Collecting from a customer or paying a supplier. Route-level `can:`
 * middleware decides which of the two the user may do.
 */
class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_method_id' => ['required', 'integer', TenantRule::exists('payment_methods')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
