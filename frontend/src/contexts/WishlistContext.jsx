import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { accountApi } from '../api/accountApi'
import { useAuth } from './AuthContext'

const WishlistContext = createContext(null)

/** Tracks wishlisted product ids so any product card can show/toggle its state. */
export function WishlistProvider({ children }) {
  const { isAuthenticated, user } = useAuth()
  const [ids, setIds] = useState(() => new Set())

  useEffect(() => {
    if (!isAuthenticated) {
      setIds(new Set())
      return
    }

    let active = true
    accountApi
      .wishlistIds()
      .then((list) => active && setIds(new Set(list)))
      .catch(() => {})

    return () => {
      active = false
    }
  }, [isAuthenticated, user?.id])

  const toggle = useCallback(
    async (productId) => {
      const had = ids.has(productId)

      // Optimistic update, rolled back on failure.
      setIds((current) => {
        const next = new Set(current)
        if (had) next.delete(productId)
        else next.add(productId)
        return next
      })

      try {
        if (had) await accountApi.removeFromWishlist(productId)
        else await accountApi.addToWishlist(productId)
        return !had
      } catch (error) {
        setIds((current) => {
          const next = new Set(current)
          if (had) next.add(productId)
          else next.delete(productId)
          return next
        })
        throw error
      }
    },
    [ids],
  )

  const remove = useCallback((productId) => {
    setIds((current) => {
      const next = new Set(current)
      next.delete(productId)
      return next
    })
  }, [])

  const value = useMemo(() => ({ ids, has: (id) => ids.has(id), toggle, remove, count: ids.size }), [ids, toggle, remove])

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>
}

export function useWishlist() {
  const context = useContext(WishlistContext)
  if (!context) throw new Error('useWishlist must be used within WishlistProvider')
  return context
}
