# Architecture

## Overview

```
┌────────────────────────── Browser ───────────────────────────┐
│ React SPA (storefront + /admin)                               │
│  pages → features/components → contexts/hooks → src/api/*     │
└───────────────┬───────────────────────────────────────────────┘
                │ same origin: /api/v1, /sanctum, /storage
                ▼  (session cookie + X-XSRF-TOKEN)
┌──────────────────────── Laravel API ─────────────────────────┐
│ Middleware: ForceJson → Sanctum stateful → auth:sanctum →     │
│             active → throttle → permission:<name>             │
│ Controllers (thin) → Form Requests (validation)               │
│                    → Policies (ownership)                     │
│                    → Services (business rules, transactions)  │
│                    → API Resources (response shape)           │
│ Events (after commit) → Listeners → queued Notifications      │
└───────────────┬───────────────────────────────────────────────┘
                ▼
        Eloquent models → MySQL
```

## Backend

### Layers and responsibilities

| Layer | Location | Responsibility |
|---|---|---|
| Routes | `routes/api.php` | URL → controller, **plus the permission each admin route requires** (readable authorization map) |
| Middleware | `app/Http/Middleware` | JSON-only API, security headers, blocking deactivated accounts |
| Form Requests | `app/Http/Requests` | Validation; money is converted to cents in `toModelAttributes()` |
| Controllers | `app/Http/Controllers/Api` | Receive request → authorize → call a service → return a resource. No business logic |
| Services | `app/Services` | All business rules and transactions (see below) |
| Policies | `app/Policies` | Ownership rules (orders, addresses, reviews) |
| Resources | `app/Http/Resources` | Response shape; cents → major units; never exposes secrets |
| Enums | `app/Enums` | Product/order/payment/review statuses, movement types, coupon types, role names; the order state machine |
| Models | `app/Models` | Relationships, casts, query scopes. Sensitive attributes (stock, statuses, ratings) are not mass-assignable |
| Events / Listeners / Notifications | `app/Events`, `app/Listeners`, `app/Notifications` | Emails dispatched only after the DB transaction commits |

The project uses **Services** for business logic instead of a separate `Actions/`
directory: each service groups a cohesive set of operations on one aggregate.
Repositories were intentionally not introduced — Eloquent query scopes and small query
classes (`ProductQuery`, `ReportService`) cover the need without an extra abstraction layer.

### Key services

| Service | Responsibility |
|---|---|
| `Catalog\ProductService` | Create/update products and variants; opening stock via inventory |
| `Catalog\ProductQuery` | Server-side search, filtering, sorting and pagination (public and admin) |
| `Catalog\ProductImageService`, `Media\ImageStorage` | Validated uploads, primary image invariant, file cleanup |
| `Catalog\CategoryService`, `BrandService` | CRUD with deletion protection, cycle prevention, cached category tree |
| `Inventory\InventoryService` | **Only** code path that changes stock: row locks, no negative stock, movement ledger, auto in/out-of-stock status |
| `Cart\CartService` | Guest/user carts, validation against stock, coupon application, guest-cart merge, server-side pricing |
| `Checkout\TotalsCalculator` | Single source of truth for subtotal/discount/shipping/tax/total |
| `Checkout\CheckoutService` | Idempotent, transactional, row-locked order placement |
| `Orders\OrderService` | Status transitions (state machine), cancellation (restock, coupon release, refund) |
| `Payments\PaymentService` | Charges via `PaymentGatewayInterface`, retries, refunds, manual COD updates |
| `Coupons\CouponService` | Coupon validation (active, dates, minimum, global and per-customer limits) |
| `Reviews\ReviewService` | Eligibility, moderation, rating aggregates |
| `Admin\UserManagementService`, `RoleService`, `PrivilegeGuard` | User/role administration with privilege-escalation protection |
| `Reports\DashboardService`, `ReportService` | Aggregate SQL for dashboard and reports |

### Checkout: how integrity is guaranteed

1. If an order with the same `(user, idempotency_key)` exists, return it (HTTP 200).
2. Start a transaction. Lock the cart and its items, then lock every product and variant
   row **in ascending id order** (consistent lock order prevents deadlocks).
3. Price the cart from the locked catalogue rows; refuse if any line has an issue (409).
4. Re-validate the coupon with the coupon row locked (usage limits cannot be exceeded).
5. Create the order with address and product **snapshots**, deduct stock via
   `InventoryService` (which refuses negative stock), record coupon usage, clear the cart.
6. Commit. A unique index on `(user_id, idempotency_key)` makes a concurrent duplicate
   fail; that request then returns the original order.
7. For card payments, charge **outside** the transaction (payment row reserved under an
   order lock, gateway called with an idempotency key, result recorded under the lock).

### Payment gateway extension point

```php
interface PaymentGatewayInterface {
    public function name(): string;
    public function charge(ChargeRequest $request): PaymentResult;
    public function refund(Payment $payment): PaymentResult;
}
```

To add Stripe: implement the interface (e.g. `Gateways\StripePaymentGateway` using the
payment-method id created by Stripe.js as the `paymentToken`), register it in the
`match` in `AppServiceProvider::register()`, and set `PAYMENT_GATEWAY=stripe`. Checkout,
orders and refunds need no changes.

### Error handling

`bootstrap/app.php` renders every API exception in one envelope
(`{"success": false, "message": "...", "errors": {...}}`) with the right status code
(401/403/404/409/419/422/429/500). Business-rule violations are thrown as
`BusinessRuleException` (409 or 422) by services. Internal details are never returned
when `APP_DEBUG=false`; unexpected exceptions are logged.

### Logging

- Default channel: application errors.
- `audit` channel (`storage/logs/audit-*.log`, 90 days): logins and failed logins,
  registrations, password changes/resets, order placement and status changes, payments
  and refunds, manual inventory changes, catalogue changes, user/role changes and blocked
  privilege-escalation attempts. Passwords, tokens and payment data are never logged.

## Frontend

```
src/
├── api/          Axios instance (CSRF, cart token, error normalisation) + one module per API area
├── components/   Reusable UI: common/ (Button, Modal, DataTable, …), forms/, layout/, product/, admin/
├── features/     Feature-specific building blocks: auth, products, cart, checkout, orders, reviews, account, admin
├── contexts/     AuthContext (user/roles/permissions + can()), CartContext, WishlistContext, ToastContext
├── hooks/        useAsync, useForm (server validation errors), useQueryParams (URL state), useResourceList, …
├── layouts/      PublicLayout, CustomerLayout, AdminLayout
├── pages/        public/, customer/, admin/ — route-level screens (lazy-loaded)
├── routes/       Route table and guards (RequireAuth, GuestOnly, RequirePermission, Can)
├── constants/    Permission names, status tones, admin navigation
├── types/        JSDoc shapes of API payloads
└── utils/        Formatting, errors, storage
```

Principles:

- **The server is authoritative.** The client never computes prices or totals; every cart
  mutation returns the full server-priced cart.
- **URL is state** for listings (filters, sort, page), so pages are shareable and the back
  button works.
- **Every screen has loading (skeletons), empty, error (with retry) and success states.**
- **Permission helpers are UI-only** (`can('create products')`, `<Can>`, `RequirePermission`).
- **Accessibility:** semantic landmarks, labelled controls, `aria-invalid`/`aria-describedby`
  on errors, focus-trapped modals with Escape, skip link, visible focus rings, reduced-motion support.
- Pages are code-split; charts (Recharts) load only in the admin portal.
