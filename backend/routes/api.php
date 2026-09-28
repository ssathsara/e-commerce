<?php

use App\Http\Controllers\Api\Account\AddressController;
use App\Http\Controllers\Api\Account\OrderController;
use App\Http\Controllers\Api\Account\ProfileController;
use App\Http\Controllers\Api\Account\ReviewController;
use App\Http\Controllers\Api\Account\WishlistController;
use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutOptionsController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\ProductController;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (prefix: /api/v1, configured in bootstrap/app.php)
|--------------------------------------------------------------------------
|
| Authentication: Laravel Sanctum SPA (session cookie + CSRF).
| Authorization:  Spatie permissions on admin routes ("permission:<name>"),
|                 policies for ownership (orders, addresses, reviews).
|
*/

$can = fn (string $permission) => 'permission:'.$permission;

Route::middleware('throttle:api')->group(function () use ($can) {

    /* ------------------------------------------------------------------ */
    /* Public storefront */
    /* ------------------------------------------------------------------ */

    Route::get('home', HomeController::class);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('categories/{slug}', [CatalogController::class, 'category']);
    Route::get('brands', [CatalogController::class, 'brands']);
    Route::get('brands/{slug}', [CatalogController::class, 'brand']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{slug}', [ProductController::class, 'show']);
    Route::get('products/{slug}/related', [ProductController::class, 'related']);
    Route::get('products/{slug}/reviews', [ProductController::class, 'reviews']);
    Route::get('checkout/options', CheckoutOptionsController::class);

    /* Cart: guests (X-Cart-Token header) and signed-in customers. */
    Route::prefix('cart')->middleware('active')->controller(CartController::class)->group(function () {
        Route::get('/', 'show');
        Route::delete('/', 'clear');
        Route::post('items', 'addItem');
        Route::put('items/{item}', 'updateItem');
        Route::delete('items/{item}', 'removeItem');
        Route::post('coupon', 'applyCoupon');
        Route::delete('coupon', 'removeCoupon');
    });

    /* ------------------------------------------------------------------ */
    /* Authentication */
    /* ------------------------------------------------------------------ */

    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', 'register');
            Route::post('login', 'login');
            Route::post('forgot-password', 'forgotPassword');
            Route::post('reset-password', 'resetPassword');
        });

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('logout', 'logout');
            Route::get('me', 'me');
            Route::post('email/verification-notification', 'sendVerificationEmail')->middleware('throttle:6,1');
            Route::post('email/verify/{id}/{hash}', 'verifyEmail')
                ->middleware(['signed:relative', 'throttle:6,1'])
                ->whereNumber('id')
                ->name('verification.verify');
        });
    });

    /* ------------------------------------------------------------------ */
    /* Customer account */
    /* ------------------------------------------------------------------ */

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::prefix('account')->group(function () {
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::put('password', [ProfileController::class, 'changePassword'])->middleware('throttle:auth');

            Route::apiResource('addresses', AddressController::class);

            Route::get('wishlist', [WishlistController::class, 'index']);
            Route::get('wishlist/ids', [WishlistController::class, 'ids']);
            Route::post('wishlist', [WishlistController::class, 'store']);
            Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy'])->whereNumber('productId');
            Route::post('wishlist/{productId}/move-to-cart', [WishlistController::class, 'moveToCart'])->whereNumber('productId');

            Route::get('reviews', [ReviewController::class, 'index']);
        });

        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:checkout');
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{order}/payments', [OrderController::class, 'pay'])->middleware('throttle:checkout');

        Route::get('products/{slug}/review-eligibility', [ReviewController::class, 'eligibility']);
        Route::post('products/{slug}/reviews', [ReviewController::class, 'store']);
        Route::put('reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);
    });

    /* ------------------------------------------------------------------ */
    /* Admin portal — every route requires "access admin panel" plus the */
    /* specific permission listed. */
    /* ------------------------------------------------------------------ */

    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'active', $can(P::ACCESS_ADMIN)])
        ->group(function () use ($can) {
            Route::get('dashboard', Admin\DashboardController::class);
            Route::get('notifications', [Admin\DashboardController::class, 'notifications']);
            Route::get('options', Admin\OptionsController::class);

            // Products
            Route::get('products', [Admin\ProductController::class, 'index'])->middleware($can(P::VIEW_PRODUCTS));
            Route::post('products', [Admin\ProductController::class, 'store'])->middleware($can(P::CREATE_PRODUCTS));
            Route::get('products/{product}', [Admin\ProductController::class, 'show'])->middleware($can(P::VIEW_PRODUCTS));
            Route::put('products/{product}', [Admin\ProductController::class, 'update'])->middleware($can(P::EDIT_PRODUCTS));
            Route::patch('products/{product}/status', [Admin\ProductController::class, 'updateStatus'])->middleware($can(P::EDIT_PRODUCTS));
            Route::delete('products/{product}', [Admin\ProductController::class, 'destroy'])->middleware($can(P::DELETE_PRODUCTS));

            // Product images (scoped: an image is only reachable through its own product)
            Route::middleware([$can(P::EDIT_PRODUCTS), 'throttle:uploads'])->scopeBindings()->group(function () {
                Route::post('products/{product}/images', [Admin\ProductImageController::class, 'store']);
                Route::put('products/{product}/images/reorder', [Admin\ProductImageController::class, 'reorder']);
                Route::patch('products/{product}/images/{image}', [Admin\ProductImageController::class, 'update']);
                Route::post('products/{product}/images/{image}/replace', [Admin\ProductImageController::class, 'replace']);
                Route::delete('products/{product}/images/{image}', [Admin\ProductImageController::class, 'destroy']);
            });

            // Categories
            Route::get('categories', [Admin\CategoryController::class, 'index'])->middleware($can(P::VIEW_CATEGORIES));
            Route::post('categories', [Admin\CategoryController::class, 'store'])->middleware($can(P::CREATE_CATEGORIES));
            Route::get('categories/{category}', [Admin\CategoryController::class, 'show'])->middleware($can(P::VIEW_CATEGORIES));
            Route::put('categories/{category}', [Admin\CategoryController::class, 'update'])->middleware($can(P::EDIT_CATEGORIES));
            Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy'])->middleware($can(P::DELETE_CATEGORIES));

            // Brands
            Route::get('brands', [Admin\BrandController::class, 'index'])->middleware($can(P::VIEW_BRANDS));
            Route::post('brands', [Admin\BrandController::class, 'store'])->middleware($can(P::CREATE_BRANDS));
            Route::get('brands/{brand}', [Admin\BrandController::class, 'show'])->middleware($can(P::VIEW_BRANDS));
            Route::put('brands/{brand}', [Admin\BrandController::class, 'update'])->middleware($can(P::EDIT_BRANDS));
            Route::delete('brands/{brand}', [Admin\BrandController::class, 'destroy'])->middleware($can(P::DELETE_BRANDS));

            // Orders
            Route::get('orders', [Admin\OrderController::class, 'index'])->middleware($can(P::VIEW_ORDERS));
            Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->middleware($can(P::VIEW_ORDERS));
            Route::patch('orders/{order}', [Admin\OrderController::class, 'update'])->middleware($can(P::EDIT_ORDERS));
            // Fine-grained check (update vs cancel orders) happens in the controller.
            Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->middleware($can(P::UPDATE_ORDERS.'|'.P::CANCEL_ORDERS));
            Route::patch('orders/{order}/payment-status', [Admin\OrderController::class, 'updatePaymentStatus'])->middleware($can(P::EDIT_ORDERS));

            // Customers
            Route::get('customers', [Admin\CustomerController::class, 'index'])->middleware($can(P::VIEW_CUSTOMERS));
            Route::post('customers', [Admin\CustomerController::class, 'store'])->middleware($can(P::CREATE_CUSTOMERS));
            Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->middleware($can(P::VIEW_CUSTOMERS));
            Route::put('customers/{customer}', [Admin\CustomerController::class, 'update'])->middleware($can(P::EDIT_CUSTOMERS));
            Route::patch('customers/{customer}/status', [Admin\CustomerController::class, 'updateStatus'])->middleware($can(P::EDIT_CUSTOMERS));
            Route::delete('customers/{customer}', [Admin\CustomerController::class, 'destroy'])->middleware($can(P::DELETE_CUSTOMERS));

            // Inventory
            Route::get('inventory', [Admin\InventoryController::class, 'index'])->middleware($can(P::VIEW_INVENTORY));
            Route::get('inventory/movements', [Admin\InventoryController::class, 'movements'])->middleware($can(P::VIEW_INVENTORY));
            Route::post('inventory/adjustments', [Admin\InventoryController::class, 'adjust'])->middleware($can(P::MANAGE_INVENTORY));

            // Reviews
            Route::get('reviews', [Admin\ReviewController::class, 'index'])->middleware($can(P::VIEW_REVIEWS));
            Route::get('reviews/{review}', [Admin\ReviewController::class, 'show'])->middleware($can(P::VIEW_REVIEWS));
            Route::patch('reviews/{review}/status', [Admin\ReviewController::class, 'moderate'])->middleware($can(P::APPROVE_REVIEWS));
            Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy'])->middleware($can(P::DELETE_REVIEWS));

            // Coupons
            Route::get('coupons', [Admin\CouponController::class, 'index'])->middleware($can(P::VIEW_COUPONS));
            Route::post('coupons', [Admin\CouponController::class, 'store'])->middleware($can(P::CREATE_COUPONS));
            Route::get('coupons/{coupon}', [Admin\CouponController::class, 'show'])->middleware($can(P::VIEW_COUPONS));
            Route::get('coupons/{coupon}/usages', [Admin\CouponController::class, 'usages'])->middleware($can(P::VIEW_COUPONS));
            Route::put('coupons/{coupon}', [Admin\CouponController::class, 'update'])->middleware($can(P::EDIT_COUPONS));
            Route::patch('coupons/{coupon}/toggle', [Admin\CouponController::class, 'toggle'])->middleware($can(P::EDIT_COUPONS));
            Route::delete('coupons/{coupon}', [Admin\CouponController::class, 'destroy'])->middleware($can(P::DELETE_COUPONS));

            // Users (back-office accounts)
            Route::get('users', [Admin\UserController::class, 'index'])->middleware($can(P::VIEW_USERS));
            Route::get('users/assignable-roles', [Admin\UserController::class, 'assignableRoles'])->middleware($can(P::ASSIGN_ROLES));
            Route::post('users', [Admin\UserController::class, 'store'])->middleware($can(P::CREATE_USERS));
            Route::get('users/{user}', [Admin\UserController::class, 'show'])->middleware($can(P::VIEW_USERS));
            Route::put('users/{user}', [Admin\UserController::class, 'update'])->middleware($can(P::EDIT_USERS));
            Route::patch('users/{user}/status', [Admin\UserController::class, 'updateStatus'])->middleware($can(P::EDIT_USERS));
            Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->middleware($can(P::DELETE_USERS));

            // Roles & permissions
            Route::get('roles', [Admin\RoleController::class, 'index'])->middleware($can(P::VIEW_ROLES));
            Route::post('roles', [Admin\RoleController::class, 'store'])->middleware($can(P::CREATE_ROLES));
            Route::get('roles/{role}', [Admin\RoleController::class, 'show'])->middleware($can(P::VIEW_ROLES));
            Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->middleware($can(P::EDIT_ROLES));
            Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->middleware($can(P::DELETE_ROLES));
            Route::get('permissions', [Admin\PermissionController::class, 'index'])->middleware($can(P::VIEW_PERMISSIONS));

            // Reports
            Route::get('reports/sales', [Admin\ReportController::class, 'sales'])->middleware($can(P::VIEW_SALES_REPORTS));
            Route::get('reports/orders', [Admin\ReportController::class, 'orders'])->middleware($can(P::VIEW_REPORTS));
            Route::get('reports/products', [Admin\ReportController::class, 'products'])->middleware($can(P::VIEW_REPORTS));
            Route::get('reports/customers', [Admin\ReportController::class, 'customers'])->middleware($can(P::VIEW_REPORTS));
            Route::get('reports/inventory', [Admin\ReportController::class, 'inventory'])->middleware($can(P::VIEW_INVENTORY_REPORTS));
        });
});
