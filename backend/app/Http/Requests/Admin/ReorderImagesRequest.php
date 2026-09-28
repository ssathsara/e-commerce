<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'min:1', 'max:50'],
            'image_ids.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $this->route('product')->id)],
        ];
    }
}
