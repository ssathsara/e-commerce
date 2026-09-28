<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/**
 * Shared upload rules. "image" + "mimes" inspect the file CONTENTS (not the client
 * extension), which blocks executables/SVG with scripts disguised as images.
 */
final class ImageRules
{
    public static function rules(int $minDimension = 100): array
    {
        return [
            'file',
            'image',
            'mimes:'.implode(',', config('shop.uploads.image_mimes')),
            'max:'.config('shop.uploads.max_image_kb'),
            Rule::dimensions()->minWidth($minDimension)->minHeight($minDimension)->maxWidth(6000)->maxHeight(6000),
        ];
    }
}
