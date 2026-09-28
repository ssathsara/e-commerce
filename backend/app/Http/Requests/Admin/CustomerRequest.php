<?php

namespace App\Http\Requests\Admin;

use Illuminate\Support\Arr;

/**
 * Customer accounts always get the Customer role, so roles are not accepted here.
 */
class CustomerRequest extends UserRequest
{
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['roles', 'roles.*']);
    }
}
