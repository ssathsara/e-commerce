import { Link } from 'react-router-dom'
import { Icon } from './Icon'

/** items: [{ label, to? }] — the last item is the current page. */
export function Breadcrumbs({ items, className = '' }) {
  return (
    <nav aria-label="Breadcrumb" className={className}>
      <ol className="flex flex-wrap items-center gap-1 text-sm text-slate-500">
        {items.map((item, index) => {
          const last = index === items.length - 1
          return (
            <li key={`${item.label}-${index}`} className="flex items-center gap-1">
              {index > 0 && <Icon name="chevronRight" className="h-3.5 w-3.5 text-slate-400" />}
              {item.to && !last ? (
                <Link to={item.to} className="hover:text-slate-700 hover:underline">
                  {item.label}
                </Link>
              ) : (
                <span aria-current={last ? 'page' : undefined} className={last ? 'font-medium text-slate-700' : ''}>
                  {item.label}
                </span>
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
