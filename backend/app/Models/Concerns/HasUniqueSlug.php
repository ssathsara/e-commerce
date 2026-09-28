<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Generates an SEO-friendly, unique slug from the model's name when one is not provided.
 * Uniqueness is checked including soft-deleted rows because the DB unique index covers them.
 */
trait HasUniqueSlug
{
    public static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model) {
            if (blank($model->slug)) {
                $model->slug = static::generateUniqueSlug((string) $model->name, $model->getKey());
            } elseif ($model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model->slug, $model->getKey());
            }
        });
    }

    public static function generateUniqueSlug(string $value, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $base = Str::limit($base, 120, '');
        $slug = $base;
        $suffix = 2;

        while (static::slugExists($slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected static function slugExists(string $slug, int|string|null $ignoreId): bool
    {
        $query = static::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withTrashed();
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
