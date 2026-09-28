import { Navigate, Outlet, useLocation, useSearchParams } from 'react-router-dom'
import { PageSpinner } from '../components/common/Spinner'
import { ErrorState } from '../components/common/States'
import { useAuth } from '../contexts/AuthContext'
import { ForbiddenPage } from '../pages/public/ForbiddenPage'

/*
 * Route guards control what the UI shows. They are NOT a security boundary:
 * every protected API endpoint enforces authentication and permissions itself.
 */

export function RequireAuth({ children }) {
  const { isLoading, isAuthenticated, hasError, refresh } = useAuth()
  const location = useLocation()

  if (isLoading) return <PageSpinner />
  if (hasError) {
    return <ErrorState error={{ message: 'We could not confirm your session. Please check your connection and try again.' }} onRetry={refresh} />
  }
  if (!isAuthenticated) {
    const redirect = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  return children ?? <Outlet />
}

export function GuestOnly({ children }) {
  const auth = useAuth()
  const [params] = useSearchParams()

  if (auth.isLoading) return <PageSpinner />
  // Same destination the login page navigates to, so the two never race to different pages.
  if (auth.isAuthenticated) return <Navigate to={safeRedirect(params.get('redirect'), homePathFor(auth))} replace />

  return children ?? <Outlet />
}

/** Requires one of the given permissions (string or array). */
export function RequirePermission({ permission, children }) {
  const { canAny } = useAuth()
  const required = Array.isArray(permission) ? permission : [permission]

  if (!canAny(required)) return <ForbiddenPage embedded />

  return children ?? <Outlet />
}

/** Renders children only when the user has the permission (buttons, links, menu items). */
export function Can({ permission, children, fallback = null }) {
  const { canAny } = useAuth()
  const required = Array.isArray(permission) ? permission : [permission]

  return canAny(required) ? children : fallback
}

/** Safe post-login redirect: only same-site relative paths are honoured. */
export function safeRedirect(value, fallback = '/account') {
  if (!value) return fallback
  const decoded = decodeURIComponent(value)
  return decoded.startsWith('/') && !decoded.startsWith('//') ? decoded : fallback
}

/** Where a user lands after signing in when no redirect was requested. */
export function homePathFor({ canAccessAdmin }) {
  return canAccessAdmin ? '/admin' : '/account'
}
