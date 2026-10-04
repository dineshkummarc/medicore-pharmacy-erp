<?php

namespace App\Http\Requests;

use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('staff.manage');
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'role_id' => ['required', 'integer', TenantRule::exists('roles')],
            'password' => $staff
                ? ['nullable', 'confirmed', Password::defaults()]
                : ['required', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
