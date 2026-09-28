import { TableSkeleton } from './Skeleton'
import { EmptyState, ErrorState } from './States'

/**
 * Responsive table with built-in loading / error / empty states.
 * columns: [{ key, header, render?(row), className?, hideBelow?: 'sm'|'md'|'lg' }]
 * Narrow screens scroll horizontally; low-priority columns can be hidden.
 */
const HIDE = { sm: 'hidden sm:table-cell', md: 'hidden md:table-cell', lg: 'hidden lg:table-cell' }

export function DataTable({ columns, rows, loading, error, onRetry, empty, rowKey = 'id', caption, onRowClick }) {
  if (error) return <ErrorState error={error} onRetry={onRetry} />
  if (loading && !rows?.length) return <TableSkeleton columns={Math.min(columns.length, 6)} />
  if (!rows?.length) return empty ?? <EmptyState title="Nothing here yet" />

  return (
    <div className={`overflow-x-auto ${loading ? 'opacity-60 transition-opacity' : ''}`} aria-busy={loading || undefined}>
      <table className="min-w-full divide-y divide-slate-200 text-sm">
        {caption && <caption className="sr-only">{caption}</caption>}
        <thead className="bg-slate-50">
          <tr>
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={`px-4 py-3 text-left text-xs font-semibold tracking-wide whitespace-nowrap text-slate-500 uppercase ${HIDE[column.hideBelow] ?? ''} ${column.headerClassName ?? ''}`}
              >
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100 bg-white">
          {rows.map((row) => (
            <tr
              key={row[rowKey]}
              className={onRowClick ? 'cursor-pointer hover:bg-slate-50' : 'hover:bg-slate-50/60'}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
            >
              {columns.map((column) => (
                <td key={column.key} className={`px-4 py-3 align-middle text-slate-700 ${HIDE[column.hideBelow] ?? ''} ${column.className ?? ''}`}>
                  {column.render ? column.render(row) : row[column.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
