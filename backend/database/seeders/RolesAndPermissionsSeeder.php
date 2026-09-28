<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Support\Permissions as P;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds every permission and the four built-in roles. Idempotent: safe to re-run
 * in any environment (it only adds missing permissions and re-syncs built-in roles).
 * Everything is referenced by NAME; IDs are never hard-coded.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Super Admin gets everything through Gate::before (see AppServiceProvider),
        // so the role itself needs no explicit permissions.
        Role::findOrCreate(RoleName::SuperAdmin->value, 'web');

        // Admin: full store management, but role/permission administration is limited
        // to viewing and assigning (cannot create/delete roles).
        Role::findOrCreate(RoleName::Admin->value, 'web')->syncPermissions(
            array_values(array_diff(P::all(), [P::CREATE_ROLES, P::DELETE_ROLES, P::EDIT_ROLES, P::ASSIGN_PERMISSIONS]))
        );

        // Staff: day-to-day operations (catalogue upkeep, fulfilment, inventory, moderation).
        Role::findOrCreate(RoleName::Staff->value, 'web')->syncPermissions([
            P::ACCESS_ADMIN,
            P::VIEW_PRODUCTS, P::EDIT_PRODUCTS,
            P::VIEW_CATEGORIES, P::VIEW_BRANDS,
            P::VIEW_ORDERS, P::UPDATE_ORDERS,
            P::VIEW_CUSTOMERS,
            P::VIEW_INVENTORY, P::MANAGE_INVENTORY,
            P::VIEW_REVIEWS, P::APPROVE_REVIEWS,
            P::VIEW_COUPONS,
        ]);

        // Customers act on their own data only; that is enforced by policies, not permissions.
        Role::findOrCreate(RoleName::Customer->value, 'web')->syncPermissions([]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
