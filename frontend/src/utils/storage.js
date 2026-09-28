/**
 * localStorage wrapper that never throws (private mode, disabled storage, SSR/tests).
 * Only non-sensitive values are stored here: the guest cart token and UI preferences.
 * Authentication uses HttpOnly session cookies and is never stored in JS.
 */
export const storage = {
  get(key) {
    try {
      return window.localStorage.getItem(key)
    } catch {
      return null
    }
  },
  set(key, value) {
    try {
      window.localStorage.setItem(key, value)
    } catch {
      /* ignore */
    }
  },
  remove(key) {
    try {
      window.localStorage.removeItem(key)
    } catch {
      /* ignore */
    }
  },
}

const CART_TOKEN_KEY = 'shop.cartToken'

export const cartToken = {
  get: () => storage.get(CART_TOKEN_KEY),
  set: (token) => (token ? storage.set(CART_TOKEN_KEY, token) : storage.remove(CART_TOKEN_KEY)),
  clear: () => storage.remove(CART_TOKEN_KEY),
}
