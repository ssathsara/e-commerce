# API reference

Base URL: **`/api/v1`**. All requests and responses are JSON (uploads use
`multipart/form-data`). Authentication is the Sanctum session cookie; state-changing
requests need the `X-XSRF-TOKEN` header (see [authentication.md](authentication.md)).

Legend — **Auth**: `–` public, `user` signed in, `guest/user` works for both.
**Permission**: Spatie permission required (Super Admin always passes). Every
`/admin/*` route additionally requires `access admin panel`.

## Response format

```jsonc
// Success
{ "success": true, "message": "Product created successfully.", "data": { ... } }

// Paginated
{ "success": true, "message": null, "data": [ ... ],
  "meta": { "current_page": 1, "last_page": 10, "per_page": 20, "total": 200, "from": 1, "to": 20 } }

// Error
{ "success": false, "message": "Validation failed.", "errors": { "email": ["The email field is required."] } }
```

| Status | Meaning |
|---|---|
| 200 / 201 | OK / created |
| 401 | Not authenticated |
| 402 | Payment declined (payment retry endpoint) |
| 403 | Authenticated but not allowed (missing permission, not the owner, deactivated, escalation) |
| 404 | Resource not found (or not visible to the caller) |
| 409 | Conflicts with current state: insufficient stock, invalid status transition, resource in use |
| 419 | CSRF token expired |
| 422 | Validation or business-rule failure (field errors in `errors`) |
| 429 | Rate limit exceeded (`Retry-After` header) |
| 500 | Unexpected error (no internals exposed) |

Money values in responses are **major units** (e.g. `19.99`); requests from admins also
use major units. The server converts to cents. Clients never send prices for purchases.

---

## Authentication

| Method | Endpoint | Auth | Body |
|---|---|---|---|
| POST | `/auth/register` | – | `name`, `email`, `password`, `password_confirmation` |
| POST | `/auth/login` | – | `email`, `password`, `remember?` |
| POST | `/auth/logout` | user | – |
| GET | `/auth/me` | user | – |
| POST | `/auth/forgot-password` | – | `email` |
| POST | `/auth/reset-password` | – | `token`, `email`, `password`, `password_confirmation` |
| POST | `/auth/email/verification-notification` | user | – |
| POST | `/auth/email/verify/{id}/{hash}` | user | signed query string |

`/auth/me`, `/auth/login` and `/auth/register` return:

```json
{ "user": { "id": 2, "name": "Alex Admin", "email": "admin@example.com", "phone": null,
            "email_verified": true, "is_active": true, "created_at": "..." },
  "roles": ["Admin"],
  "permissions": ["access admin panel", "view products", "create products", "..."] }
```

## Catalogue (public)

| Method | Endpoint | Notes |
|---|---|---|
| GET | `/home` | Featured categories/products, new arrivals, best sellers, on sale |
| GET | `/categories` | Active category tree (cached) |
| GET | `/categories/{slug}` | Category with parent and children |
| GET | `/brands`, `/brands/{slug}` | Active brands |
| GET | `/products` | Listing (below) |
| GET | `/products/{slug}` | Detail: images, active variants, brand, category, SEO |
| GET | `/products/{slug}/related` | Up to 8 products |
| GET | `/products/{slug}/reviews` | Approved reviews, paginated; `meta.rating_average`, `meta.distribution` |

`GET /products` query parameters (all optional, validated):

| Param | Type | Description |
|---|---|---|
| `search` | string ≤100 | Name, SKU prefix, short description |
| `category` | slug | Includes all subcategories |
| `brand` | slug or comma list | e.g. `brand=nimbus,sonora` |
| `min_price`, `max_price` | number | Major units |
| `in_stock` | boolean | Purchasable items only |
| `rating` | 1–5 | Minimum average rating |
| `featured` | boolean | |
| `sort` | `newest` \| `price_asc` \| `price_desc` \| `popular` \| `rating` | Default `newest` |
| `page`, `per_page` | int | `per_page` ≤ 60 |

## Cart (guest or user)

Guests send `X-Cart-Token: <uuid>` (returned as `data.token` on the first add). Every
endpoint returns the full server-priced cart. Optional `?shipping_method=standard|express`
selects which rate the totals include.

| Method | Endpoint | Body |
|---|---|---|
| GET | `/cart` | – |
| POST | `/cart/items` | `product_id`, `variant_id?`, `quantity` (1–99) |
| PUT | `/cart/items/{item}` | `quantity` |
| DELETE | `/cart/items/{item}` | – |
| DELETE | `/cart` | – (clear) |
| POST | `/cart/coupon` | `coupon_code` |
| DELETE | `/cart/coupon` | – |
| GET | `/checkout/options` | Shipping methods (costs for the current cart), payment methods, tax rate |

Cart response:

```json
{ "id": 12, "token": null, "item_count": 3, "has_issues": false, "currency": "USD", "tax_rate": 8,
  "items": [ { "id": 40, "quantity": 2, "unit_price": 59, "line_total": 118, "available_stock": 25, "issue": null,
               "product": { "id": 14, "name": "Classic Oxford Shirt", "slug": "classic-oxford-shirt", "sku": "MEN-0014", "image": { "url": "..." } },
               "variant": { "id": 9, "name": "Medium", "sku": "MEN-0014-M", "options": { "Size": "M" } } } ],
  "coupon": { "code": "SUMMER25", "is_applied": true, "error": null },
  "shipping_method": "express",
  "totals": { "subtotal": 118, "discount": 29.5, "shipping": 14.99, "tax": 7.08, "total": 110.57 } }
```

Errors: `422` invalid product/variant/quantity or coupon (`errors.coupon_code`), `409`
quantity exceeds stock, `404` item not in the caller's cart.

## Orders (customer)

| Method | Endpoint | Auth | Notes |
|---|---|---|---|
| GET | `/orders` | user | Own orders; `?status=` |
| POST | `/orders` | user | Place order (below). Throttled 10/min |
| GET | `/orders/{order}` | user | Owner or `view orders` |
| POST | `/orders/{order}/cancel` | user | Owner; only while pending/confirmed |
| POST | `/orders/{order}/payments` | user | Retry card payment: `payment_token`, `idempotency_key` |

`POST /orders` body — **no prices or totals**:

```json
{ "idempotency_key": "3f1c2b8e-2d4a-4c1e-9b1a-6f1f0a9b7c55",
  "shipping_address_id": 3, "billing_same_as_shipping": true, "billing_address_id": null,
  "shipping_method": "standard", "payment_method": "card", "payment_token": "tok_visa",
  "notes": "Leave at the door" }
```

- `201` new order (`payment_error` is set if the card was declined — the order exists
  and can be paid via the retry endpoint).
- `200` the same key was already used: the original order is returned (no duplicate).
- `409` a cart line became unavailable or stock is insufficient (`errors.cart`).
- `422` validation (address not owned, unknown method, missing token) or coupon no longer valid.

## Account (user)

| Method | Endpoint | Body |
|---|---|---|
| GET | `/account/profile` | – (includes `stats`) |
| PUT | `/account/profile` | `name`, `email`, `phone?` (email change → re-verification) |
| PUT | `/account/password` | `current_password`, `password`, `password_confirmation` |
| GET/POST | `/account/addresses` | address fields |
| GET/PUT/DELETE | `/account/addresses/{id}` | owner only |
| GET | `/account/wishlist`, `/account/wishlist/ids` | – |
| POST | `/account/wishlist` | `product_id` (idempotent) |
| DELETE | `/account/wishlist/{productId}` | – |
| POST | `/account/wishlist/{productId}/move-to-cart` | `variant_id?` |
| GET | `/account/reviews` | own reviews |
| GET | `/products/{slug}/review-eligibility` | `{can_review, reason, review}` |
| POST | `/products/{slug}/reviews` | `rating` 1–5, `title?`, `body` (10–5000). 403 not eligible, 409 duplicate |
| PUT/DELETE | `/reviews/{review}` | author only; editing resubmits for approval |

Address fields: `first_name`, `last_name`, `line1`, `city`, `postal_code`, `country`
(ISO-2) required; `label`, `company`, `phone`, `line2`, `state`, `is_default_shipping`,
`is_default_billing` optional.

## Admin

| Method | Endpoint | Permission |
|---|---|---|
| GET | `/admin/dashboard` | access admin panel (revenue fields only with `view sales reports`) |
| GET | `/admin/notifications` | access admin panel (counts filtered by permission) |
| GET | `/admin/options` | access admin panel (enum options, category/brand pickers) |
| GET | `/admin/products` | view products — `search`, `status`, `category_id`, `brand_id`, `stock=in\|low\|out`, `sort` |
| POST | `/admin/products` | create products |
| GET | `/admin/products/{id}` | view products |
| PUT | `/admin/products/{id}` | edit products |
| PATCH | `/admin/products/{id}/status` | edit products — `status`: draft\|active\|inactive |
| DELETE | `/admin/products/{id}` | delete products (soft delete) |
| POST | `/admin/products/{id}/images` | edit products — multipart `images[]` (jpeg/png/webp, ≤5 MB, ≥200px) |
| PATCH | `/admin/products/{id}/images/{image}` | edit products — `alt_text?`, `is_primary?` |
| POST | `/admin/products/{id}/images/{image}/replace` | edit products — multipart `image` |
| PUT | `/admin/products/{id}/images/reorder` | edit products — `image_ids[]` |
| DELETE | `/admin/products/{id}/images/{image}` | edit products |
| GET/POST | `/admin/categories` | view / create categories (multipart; `image`) |
| GET/PUT/DELETE | `/admin/categories/{id}` | view / edit / delete categories (409 if in use) |
| GET/POST | `/admin/brands` | view / create brands (multipart; `logo`) |
| GET/PUT/DELETE | `/admin/brands/{id}` | view / edit / delete brands (409 if in use) |
| GET | `/admin/orders` | view orders — `search`, `status`, `payment_status`, `payment_method`, `customer_id`, `from`, `to`, `sort` |
| GET | `/admin/orders/{id}` | view orders (includes `allowed_transitions`) |
| PATCH | `/admin/orders/{id}` | edit orders — `admin_notes`, `tracking_number` |
| PATCH | `/admin/orders/{id}/status` | update orders (or cancel orders for `cancelled`) — `status`, `tracking_number?`; 409 on invalid transition |
| PATCH | `/admin/orders/{id}/payment-status` | edit orders — cash on delivery only |
| GET/POST | `/admin/customers` | view / create customers |
| GET/PUT/DELETE | `/admin/customers/{id}` | view / edit / delete customers |
| PATCH | `/admin/customers/{id}/status` | edit customers — `is_active` |
| GET | `/admin/inventory` | view inventory — `search`, `stock`, `sort`; `meta.low_stock_count`, `meta.out_of_stock_count` |
| GET | `/admin/inventory/movements` | view inventory — `product_id`, `type`, `from`, `to` |
| POST | `/admin/inventory/adjustments` | manage inventory — `product_id`, `variant_id?`, `type` (purchase\|adjustment\|return), `quantity` (signed), `note` (required for adjustments) |
| GET | `/admin/reviews` | view reviews — `search`, `status`, `rating`, `product_id` |
| GET | `/admin/reviews/{id}` | view reviews |
| PATCH | `/admin/reviews/{id}/status` | approve reviews — `status`: approved\|rejected |
| DELETE | `/admin/reviews/{id}` | delete reviews |
| GET/POST | `/admin/coupons` | view / create coupons |
| GET/PUT/DELETE | `/admin/coupons/{id}` | view / edit / delete coupons |
| PATCH | `/admin/coupons/{id}/toggle` | edit coupons |
| GET | `/admin/coupons/{id}/usages` | view coupons |
| GET/POST | `/admin/users` | view / create users (+ assign roles) |
| GET | `/admin/users/assignable-roles` | assign roles |
| GET/PUT/DELETE | `/admin/users/{id}` | view / edit / delete users |
| PATCH | `/admin/users/{id}/status` | edit users |
| GET/POST | `/admin/roles` | view / create roles (+ assign permissions for `permissions[]`) |
| GET/PUT/DELETE | `/admin/roles/{id}` | view / edit / delete roles |
| GET | `/admin/permissions` | view permissions (grouped, with granting roles) |
| GET | `/admin/reports/sales` | view sales reports — `from`, `to`, `grain=day\|month` |
| GET | `/admin/reports/orders` | view reports — `from`, `to`, `search`, `status`, `payment_status` |
| GET | `/admin/reports/products` | view reports — `from`, `to`, `search`, `sort=units\|revenue` |
| GET | `/admin/reports/customers` | view reports — `from`, `to`, `search` |
| GET | `/admin/reports/inventory` | view inventory reports — `search`, `stock`, plus movement summary for `from`/`to` |

### Example: create a product

`POST /api/v1/admin/products` (permission `create products`)

```json
{ "name": "Aurora Desk Lamp", "sku": "LAMP-001", "category_id": 4, "brand_id": 2,
  "short_description": "A warm, dimmable lamp.", "description": "…",
  "price": 49.99, "compare_at_price": 59.99, "stock_quantity": 12, "low_stock_threshold": 3,
  "status": "active", "is_featured": true, "seo_title": "Aurora Desk Lamp",
  "variants": [ { "name": "Black", "sku": "LAMP-BLK", "options": { "Color": "Black" }, "price": null, "stock_quantity": 4, "is_active": true } ] }
```

`201` → `{ "success": true, "message": "Product created successfully.", "data": { "id": 44, "slug": "aurora-desk-lamp", … } }`

Validation errors (`422`), e.g.:

```json
{ "success": false, "message": "Validation failed.",
  "errors": { "sku": ["The sku has already been taken."], "variants.0.sku": ["The variant SKU has already been taken."] } }
```

Opening stock (`stock_quantity`) is accepted only on create; later stock changes go
through `/admin/inventory/adjustments` so every change is recorded.
