<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.manage');
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', TenantRule::exists('expense_categories', true)],
            'payment_method_id' => ['required', 'integer', TenantRule::exists('payment_methods')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:120'],
            // Only inert document/image types; SVG/HTML are refused to prevent stored XSS.
            'receipt' => ['nullable', 'file', 'max:4096', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'],
        ];
    }
}
