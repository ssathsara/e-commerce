import { useEffect, useState } from 'react'
import { useAsync } from './useAsync'
import { useDebounce } from './useDebounce'
import { useQueryParams } from './useQueryParams'

/**
 * Server-driven list state for admin tables: filters/sort/page live in the URL,
 * the search box is debounced, and each change issues one paginated API request.
 */
export function useResourceList(loader, defaults = {}) {
  const [params, setParams] = useQueryParams(defaults)
  const [search, setSearch] = useState(params.search ?? '')
  const debouncedSearch = useDebounce(search)

  useEffect(() => {
    if ((params.search ?? '') !== debouncedSearch) setParams({ search: debouncedSearch })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearch])

  const query = { ...params, page: params.page ?? 1 }
  const result = useAsync(() => loader(query), [JSON.stringify(query)])

  return {
    ...result,
    params,
    setParams,
    search,
    setSearch,
    setPage: (page) => setParams({ page }, { resetPage: false }),
  }
}
