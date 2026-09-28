# Deployment

## Topology

The SPA and the API **must share one site** (same scheme + host) so that Sanctum's
session cookie and CSRF protection work without cross-site cookies:

```
https://shop.example.com/            → React build (static files)
https://shop.example.com/api/…       → Laravel (PHP-FPM)
https://shop.example.com/sanctum/…   → Laravel
https://shop.example.com/storage/…   → uploaded images (static, never executed)
```

Background processes: a **queue worker** (notification emails) and the **scheduler**
(pruning abandoned guest carts, expired reset tokens).

## Docker (local development)

```bash
docker compose up -d --build
docker compose logs -f app          # watch first-start migrations/seeding
open http://localhost:8080
```

| Service | Purpose |
|---|---|
| `mysql` | MySQL 8.4; creates `ecommerce` and `ecommerce_testing`. Host port 33060 |
| `app` | PHP 8.3-FPM; on start: `.env`, key, wait for DB, migrate, storage link, seed demo data once |
| `queue` | `php artisan queue:work` |
| `frontend` | Vite dev server with HMR |
| `web` | Nginx on port 8080: `/api`, `/sanctum` → app; `/storage` → uploads; `/` → frontend |

Useful commands:

```bash
docker compose exec app php artisan test
docker compose exec app php artisan migrate:fresh --seed
docker compose exec frontend npm test
docker compose down -v            # stop and delete volumes (database!)
```

> This configuration was written and statically validated but not executed in the
> environment where the project was built (Docker was unavailable). Please report any
> issues; the non-Docker setup in the README was fully tested.

## Production

### 1. Build

```bash
# Frontend
cd frontend
npm ci
VITE_API_BASE_URL=/api/v1 npm run build          # → frontend/dist

# Backend
cd backend
composer install --no-dev --optimize-autoloader
```

### 2. Configure `backend/.env`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shop.example.com
FRONTEND_URL=https://shop.example.com
SANCTUM_STATEFUL_DOMAINS=shop.example.com
SESSION_DOMAIN=shop.example.com
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
QUEUE_CONNECTION=database        # or redis
CACHE_STORE=database             # or redis
MAIL_MAILER=smtp                 # credentials from your secret store
```

Generate the key once (`php artisan key:generate`) and keep it secret and stable.

### 3. Initialise

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # NOT the demo seeders
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan event:cache
```

Create the first Super Admin:

```bash
php artisan tinker
>>> $u = App\Models\User::create(['name' => 'Owner', 'email' => 'owner@shop.example.com', 'password' => 'a-strong-passphrase']);
>>> $u->forceFill(['email_verified_at' => now()])->save();
>>> $u->assignRole('Super Admin');
```

### 4. Web server

See `docker/nginx/production.conf.example`: serve `frontend/dist` with an
`index.html` fallback for client-side routes, pass `/api` and `/sanctum` to
`public/index.php`, serve `/storage` statically with PHP execution denied, enable TLS
and HSTS.

### 5. Processes

```ini
; supervisor
[program:shop-queue]
command=php /var/www/html/artisan queue:work --tries=3 --backoff=10 --max-time=3600
autorestart=true
user=www-data
```

```cron
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

After each deployment: `php artisan queue:restart`.

### 6. Operations

- Logs: `storage/logs/laravel-*.log` (errors) and `storage/logs/audit-*.log` (audit trail).
- Back up the database and `storage/app/public` (product images).
- Keep dependencies patched: `composer audit`, `npm audit`.
- Replace the mock payment gateway before accepting real payments (see architecture.md).
