<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coupon_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return ['coupon_code.regex' => 'This coupon code is invalid.'];
    }
}
