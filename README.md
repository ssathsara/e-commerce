# ShopSphere — Full-Stack E-Commerce Platform

A production-style e-commerce application: a **Laravel 12 REST API** with
**Sanctum** authentication and **Spatie** role/permission authorization, and a
**React + Tailwind CSS** single-page app containing both the public storefront and a
separate, permission-aware admin portal.

Everything in the UI is backed by real API endpoints, database state, validation and
authorization — there is no mocked functionality except the payment gateway, which
is a deliberate, safe test gateway behind a provider-agnostic interface.

```
React UI  ─►  REST API (/api/v1)  ─►  Services  ─►  Policies / Spatie  ─►  Eloquent  ─►  MySQL
```

---

## Contents

- [Features](#features)
- [Technology stack](#technology-stack)
- [Requirements](#requirements)
- [Quick start (local)](#quick-start-local)
- [Quick start (Docker)](#quick-start-docker)
- [Development accounts](#development-accounts)
- [Environment variables](#environment-variables)
- [Database, migrations and seeders](#database-migrations-and-seeders)
- [Running the tests](#running-the-tests)
- [Building for production](#building-for-production)
- [Authentication and authorization](#authentication-and-authorization)
- [Key design decisions and assumptions](#key-design-decisions-and-assumptions)
- [Production security checklist](#production-security-checklist)
- [Known limitations](#known-limitations)
- [Further documentation](#further-documentation)

---

## Features

**Storefront**
- Home page with featured categories, featured products, new arrivals, best sellers and sale section
- Product listing with **server-side** search, category (including subcategories), brand, price range, availability and rating filters, sorting (newest, price, popularity, rating) and pagination — filter state lives in the URL
- Product detail with image gallery, variants (per-variant price and stock), stock-capped quantity, wishlist, ratings, approved reviews and related products
- Guest and customer carts, coupons, live server-computed totals (discount, shipping, tax)
- Four-step checkout (addresses → delivery → payment → review) with idempotent order placement
- Registration, login, logout, forgot/reset password, email verification

**Customer account** — overview, profile, password change, orders (detail, cancel, retry payment, review purchased items), addresses, wishlist (move to cart)

**Admin portal** (`/admin`, responsive, collapsible sidebar, breadcrumbs, notification bell)
- Dashboard with live KPIs, 30-day revenue and order charts, status breakdown, recent orders and customers
- Products (search/filter/sort, create/edit/view/delete, activate/deactivate, variants, image upload/reorder/primary/replace/delete)
- Categories (hierarchical, images) and brands (logos), with deletion protection
- Orders (filters, date range, validated status transitions, cash-on-delivery payment updates, tracking numbers, internal notes)
- Customers (search, detail with orders and addresses, create, activate/deactivate, delete)
- Inventory (stock levels, low/out-of-stock filters, manual adjustments, full movement history)
- Reviews moderation, coupons (limits, expiry, usage history)
- Users, roles (permission matrix editor) and permissions catalogue
- Reports: sales, orders, products, customers and inventory — date range, search, pagination

## Technology stack

| Layer | Technology |
|---|---|
| API | Laravel 12, PHP 8.2+, Laravel Sanctum 4 (SPA cookie auth), Spatie Laravel Permission 6 |
| Database | MySQL 8 / MariaDB 10.4+ |
| Frontend | React 19, React Router 7, Axios, Tailwind CSS 4, Recharts, Vite 8 |
| Tests | PHPUnit 11 (feature + unit), Vitest 4 + Testing Library |
| Tooling | Docker Compose (PHP-FPM, MySQL, Node, Nginx), Laravel Pint, oxlint |

## Requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `bcmath`, `intl`
- Composer 2
- MySQL 8+ (or MariaDB 10.4+)
- Node.js 20+ and npm

## Quick start (local)

```bash
# 1. Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
#   create the databases (adjust credentials in .env if needed)
#   CREATE DATABASE ecommerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#   CREATE DATABASE ecommerce_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate --seed
php artisan storage:link
php artisan serve            # http://localhost:8000
php artisan queue:work       # separate terminal: sends queued notification emails

# 2. Frontend
cd ../frontend
npm install
cp .env.example .env
npm run dev                  # http://localhost:5173
```

Open **http://localhost:5173**. The Vite dev server proxies `/api`, `/sanctum` and
`/storage` to Laravel, so the SPA and API share one origin (required for Sanctum's
cookie-based authentication).

> Emails use the `log` mailer in development — password-reset and verification links
> appear in `backend/storage/logs/laravel-*.log`.

## Quick start (Docker)

```bash
docker compose up -d --build
# first start: installs dependencies, migrates and seeds demo data
open http://localhost:8080
```

Services: `mysql`, `app` (PHP-FPM), `queue` (queue worker), `frontend` (Vite dev
server) and `web` (Nginx serving everything on one origin). See
[docs/deployment.md](docs/deployment.md).

> The Docker configuration has been written and statically validated (compose file
> parsed, entrypoint syntax-checked) but was **not executed** in the environment this
> project was built in (Docker was unavailable). The local setup above was fully run and tested.

## Development accounts

> **For local development only.** Created by `UserSeeder`; never seed these in production.

| Role | Email | Password |
|---|---|---|
| Super Admin | `superadmin@example.com` | `Password123!` |
| Admin | `admin@example.com` | `Password123!` |
| Staff | `staff@example.com` | `Password123!` |
| Customer | `customer@example.com` | `Password123!` |

Seeded coupons: `WELCOME10` (10%, once per customer), `SAVE20` ($20 off $150+),
`SUMMER25` (25% up to $75, min $50), `FLASH50` (limited), `EXPIRED15` (expired), `VIP30` (inactive).

Test cards for the mock gateway: `Visa •••• 4242` (succeeds), `Mastercard •••• 4444`
(succeeds), `Visa •••• 0002` (declined), `Visa •••• 9995` (insufficient funds).

## Environment variables

**Backend** (`backend/.env.example` documents every variable):

| Variable | Purpose |
|---|---|
| `APP_URL`, `FRONTEND_URL` | API base URL; SPA URL used in email links |
| `DB_*` | MySQL connection |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts (with port) allowed to use cookie auth, e.g. `localhost:5173` |
| `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | Session cookie settings |
| `CORS_ALLOWED_ORIGINS` | Only needed if the SPA is served from a different origin |
| `MAIL_*` | SMTP settings (never commit credentials) |
| `QUEUE_CONNECTION` | `database` by default; run a queue worker |
| `SHOP_CURRENCY`, `SHOP_TAX_RATE` | Currency code; tax % applied to the discounted subtotal |
| `SHOP_FREE_SHIPPING_THRESHOLD`, `SHOP_STANDARD_SHIPPING`, `SHOP_EXPRESS_SHIPPING` | Shipping rules |
| `SHOP_REVIEWS_REQUIRE_PURCHASE`, `SHOP_REVIEWS_REQUIRE_APPROVAL` | Review business rules |
| `PAYMENT_GATEWAY` | `mock` (the only bundled gateway) |

**Frontend** (`frontend/.env.example`): `VITE_API_BASE_URL` (default `/api/v1`),
`VITE_API_PROXY_TARGET` (dev proxy target), `VITE_APP_NAME`.

`.env` files are git-ignored. No secrets are committed.

## Database, migrations and seeders

```bash
php artisan migrate                        # schema
php artisan db:seed                        # full demo dataset
php artisan migrate:fresh --seed           # rebuild from scratch
php artisan db:seed --class=RolesAndPermissionsSeeder   # production: roles & permissions only
```

The demo seed creates 4 role accounts + 30 customers, 6 top-level categories with
subcategories, 11 brands, 43 products (several with variants) with generated product
images, coupons, ~60 orders placed **through the real checkout service** (so stock,
inventory movements, payments and coupon usage are consistent) spread over 60 days,
and verified-purchase reviews. See [docs/database.md](docs/database.md).

## Running the tests

```bash
cd backend && php artisan test      # 156 PHPUnit tests (uses the ecommerce_testing database)
cd frontend && npm test             # 37 Vitest tests
cd frontend && npm run lint         # oxlint
```

The backend suite covers every scenario in the specification — for example: guests
get 401 and customers 403 on admin APIs; staff cannot perform unpermitted actions;
Super Admin has unrestricted access; price manipulation is ignored; out-of-stock and
oversold items cannot be ordered; duplicate orders are prevented by idempotency keys;
stock can never go negative; customers cannot see each other's orders; invalid input
returns 422.

## Building for production

```bash
cd frontend && npm ci && npm run build     # outputs frontend/dist
cd backend && composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

Serve `frontend/dist` and route `/api`, `/sanctum` to Laravel from the **same
domain** (see `docker/nginx/production.conf.example`), run a queue worker and the
scheduler (`* * * * * php artisan schedule:run`).

## Authentication and authorization

- **Authentication:** Sanctum SPA mode — an HttpOnly session cookie plus CSRF token
  (`XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header, handled by Axios). No tokens are stored
  in JavaScript. Details: [docs/authentication.md](docs/authentication.md).
- **Authorization:** Spatie Permission is the single source of truth. Every admin route
  declares its permission (`permission:create products`), policies enforce ownership
  (orders, addresses, reviews), and **Super Admin** is granted everything via Spatie's
  recommended `Gate::before`. The React app only uses roles/permissions to decide what
  to *show*. Details: [docs/authorization.md](docs/authorization.md).

## Key design decisions and assumptions

These decisions affect behaviour; they are documented rather than hidden.

1. **Money is stored as integer cents** (no floating-point rounding); the API exposes major units.
2. **Guest carts are allowed; checkout requires an account.** Guest carts are identified by
   an opaque UUID (`X-Cart-Token`) and merged into the account cart at login/registration.
   Guest checkout was not implemented because orders, reviews and order history are
   account-bound.
3. **Prices, discounts, shipping and tax are always computed on the server** by one
   `TotalsCalculator`, used by both the cart and checkout, so the total shown is the total charged.
4. **Tax** is a flat configurable percentage of the discounted subtotal; shipping is not taxed.
   **Shipping** has two configurable methods; standard is free above a threshold.
5. **Stock is deducted when the order is placed** (inside the checkout transaction, with
   row locks), not reserved with an expiry. Cancelling returns stock; refunding a delivered
   order does **not** automatically restock — record a "return" movement in Inventory.
6. **Products with variants** track stock per variant; the product's stock is the sum of its
   active variants.
7. **Order and payment status are separate enums.** Card orders auto-confirm when paid and
   cannot be confirmed while unpaid. Card payment status is owned by the gateway; manual
   payment status changes are only allowed for cash on delivery.
8. **Reviews** require a *delivered* order containing the product and moderator approval
   (both configurable); one review per customer per product.
9. **Categories and brands in use cannot be deleted** (products must be reassigned first),
   preventing orphaned products.
10. An extra permission, **`access admin panel`**, gates entry to the admin portal so that
    customers (who hold no permissions) are cleanly separated from staff roles.
11. **Dashboard revenue** is shown only to users with `view sales reports`.
12. **Email verification** is implemented but not required to shop.
13. Revenue in dashboards/reports = orders with payment status **Paid** (refunds excluded).

## Production security checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`
- [ ] HTTPS everywhere; `SESSION_SECURE_COOKIE=true`; HSTS enabled
- [ ] `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN` set to the real domain only
- [ ] SPA and API served from the same site; `CORS_ALLOWED_ORIGINS` minimal
- [ ] Strong DB credentials; DB not publicly reachable
- [ ] Real SMTP credentials via environment/secret store, never in git
- [ ] **Do not run the demo seeders**; seed only `RolesAndPermissionsSeeder`; create the first Super Admin manually
- [ ] Queue worker and scheduler running under a process supervisor
- [ ] `storage/` and `bootstrap/cache` writable only by the app user; uploads never executable
- [ ] Log rotation for `storage/logs` (`audit.log` retained 90 days by default)
- [ ] Replace the mock payment gateway with a real provider before taking payments
- [ ] Regular `composer audit` / `npm audit` and dependency updates
- [ ] Database backups and restore tested

## Known limitations

- The payment gateway is a **mock** (no real charges). Add a provider by implementing
  `PaymentGatewayInterface` — see [docs/architecture.md](docs/architecture.md).
- Product search uses indexed `LIKE` queries; for large catalogues use MySQL full-text or
  Laravel Scout (Meilisearch/Algolia).
- Single currency and English-only UI.
- The Docker setup was not executed in the build environment (see above).

## Further documentation

| Document | Contents |
|---|---|
| [docs/architecture.md](docs/architecture.md) | Layers, request flow, services, frontend structure, extension points |
| [docs/database.md](docs/database.md) | Schema, relationships, indexes, integrity rules |
| [docs/api.md](docs/api.md) | Endpoints, permissions, request/response formats, errors |
| [docs/authentication.md](docs/authentication.md) | Sanctum SPA flow, CSRF, sessions, password reset, verification |
| [docs/authorization.md](docs/authorization.md) | Roles, permissions, policies, Super Admin, escalation rules |
| [docs/deployment.md](docs/deployment.md) | Docker, production build, web server, queue, scheduler |
