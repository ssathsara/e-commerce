# Authentication

## Approach: Sanctum SPA (cookie) authentication

The React app and the API are served from **one origin** (Vite proxy in development,
Nginx in Docker/production). Sanctum's stateful middleware authenticates requests
from `SANCTUM_STATEFUL_DOMAINS` using Laravel's session:

- The session cookie is **HttpOnly** — JavaScript can never read it, so an XSS bug cannot
  steal a bearer token (none exists in the browser).
- State-changing requests are protected by **CSRF**: `GET /sanctum/csrf-cookie` sets an
  `XSRF-TOKEN` cookie; Axios copies it into the `X-XSRF-TOKEN` header automatically
  (`withXSRFToken: true`).
- The session id is regenerated on login (prevents session fixation) and invalidated on logout.

Non-browser clients (mobile apps, integrations) are out of scope for this version;
Sanctum personal access tokens could be added without changing authorization.

## Flow

```
SPA start      GET /api/v1/auth/me            → 200 {user, roles, permissions} or 401 (guest)
Login          GET /sanctum/csrf-cookie       → sets XSRF-TOKEN
               POST /api/v1/auth/login        → 200 {user, roles, permissions}; session cookie
                 (X-Cart-Token header present → guest cart merged into the account cart)
Requests       cookie + X-XSRF-TOKEN          → authenticated
CSRF expiry    419 → Axios fetches a new CSRF cookie and retries once
Session loss   401 → AuthContext switches to guest; protected routes redirect to /login?redirect=…
Logout         POST /api/v1/auth/logout       → session invalidated, CSRF token rotated
```

## Endpoints

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/register` | Creates a **Customer**, signs in, sends verification + welcome emails. Throttled |
| POST | `/auth/login` | `email`, `password`, `remember`. Deactivated accounts receive 403. Throttled (5/min per email+IP, 20/min per IP) |
| POST | `/auth/logout` | Authenticated |
| GET | `/auth/me` | User + role names + effective permission names |
| POST | `/auth/forgot-password` | Always returns the same message (no account enumeration). Throttled |
| POST | `/auth/reset-password` | `token`, `email`, `password`, `password_confirmation`. Signs out other sessions |
| POST | `/auth/email/verification-notification` | Resend verification (6/min) |
| POST | `/auth/email/verify/{id}/{hash}?expires=…&signature=…` | Signed link; must be the signed-in user |

## Passwords

- Hashed with bcrypt via Laravel's `hashed` cast — never stored or logged in plain text,
  never serialised (`$hidden`).
- Policy (`Password::defaults()`): at least 8 characters, letters, mixed case and a number.
- Changing a password requires the current password and signs out all other sessions.
- Reset tokens are stored hashed by Laravel, expire after 60 minutes, and are throttled.

## Password reset and verification links

Emails link to the React app, never to Laravel views:

- Reset: `FRONTEND_URL/reset-password?token=…&email=…` → `POST /auth/reset-password`.
- Verify: `FRONTEND_URL/verify-email?path=<signed relative API path>` → the SPA posts that
  path while signed in; the API validates the signature (`signed:relative`) and that the
  link belongs to the current user.

## Account deactivation

`users.is_active = false` blocks login, and the `active` middleware rejects any existing
session on its next request (and logs it out). Deactivating a user through the admin also
deletes their stored sessions immediately.

## Rate limits

| Limiter | Limit |
|---|---|
| `api` | 300/min per signed-in user, 120/min per IP for guests |
| `auth` | login/register/forgot/reset: 5/min per email+IP and 20/min per IP |
| `checkout` | order placement and payment retries: 10/min |
| `uploads` | image uploads: 30/min |

Exceeding a limit returns `429` with the standard error envelope and `Retry-After`.
