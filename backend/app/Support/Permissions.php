<?php

namespace App\Support;

/**
 * Catalogue of every permission the application checks.
 *
 * Spatie Permission is the single source of truth at runtime; this class only
 * provides the canonical names (to avoid typos) and their grouping for the
 * seeder and the admin UI. Checks are always done by permission NAME.
 */
final class Permissions
{
    public const ACCESS_ADMIN = 'access admin panel';

    public const VIEW_PRODUCTS = 'view products';

    public const CREATE_PRODUCTS = 'create products';

    public const EDIT_PRODUCTS = 'edit products';

    public const DELETE_PRODUCTS = 'delete products';

    public const VIEW_CATEGORIES = 'view categories';

    public const CREATE_CATEGORIES = 'create categories';

    public const EDIT_CATEGORIES = 'edit categories';

    public const DELETE_CATEGORIES = 'delete categories';

    public const VIEW_BRANDS = 'view brands';

    public const CREATE_BRANDS = 'create brands';

    public const EDIT_BRANDS = 'edit brands';

    public const DELETE_BRANDS = 'delete brands';

    public const VIEW_ORDERS = 'view orders';

    public const CREATE_ORDERS = 'create orders';

    public const EDIT_ORDERS = 'edit orders';

    public const UPDATE_ORDERS = 'update orders';

    public const CANCEL_ORDERS = 'cancel orders';

    public const VIEW_CUSTOMERS = 'view customers';

    public const CREATE_CUSTOMERS = 'create customers';

    public const EDIT_CUSTOMERS = 'edit customers';

    public const DELETE_CUSTOMERS = 'delete customers';

    public const VIEW_INVENTORY = 'view inventory';

    public const MANAGE_INVENTORY = 'manage inventory';

    public const VIEW_REVIEWS = 'view reviews';

    public const APPROVE_REVIEWS = 'approve reviews';

    public const EDIT_REVIEWS = 'edit reviews';

    public const DELETE_REVIEWS = 'delete reviews';

    public const VIEW_COUPONS = 'view coupons';

    public const CREATE_COUPONS = 'create coupons';

    public const EDIT_COUPONS = 'edit coupons';

    public const DELETE_COUPONS = 'delete coupons';

    public const VIEW_REPORTS = 'view reports';

    public const VIEW_SALES_REPORTS = 'view sales reports';

    public const VIEW_INVENTORY_REPORTS = 'view inventory reports';

    public const VIEW_USERS = 'view users';

    public const CREATE_USERS = 'create users';

    public const EDIT_USERS = 'edit users';

    public const DELETE_USERS = 'delete users';

    public const VIEW_ROLES = 'view roles';

    public const CREATE_ROLES = 'create roles';

    public const EDIT_ROLES = 'edit roles';

    public const DELETE_ROLES = 'delete roles';

    public const ASSIGN_ROLES = 'assign roles';

    public const VIEW_PERMISSIONS = 'view permissions';

    public const ASSIGN_PERMISSIONS = 'assign permissions';

    /**
     * Permissions grouped by module, for seeding and display.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'Admin' => [self::ACCESS_ADMIN],
            'Products' => [self::VIEW_PRODUCTS, self::CREATE_PRODUCTS, self::EDIT_PRODUCTS, self::DELETE_PRODUCTS],
            'Categories' => [self::VIEW_CATEGORIES, self::CREATE_CATEGORIES, self::EDIT_CATEGORIES, self::DELETE_CATEGORIES],
            'Brands' => [self::VIEW_BRANDS, self::CREATE_BRANDS, self::EDIT_BRANDS, self::DELETE_BRANDS],
            'Orders' => [self::VIEW_ORDERS, self::CREATE_ORDERS, self::EDIT_ORDERS, self::UPDATE_ORDERS, self::CANCEL_ORDERS],
            'Customers' => [self::VIEW_CUSTOMERS, self::CREATE_CUSTOMERS, self::EDIT_CUSTOMERS, self::DELETE_CUSTOMERS],
            'Inventory' => [self::VIEW_INVENTORY, self::MANAGE_INVENTORY],
            'Reviews' => [self::VIEW_REVIEWS, self::APPROVE_REVIEWS, self::EDIT_REVIEWS, self::DELETE_REVIEWS],
            'Coupons' => [self::VIEW_COUPONS, self::CREATE_COUPONS, self::EDIT_COUPONS, self::DELETE_COUPONS],
            'Reports' => [self::VIEW_REPORTS, self::VIEW_SALES_REPORTS, self::VIEW_INVENTORY_REPORTS],
            'Users' => [self::VIEW_USERS, self::CREATE_USERS, self::EDIT_USERS, self::DELETE_USERS],
            'Roles' => [self::VIEW_ROLES, self::CREATE_ROLES, self::EDIT_ROLES, self::DELETE_ROLES, self::ASSIGN_ROLES],
            'Permissions' => [self::VIEW_PERMISSIONS, self::ASSIGN_PERMISSIONS],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::grouped()));
    }

    public static function groupOf(string $permission): string
    {
        foreach (self::grouped() as $group => $permissions) {
            if (in_array($permission, $permissions, true)) {
                return $group;
            }
        }

        return 'Other';
    }
}
