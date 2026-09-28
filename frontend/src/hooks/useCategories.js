import { useEffect, useState } from 'react'
import { productApi } from '../api/productApi'

// The category tree changes rarely; share one request across all components.
let cache = null
let pending = null

function loadCategories() {
  if (cache) return Promise.resolve(cache)
  if (!pending) {
    pending = productApi
      .categories()
      .then((tree) => {
        cache = tree
        return tree
      })
      .finally(() => {
        pending = null
      })
  }
  return pending
}

export function useCategories() {
  const [categories, setCategories] = useState(cache ?? [])
  const [loading, setLoading] = useState(!cache)

  useEffect(() => {
    let active = true
    loadCategories()
      .then((tree) => active && setCategories(tree))
      .catch(() => {})
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  return { categories, loading }
}

/** Test helper. */
export function resetCategoryCache() {
  cache = null
  pending = null
}
