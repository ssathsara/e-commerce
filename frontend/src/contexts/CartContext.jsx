import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { cartApi } from '../api/cartApi'
import { cartToken } from '../utils/storage'
import { useAuth } from './AuthContext'

const CartContext = createContext(null)

/**
 * Client-side mirror of the server cart. Every mutation returns the full,
 * server-priced cart, which replaces local state (the client never computes totals).
 *
 * The selected shipping method is sent with EVERY cart request, so totals shown
 * after any change (e.g. applying a coupon at checkout) include the chosen rate.
 */
export function CartProvider({ children }) {
  const { status, user } = useAuth()
  const [cart, setCart] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const shippingMethod = useRef(null)

  const accept = useCallback((next) => {
    // Guest carts are identified by a token the API returns; keep it for later requests.
    if (next?.token) cartToken.set(next.token)
    setCart(next)
    setError(null)
    return next
  }, [])

  const refresh = useCallback(async () => {
    try {
      accept(await cartApi.get(shippingMethod.current))
    } catch (e) {
      setError(e)
    } finally {
      setLoading(false)
    }
  }, [accept])

  // Reload whenever the auth identity changes (login merges the guest cart server-side).
  useEffect(() => {
    if (status !== 'loading') refresh()
  }, [status, user?.id, refresh])

  const run = useCallback(async (operation) => accept(await operation(shippingMethod.current)), [accept])

  const value = useMemo(
    () => ({
      cart,
      loading,
      error,
      itemCount: cart?.item_count ?? 0,
      refresh,
      addItem: (args) => run((method) => cartApi.addItem(args, method)),
      updateItem: (itemId, quantity) => run((method) => cartApi.updateItem(itemId, quantity, method)),
      removeItem: (itemId) => run((method) => cartApi.removeItem(itemId, method)),
      clear: () => run((method) => cartApi.clear(method)),
      applyCoupon: (code) => run((method) => cartApi.applyCoupon(code, method)),
      removeCoupon: () => run((method) => cartApi.removeCoupon(method)),
      /** Select the shipping method used for totals (null = store default) and re-price. */
      setShippingMethod: (method) => {
        shippingMethod.current = method
        return run((m) => cartApi.get(m))
      },
      reset: () => setCart(null),
    }),
    [cart, loading, error, refresh, run],
  )

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

export function useCart() {
  const context = useContext(CartContext)
  if (!context) throw new Error('useCart must be used within CartProvider')
  return context
}
