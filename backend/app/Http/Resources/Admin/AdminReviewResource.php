<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;

/** @mixin \App\Models\Review */
class AdminReviewResource extends ReviewResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'moderator' => $this->whenLoaded('moderator', fn () => $this->moderator?->name),
            'moderated_at' => $this->moderated_at?->toIso8601String(),
        ]);
    }
}
