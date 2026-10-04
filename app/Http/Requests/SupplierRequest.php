<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('suppliers.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->has('is_active') ? $this->boolean('is_active') : true]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
