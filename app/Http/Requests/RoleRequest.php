<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles.manage');
    }

    public function rules(): array
    {
        $permissions = collect(config('pharmacy.permissions'))->flatMap(fn ($group) => array_keys($group))->all();

        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($permissions)],
        ];
    }
}
