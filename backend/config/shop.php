<?php

/*
|--------------------------------------------------------------------------
| Shop business configuration
|--------------------------------------------------------------------------
|
| All monetary values in this file are expressed in MAJOR units (e.g. 5.99)
| for readability. They are converted to minor units (cents) by App\Support\Money
| before being used in calculations. The database stores every amount in cents.
|
*/

return [

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    'currency' => env('SHOP_CURRENCY', 'USD'),

    // Percentage (e.g. 8 = 8%) applied to the discounted subtotal.
    'tax_rate' => (float) env('SHOP_TAX_RATE', 0),

    'shipping' => [
        'free_shipping_threshold' => (float) env('SHOP_FREE_SHIPPING_THRESHOLD', 100),

        // Keyed by method code. The code is what the client sends at checkout.
        'methods' => [
            'standard' => [
                'name' => 'Standard Shipping',
                'description' => '3-5 business days',
                'rate' => (float) env('SHOP_STANDARD_SHIPPING', 5.99),
                'eligible_for_free_shipping' => true,
            ],
            'express' => [
                'name' => 'Express Shipping',
                'description' => '1-2 business days',
                'rate' => (float) env('SHOP_EXPRESS_SHIPPING', 14.99),
                'eligible_for_free_shipping' => false,
            ],
        ],
    ],

    'cart' => [
        // Upper bound for a single line quantity, independent of stock.
        'max_line_quantity' => 99,
    ],

    'reviews' => [
        'require_purchase' => (bool) env('SHOP_REVIEWS_REQUIRE_PURCHASE', true),
        'require_approval' => (bool) env('SHOP_REVIEWS_REQUIRE_APPROVAL', true),
    ],

    'payments' => [
        // Gateway used for the "card" payment method.
        'gateway' => env('PAYMENT_GATEWAY', 'mock'),
    ],

    'uploads' => [
        'max_image_kb' => 5120,
        'image_mimes' => ['jpeg', 'png', 'webp'],
    ],

    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
];
