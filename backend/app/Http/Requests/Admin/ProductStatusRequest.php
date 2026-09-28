<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // "out_of_stock" is derived from stock levels and cannot be set manually.
            'status' => ['required', Rule::enum(ProductStatus::class)->except([ProductStatus::OutOfStock])],
        ];
    }
}
