import { useCallback, useEffect, useRef, useState } from 'react'
import { normalizeError } from '../utils/errors'

/**
 * Runs an async loader and tracks { data, error, loading }. Responses from stale
 * calls (e.g. after filters changed) are ignored, so fast typing never shows old results.
 *
 * @param {() => Promise<any>} loader
 * @param {Array} deps  Re-runs the loader when these change.
 */
export function useAsync(loader, deps = [], { immediate = true, initialData = null } = {}) {
  const [data, setData] = useState(initialData)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(immediate)
  const callId = useRef(0)
  const loaderRef = useRef(loader)
  loaderRef.current = loader

  const run = useCallback(async () => {
    const id = ++callId.current
    setLoading(true)
    setError(null)

    try {
      const result = await loaderRef.current()
      if (id === callId.current) setData(result)
      return result
    } catch (e) {
      if (id === callId.current) setError(normalizeError(e))
      return undefined
    } finally {
      if (id === callId.current) setLoading(false)
    }
  }, [])

  useEffect(() => {
    if (immediate) run()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)

  return { data, error, loading, reload: run, setData }
}
