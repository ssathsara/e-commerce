import { Icon } from './Icon'

/** Windowed page list: 1 … 4 5 [6] 7 8 … 20 */
function pageWindow(current, last) {
  const pages = new Set([1, last, current - 1, current, current + 1])
  if (current <= 3) [2, 3, 4].forEach((p) => pages.add(p))
  if (current >= last - 2) [last - 1, last - 2, last - 3].forEach((p) => pages.add(p))

  const sorted = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b)
  const result = []
  sorted.forEach((page, index) => {
    if (index > 0 && page - sorted[index - 1] > 1) result.push('…')
    result.push(page)
  })
  return result
}

export function Pagination({ meta, onChange }) {
  if (!meta || meta.last_page <= 1) {
    return meta?.total ? <p className="px-1 py-3 text-sm text-slate-500">Showing {meta.total} result{meta.total === 1 ? '' : 's'}</p> : null
  }

  const { current_page: current, last_page: last, from, to, total } = meta
  const button = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium transition-colors'

  return (
    <nav className="flex flex-col items-center justify-between gap-3 py-3 sm:flex-row" aria-label="Pagination">
      <p className="text-sm text-slate-500">
        Showing <span className="font-medium text-slate-700">{from}</span>–<span className="font-medium text-slate-700">{to}</span> of{' '}
        <span className="font-medium text-slate-700">{total}</span>
      </p>
      <ul className="flex items-center gap-1">
        <li>
          <button type="button" className={`${button} text-slate-600 hover:bg-slate-100 disabled:opacity-40`} disabled={current <= 1} onClick={() => onChange(current - 1)} aria-label="Previous page">
            <Icon name="chevronLeft" className="h-4 w-4" />
          </button>
        </li>
        {pageWindow(current, last).map((page, index) =>
          page === '…' ? (
            <li key={`gap-${index}`} className="px-1 text-slate-400" aria-hidden="true">
              …
            </li>
          ) : (
            <li key={page} className={page === current ? '' : 'hidden sm:block'}>
              <button
                type="button"
                onClick={() => onChange(page)}
                aria-current={page === current ? 'page' : undefined}
                className={`${button} ${page === current ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
              >
                {page}
              </button>
            </li>
          ),
        )}
        <li>
          <button type="button" className={`${button} text-slate-600 hover:bg-slate-100 disabled:opacity-40`} disabled={current >= last} onClick={() => onChange(current + 1)} aria-label="Next page">
            <Icon name="chevronRight" className="h-4 w-4" />
          </button>
        </li>
      </ul>
    </nav>
  )
}
