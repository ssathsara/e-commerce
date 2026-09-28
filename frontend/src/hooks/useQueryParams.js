import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

/**
 * Filter/sort/page state kept in the URL, so listings are shareable and the
 * back button works. Changing any filter resets the page to 1.
 */
export function useQueryParams(defaults = {}) {
  const [searchParams, setSearchParams] = useSearchParams()

  const params = useMemo(() => {
    const result = { ...defaults }
    searchParams.forEach((value, key) => {
      result[key] = value
    })
    return result
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  const setParams = useCallback(
    (updates, { resetPage = true } = {}) => {
      setSearchParams(
        (current) => {
          const next = new URLSearchParams(current)
          Object.entries(updates).forEach(([key, value]) => {
            if (value === undefined || value === null || value === '' || value === false) next.delete(key)
            else next.set(key, String(value))
          })
          if (resetPage && !('page' in updates)) next.delete('page')
          return next
        },
        { replace: false },
      )
    },
    [setSearchParams],
  )

  const reset = useCallback(() => setSearchParams(new URLSearchParams()), [setSearchParams])

  return [params, setParams, reset]
}
