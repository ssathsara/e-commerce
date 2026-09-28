import { useEffect, useState } from 'react'
import { adminApi } from '../api/adminApi'

// Lookup data (enum options, category/brand pickers) shared across admin screens.
let cache = null

export function useAdminOptions() {
  const [options, setOptions] = useState(cache)

  useEffect(() => {
    let active = true
    adminApi
      .options()
      .then((data) => {
        cache = data
        if (active) setOptions(data)
      })
      .catch(() => {})
    return () => {
      active = false
    }
  }, [])

  return options
}

/** Call after creating/editing categories or brands so pickers refresh. */
export function invalidateAdminOptions() {
  cache = null
}
