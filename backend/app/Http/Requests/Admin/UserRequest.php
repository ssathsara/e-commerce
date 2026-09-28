<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create/update a back-office or customer account. Whether the actor may assign
 * the requested roles is decided by UserManagementService (privilege checks).
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user') ?? $this->route('customer');
        $isCreate = $user === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => [$isCreate ? 'required' : 'sometimes', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
