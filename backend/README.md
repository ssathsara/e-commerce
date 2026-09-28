# ShopSphere API (Laravel 12)

REST API for the ShopSphere store. See the [project README](../README.md) for setup and
[docs/](../docs) for architecture, database, API, authentication and authorization.

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan storage:link
php artisan serve          # http://localhost:8000
php artisan queue:work     # notifications
php artisan test           # test suite (ecommerce_testing database)
```
