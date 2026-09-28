<?php

namespace App\Http\Requests\Checkout;

use App\Enums\PaymentMethod;
use App\Services\Checkout\PlaceOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout input. Contains no prices or totals: everything monetary is computed server-side.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownAddress = Rule::exists('addresses', 'id')->where('user_id', $this->user()->id);

        return [
            // Generated once per checkout attempt by the client and reused on retries.
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'shipping_address_id' => ['required', 'integer', $ownAddress],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
            'billing_address_id' => ['exclude_if:billing_same_as_shipping,true', 'required_unless:billing_same_as_shipping,true', 'nullable', 'integer', $ownAddress],
            'shipping_method' => ['required', 'string', Rule::in(array_keys(config('shop.shipping.methods')))],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_token' => ['required_if:payment_method,'.PaymentMethod::Card->value, 'nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address_id.exists' => 'Please choose one of your saved addresses.',
            'billing_address_id.exists' => 'Please choose one of your saved addresses.',
            'payment_token.required_if' => 'Please provide your card details.',
        ];
    }

    public function toData(): PlaceOrderData
    {
        $shippingId = (int) $this->validated('shipping_address_id');

        return new PlaceOrderData(
            idempotencyKey: $this->validated('idempotency_key'),
            shippingAddressId: $shippingId,
            billingAddressId: $this->boolean('billing_same_as_shipping') ? $shippingId : (int) $this->validated('billing_address_id'),
            shippingMethod: $this->validated('shipping_method'),
            paymentMethod: PaymentMethod::from($this->validated('payment_method')),
            paymentToken: $this->validated('payment_token'),
            notes: $this->validated('notes'),
        );
    }
}
