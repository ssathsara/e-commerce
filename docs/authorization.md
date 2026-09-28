# Authorization

**Spatie Laravel Permission is the single source of truth** for roles and permissions.
There is no custom roles table, no `is_admin` flag and no `role === 'admin'` checks.
Roles and permissions are always referenced by **name**, never by id.

## Layers

| Layer | Mechanism | Example |
|---|---|---|
| Route | Spatie `permission:` middleware on every admin route | `POST /admin/products` → `permission:create products` |
| Admin entry | `permission:access admin panel` on the whole `/admin` group | Customers (no permissions) are rejected with 403 |
| Ownership | Laravel policies | `OrderPolicy::view` — owner, or `view orders` |
| Fine-grained rules | Services | `PrivilegeGuard`, "cannot delete yourself", "last Super Admin" |
| UI | `can()`, `<Can>`, `RequirePermission` | Hides "Add product" without `create products` — **visibility only** |

The frontend never makes security decisions: every rule is enforced by the API even if
the UI is bypassed. Tests call the API directly to prove it.

## Super Admin

Spatie's recommended approach, in `AppServiceProvider`:

```php
Gate::before(fn (User $user) => $user->hasRole('Super Admin') ? true : null);
```

Returning `null` for everyone else lets normal permission checks run. Because
`Gate::before` also bypasses policies, invariants that must hold **even for Super Admins**
(cannot delete/deactivate yourself, at least one active Super Admin must remain, the
Super Admin role cannot be edited) are enforced in services rather than policies.

## Roles

| Role | Permissions |
|---|---|
| **Super Admin** | Everything (via `Gate::before`) |
| **Admin** | All permissions except `create roles`, `edit roles`, `delete roles`, `assign permissions` |
| **Staff** | `access admin panel`, view/edit products, view categories & brands, view/update orders, view customers, view/manage inventory, view/approve reviews, view coupons |
| **Customer** | None — customers act only on their own data, enforced by policies |

Built-in roles cannot be deleted or renamed; custom roles can be created by users with
`create roles` (Super Admin by default).

## Permissions

| Module | Permissions |
|---|---|
| Admin | access admin panel |
| Products | view, create, edit, delete products |
| Categories | view, create, edit, delete categories |
| Brands | view, create, edit, delete brands |
| Orders | view orders, create orders*, edit orders (notes, tracking, COD payment status), update orders (status transitions), cancel orders |
| Customers | view, create, edit (incl. activate/deactivate), delete customers |
| Inventory | view inventory, manage inventory |
| Reviews | view, approve (approve/reject), edit*, delete reviews |
| Coupons | view, create, edit (incl. toggle), delete coupons |
| Reports | view reports (orders, products, customers), view sales reports (sales + dashboard revenue), view inventory reports |
| Users | view, create, edit (incl. activate/deactivate), delete users |
| Roles | view, create, edit, delete roles; assign roles |
| Permissions | view permissions; assign permissions (to roles) |

\* Seeded as required by the specification but not used by any endpoint yet (the admin
does not create orders on behalf of customers, and moderators do not edit review text).
They are harmless placeholders for future features.

The canonical list is `App\Support\Permissions` (mirrored for the UI in
`frontend/src/constants/permissions.js`). Add a permission there and re-run
`php artisan db:seed --class=RolesAndPermissionsSeeder`.

## Ownership (policies)

| Policy | Rule |
|---|---|
| `OrderPolicy::view` | The order's owner, or anyone with `view orders` |
| `OrderPolicy::pay`, `cancel` | Only the owner (admins use the admin endpoints) |
| `AddressPolicy` | Only the owner |
| `ReviewPolicy::update`, `delete` | Only the author (moderators use admin endpoints) |
| Cart items | Resolved through the caller's own cart; another cart's item returns 404 |

## Privilege-escalation protection

Implemented in `App\Services\Admin\PrivilegeGuard` and used by user and role management:

1. The **Super Admin** role can only be assigned or managed by Super Admins.
2. A role is assignable only if **all of its permissions are held by the actor**.
3. An account can only be edited, deactivated or deleted if its permissions are a subset
   of the actor's (so staff cannot touch admins).
4. Users cannot change their own roles, deactivate or delete themselves.
5. The last active Super Admin cannot be demoted, deactivated or deleted.
6. Role permissions can only be granted **or revoked** by someone who holds them; a user
   cannot edit a role they hold; the Super Admin role cannot be edited.
7. Assigning roles additionally requires `assign roles`; changing role permissions
   requires `assign permissions`.

Blocked attempts are written to the audit log as `security.escalation_blocked`.

## Error responses

| Situation | Status |
|---|---|
| Not signed in | 401 `Unauthenticated.` |
| Signed in, missing permission / not the owner | 403 `You do not have permission to perform this action.` |
| Deactivated account | 403 |
| Escalation rule violated | 403 with the specific reason |
