import { SearchInput } from '../forms/Fields'

/**
 * Search + filter selects above admin tables.
 * filters: [{ key, label, options: [{ value, label }] }]
 */
export function ListToolbar({ search, onSearch, searchPlaceholder, filters = [], params, setParams, children }) {
  return (
    <div className="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center">
      {onSearch && <SearchInput value={search} onChange={onSearch} placeholder={searchPlaceholder} className="lg:max-w-xs lg:flex-1" />}
      <div className="flex flex-wrap gap-2">
        {filters.map((filter) => (
          <select
            key={filter.key}
            aria-label={filter.label}
            value={params[filter.key] ?? ''}
            onChange={(e) => setParams({ [filter.key]: e.target.value })}
            className="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500"
          >
            <option value="">{filter.label}</option>
            {filter.options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        ))}
      </div>
      {children && <div className="flex flex-wrap gap-2 lg:ml-auto">{children}</div>}
    </div>
  )
}

/** Icon-only row action with an accessible label. */
export function RowAction({ label, onClick, children, tone = 'default', to, as: Component }) {
  const tones = { default: 'text-slate-500 hover:bg-slate-100 hover:text-slate-800', danger: 'text-slate-500 hover:bg-red-50 hover:text-red-600' }
  const className = `inline-flex rounded-md p-1.5 ${tones[tone]}`

  if (Component) {
    return (
      <Component to={to} className={className} aria-label={label} title={label}>
        {children}
      </Component>
    )
  }

  return (
    <button type="button" onClick={onClick} className={className} aria-label={label} title={label}>
      {children}
    </button>
  )
}
