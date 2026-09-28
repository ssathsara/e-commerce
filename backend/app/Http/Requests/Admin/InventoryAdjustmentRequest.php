<?php

namespace App\Http\Requests\Admin;

use App\Enums\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Manual stock movement. Sale/Cancellation movements come only from the order workflow.
 * Purchases and returns add stock; adjustments may add or remove.
 */
class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', (int) $this->input('product_id'))],
            'type' => ['required', Rule::in(array_map(fn (InventoryMovementType $t) => $t->value, InventoryMovementType::manual()))],
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = $this->input('type');
                $quantity = (int) $this->input('quantity');

                if (in_array($type, [InventoryMovementType::Purchase->value, InventoryMovementType::Return->value], true) && $quantity < 0) {
                    $validator->errors()->add('quantity', 'Purchases and returns must increase stock.');
                }

                if ($type === InventoryMovementType::Adjustment->value && blank($this->input('note'))) {
                    $validator->errors()->add('note', 'Please give a reason for manual adjustments.');
                }
            },
        ];
    }
}
