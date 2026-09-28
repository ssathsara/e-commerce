<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'grain' => ['nullable', 'in:day,month'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'stock' => ['nullable', 'in:in,low,out'],
            'sort' => ['nullable', 'in:units,revenue'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** Defaults to the last 30 days; ranges are capped at 3 years. */
    public function rangeStart(): Carbon
    {
        $from = $this->filled('from') ? Carbon::parse($this->input('from'))->startOfDay() : now()->subDays(29)->startOfDay();

        return $from->max($this->rangeEnd()->copy()->subYears(3));
    }

    public function rangeEnd(): Carbon
    {
        return $this->filled('to') ? Carbon::parse($this->input('to'))->endOfDay() : now()->endOfDay();
    }

    public function grain(): string
    {
        // Carbon 3 diffs are signed: measure from start to end.
        $default = $this->rangeStart()->diffInDays($this->rangeEnd()) > 92 ? 'month' : 'day';

        return $this->input('grain', $default) === 'month' ? 'month' : 'day';
    }
}
