import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { authApi } from '../api/authApi'
import { P, SUPER_ADMIN } from '../constants/permissions'
import { cartToken } from '../utils/storage'

const AuthContext = createContext(null)

const GUEST = { status: 'guest', user: null, roles: [], permissions: [] }

/**
 * Holds the signed-in user with roles and permissions from /auth/me.
 * Permission helpers are for UI visibility only — the API enforces authorization.
 */
export function AuthProvider({ children }) {
  const [state, setState] = useState({ ...GUEST, status: 'loading' })

  const setAuthenticated = useCallback((payload) => {
    setState({
      status: 'authenticated',
      user: payload.user,
      roles: payload.roles ?? [],
      permissions: payload.permissions ?? [],
    })
  }, [])

  /**
   * Only a 401 means "not signed in". Transient failures (rate limit, server or
   * network errors) are retried with backoff and then reported as an error state,
   * so a hiccup never silently signs the user out.
   */
  const refresh = useCallback(async () => {
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        setAuthenticated(await authApi.me())
        return
      } catch (error) {
        if (error.status === 401) {
          setState(GUEST)
          return
        }
        await new Promise((resolve) => setTimeout(resolve, 500 * 2 ** attempt))
      }
    }
    setState({ ...GUEST, status: 'error' })
  }, [setAuthenticated])

  useEffect(() => {
    refresh()
  }, [refresh])

  // Any 401 from the API means the session is gone (expired or revoked).
  useEffect(() => {
    const handle = () => setState((current) => (current.status === 'authenticated' ? GUEST : current))
    window.addEventListener('auth:unauthenticated', handle)
    return () => window.removeEventListener('auth:unauthenticated', handle)
  }, [])

  const login = useCallback(
    async (credentials) => {
      const payload = await authApi.login(credentials)
      // The server merged the guest cart into the account cart.
      cartToken.clear()
      setAuthenticated(payload)
      return payload
    },
    [setAuthenticated],
  )

  const register = useCallback(
    async (values) => {
      const payload = await authApi.register(values)
      cartToken.clear()
      setAuthenticated(payload)
      return payload
    },
    [setAuthenticated],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } finally {
      setState(GUEST)
    }
  }, [])

  const value = useMemo(() => {
    const isSuperAdmin = state.roles.includes(SUPER_ADMIN)
    const can = (permission) => isSuperAdmin || state.permissions.includes(permission)

    return {
      ...state,
      isLoading: state.status === 'loading',
      hasError: state.status === 'error',
      isAuthenticated: state.status === 'authenticated',
      isSuperAdmin,
      can,
      canAny: (permissions) => permissions.some(can),
      hasRole: (role) => state.roles.includes(role),
      canAccessAdmin: can(P.ACCESS_ADMIN),
      login,
      register,
      logout,
      refresh,
      setUser: (user) => setState((current) => ({ ...current, user })),
      setAuthenticated,
    }
  }, [state, login, register, logout, refresh, setAuthenticated])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
