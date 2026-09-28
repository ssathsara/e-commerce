<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
