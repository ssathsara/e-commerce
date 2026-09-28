<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'first_name',
        'last_name',
        'company',
        'phone',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default_shipping',
        'is_default_billing',
    ];

    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Immutable copy stored on orders so later address edits never change order history.
     *
     * @return array<string, string|null>
     */
    public function toSnapshot(): array
    {
        return $this->only([
            'first_name', 'last_name', 'company', 'phone', 'line1', 'line2',
            'city', 'state', 'postal_code', 'country',
        ]);
    }
}
