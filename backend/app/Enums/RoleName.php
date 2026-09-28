<?php

namespace App\Enums;

/**
 * Names of the built-in roles. Roles are stored and checked by NAME through
 * Spatie Permission; IDs are never hard-coded.
 */
enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Staff = 'Staff';
    case Customer = 'Customer';

    /** Built-in roles cannot be deleted or renamed. */
    public static function isSystemRole(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }
}
