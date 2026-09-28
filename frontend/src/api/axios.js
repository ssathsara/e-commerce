import axios from 'axios'
import { normalizeError } from '../utils/errors'
import { cartToken } from '../utils/storage'

/**
 * Single Axios instance for the Laravel API.
 *
 * Authentication is Sanctum SPA mode: an HttpOnly session cookie plus a CSRF token
 * that Axios copies from the XSRF-TOKEN cookie into the X-XSRF-TOKEN header.
 * No auth token is ever stored in JavaScript.
 */
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

const MUTATING = new Set(['post', 'put', 'patch', 'delete'])
let csrfRequest = null

/** Fetch the CSRF cookie once (or again when forced after it expired). */
export function ensureCsrfCookie(force = false) {
  const hasCookie = typeof document !== 'undefined' && document.cookie.includes('XSRF-TOKEN=')

  if (!force && hasCookie) return Promise.resolve()

  if (!csrfRequest || force) {
    csrfRequest = axios.get('/sanctum/csrf-cookie', { withCredentials: true }).finally(() => {
      csrfRequest = null
    })
  }

  return csrfRequest
}

api.interceptors.request.use(async (config) => {
  const token = cartToken.get()
  if (token) {
    config.headers['X-Cart-Token'] = token
  }

  if (MUTATING.has((config.method || 'get').toLowerCase())) {
    await ensureCsrfCookie()
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error

    // CSRF token expired: refresh it and retry exactly once.
    if (response?.status === 419 && config && !config.__csrfRetried) {
      config.__csrfRetried = true
      await ensureCsrfCookie(true)
      return api(config)
    }

    if (response?.status === 401 && !config?.skipAuthEvent) {
      window.dispatchEvent(new CustomEvent('auth:unauthenticated'))
    }

    return Promise.reject(normalizeError(error))
  },
)

/** Unwraps the { success, message, data } envelope. */
export const unwrap = (response) => response.data.data

/** Unwraps a paginated response into { items, meta }. */
export const unwrapPage = (response) => ({ items: response.data.data, meta: response.data.meta })

/** Removes empty values so they are not sent as query params. */
export function cleanParams(params = {}) {
  return Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '' && !(Array.isArray(v) && v.length === 0)),
  )
}

/**
 * Builds multipart form data (for uploads). Booleans become 1/0 as Laravel expects,
 * and updates are sent as POST with _method spoofing because PHP does not parse
 * multipart bodies on PUT.
 */
export function toFormData(values, method = null) {
  const form = new FormData()

  Object.entries(values).forEach(([key, value]) => {
    if (value === undefined) return
    if (value === null) form.append(key, '')
    else if (typeof value === 'boolean') form.append(key, value ? '1' : '0')
    else form.append(key, value)
  })

  if (method) form.append('_method', method)

  return form
}
