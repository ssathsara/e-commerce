import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

/** Scrolls to top on page navigation (not on query-string-only changes such as filters). */
export function ScrollToTop() {
  const { pathname } = useLocation()

  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' })
  }, [pathname])

  return null
}
