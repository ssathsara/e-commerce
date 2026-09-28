<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the "audit" log channel that consistently attaches the actor and IP.
 * Callers must never pass passwords, tokens or payment secrets in $context.
 */
final class AuditLog
{
    public static function info(string $event, array $context = []): void
    {
        Log::channel('audit')->info($event, self::withActor($context));
    }

    public static function warning(string $event, array $context = []): void
    {
        Log::channel('audit')->warning($event, self::withActor($context));
    }

    private static function withActor(array $context): array
    {
        return array_merge([
            'actor_id' => Auth::id(),
            'ip' => request()?->ip(),
        ], $context);
    }
}
