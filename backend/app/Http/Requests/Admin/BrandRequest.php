<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sent as multipart/form-data (optional logo). Updates use POST with _method=PUT.
 */
class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->ignore($brand?->id)],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('brands', 'slug')->ignore($brand?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => ['nullable', ...ImageRules::rules(64)],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    public function toModelAttributes(): array
    {
        return [
            ...$this->safe()->except(['logo']),
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
