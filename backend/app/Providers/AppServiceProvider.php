<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Payments\Gateways\MockPaymentGateway;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Resolve the configured card payment gateway. New providers (Stripe, PayPal, ...)
        // implement PaymentGatewayInterface and are added to this match.
        $this->app->bind(PaymentGatewayInterface::class, function () {
            return match (config('shop.payments.gateway')) {
                'mock' => new MockPaymentGateway,
                default => throw new InvalidArgumentException('Unsupported payment gateway ['.config('shop.payments.gateway').'].'),
            };
        });
    }

    public function boot(): void
    {
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureModels();

        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers());
    }

    /**
     * Spatie's recommended Super Admin approach: grant every ability through Gate::before.
     * Returning null (not false) lets normal permission/policy checks run for everyone else.
     *
     * Invariants that must hold even for Super Admins (e.g. "cannot delete your own account")
     * are therefore enforced in services, not in policies.
     */
    private function configureAuthorization(): void
    {
        Gate::before(function (User $user) {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }

    private function configureRateLimiting(): void
    {
        // General API budget. A single admin screen issues several requests, so
        // signed-in users get a higher budget than anonymous clients (keyed by IP).
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user('sanctum');

            return $user
                ? Limit::perMinute(300)->by('user:'.$user->id)
                : Limit::perMinute(120)->by('ip:'.$request->ip());
        });

        // Brute-force protection: per email+IP and per IP.
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function configureModels(): void
    {
        // Surface N+1 queries: fail loudly in tests, log in local development, ignore in production.
        Model::preventLazyLoading(! $this->app->isProduction());

        if ($this->app->environment('local')) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
                Log::warning('Lazy loading detected', ['model' => $model::class, 'relation' => $relation]);
            });
        }
    }
}
