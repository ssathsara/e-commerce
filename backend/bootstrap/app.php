<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA authentication: session cookies + CSRF for requests from SANCTUM_STATEFUL_DOMAINS.
        $middleware->statefulApi();

        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->api(append: [SecurityHeaders::class]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request));

        // Business rule violations are expected outcomes, not errors worth reporting.
        $exceptions->dontReport([BusinessRuleException::class]);

        $error = fn (string $message, int $status, array $errors = []) => response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?: null,
        ], fn ($value) => $value !== null), $status);

        $exceptions->render(function (BusinessRuleException $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error($e->getMessage(), $e->status(), $e->errors()) : null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error('Validation failed.', 422, $e->errors()) : null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error('Unauthenticated.', 401) : null;
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error('Your session has expired. Please refresh and try again.', 419) : null;
        });

        $forbidden = function (Throwable $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error('You do not have permission to perform this action.', 403) : null;
        };
        $exceptions->render(fn (AuthorizationException $e, Request $request) => $forbidden($e, $request));
        $exceptions->render(fn (SpatieUnauthorizedException $e, Request $request) => $forbidden($e, $request));
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($isApi, $error) {
            // Policies may carry a meaningful denial message (Response::deny('...')).
            $message = $e->getPrevious() instanceof AuthorizationException && $e->getMessage() !== 'This action is unauthorized.'
                ? $e->getMessage()
                : 'You do not have permission to perform this action.';

            return $isApi($request) ? $error($message, 403) : null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error('The requested resource was not found.', 404) : null;
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi, $error) {
            return $isApi($request) ? $error('The requested resource was not found.', 404) : null;
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi, $error) {
            if (! $isApi($request)) {
                return null;
            }

            return $error('Too many requests. Please slow down and try again shortly.', 429)
                ->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi, $error) {
            if (! $isApi($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $status >= 500 ? 'Server error.' : ($e->getMessage() ?: 'Request could not be processed.');

            return $error($message, $status)->withHeaders($e->getHeaders());
        });

        // Anything else: log it (default reporting) and hide internals from the client.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi, $error) {
            if (! $isApi($request) || config('app.debug') || $e instanceof HttpResponseException) {
                return null;
            }

            return $error('An unexpected error occurred. Please try again later.', 500);
        });
    })->create();
