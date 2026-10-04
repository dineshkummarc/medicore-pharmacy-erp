<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('medicine') ? 'medicines.update' : 'medicines.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->filled('sku') ? strtoupper(trim((string) $this->input('sku'))) : null,
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
            'requires_prescription' => $this->boolean('requires_prescription'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('medicine')?->id;

        return [
            'name' => ['required', 'string', 'max:180'],
            'generic_name' => ['nullable', 'string', 'max:180'],
            'strength' => ['nullable', 'string', 'max:60'],
            'sku' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9._\-]+$/', TenantRule::unique('medicines', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/', TenantRule::unique('medicines', 'barcode')->ignore($id)],
            'category_id' => ['nullable', 'integer', TenantRule::exists('categories', true)],
            'manufacturer_id' => ['nullable', 'integer', TenantRule::exists('manufacturers', true)],
            'supplier_id' => ['nullable', 'integer', TenantRule::exists('suppliers', true)],
            'unit' => ['required', 'string', Rule::in(config('pharmacy.units'))],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999', 'gte:purchase_price'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_stock_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'requires_prescription' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'selling_price.gte' => 'Selling price should not be below the purchase price.',
            'sku.regex' => 'SKU may contain only letters, numbers, dots, dashes and underscores.',
        ];
    }
}
