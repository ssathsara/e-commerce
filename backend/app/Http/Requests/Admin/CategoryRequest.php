<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sent as multipart/form-data (optional image). Updates use POST with _method=PUT.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ['nullable', ...ImageRules::rules(100)],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['parent_id.not_in' => 'A category cannot be its own parent.'];
    }

    public function toModelAttributes(): array
    {
        return [
            ...$this->safe()->except(['image']),
            'is_active' => $this->boolean('is_active', true),
            'is_featured' => $this->boolean('is_featured'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ];
    }
}
