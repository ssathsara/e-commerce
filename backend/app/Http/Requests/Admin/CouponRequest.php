<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');
        $isPercentage = $this->input('type') === CouponType::Percentage->value;

        return [
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => $isPercentage
                ? ['required', 'integer', 'between:1,100']
                : ['required', 'numeric', 'min:0.01', 'max:9999999', 'decimal:0,2'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999', 'decimal:0,2'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => array_filter([
                'nullable',
                'date',
                $this->filled('starts_at') ? 'after:starts_at' : null,
                // New coupons must expire in the future; existing ones may keep a past expiry.
                $coupon === null ? 'after:now' : null,
            ]),
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Codes may contain only letters, numbers, dashes and underscores.',
            'value.between' => 'Percentage discounts must be between 1 and 100.',
        ];
    }

    /** Converts money fields to cents; percentage values stay whole percents. */
    public function toModelAttributes(): array
    {
        $data = $this->validated();
        $isPercentage = $data['type'] === CouponType::Percentage->value;

        return [
            ...$data,
            'value' => $isPercentage ? (int) $data['value'] : Money::toCents($data['value']),
            'min_order_amount' => isset($data['min_order_amount']) ? Money::toCents($data['min_order_amount']) : null,
            'max_discount_amount' => isset($data['max_discount_amount']) ? Money::toCents($data['max_discount_amount']) : null,
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
