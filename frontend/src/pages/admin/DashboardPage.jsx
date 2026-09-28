import { Link } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { TimeSeriesChart } from '../../components/admin/TimeSeriesChart'
import { StatusBadge } from '../../components/common/Badge'
import { Icon } from '../../components/common/Icon'
import { Card, PageHeader } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { EmptyState, ErrorState } from '../../components/common/States'
import { P } from '../../constants/permissions'
import { ORDER_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatDate, formatNumber, formatPrice } from '../../utils/format'

function StatTile({ label, value, icon, to, hint }) {
  const content = (
    <>
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium text-slate-500">{label}</p>
        <Icon name={icon} className="h-5 w-5 text-slate-400" />
      </div>
      <p className="mt-2 text-2xl font-bold tracking-tight text-slate-900">{value}</p>
      {hint && <p className="mt-1 text-xs text-slate-500">{hint}</p>}
    </>
  )

  return to ? (
    <Link to={to} className="card block p-5 transition-shadow hover:shadow-md">
      {content}
    </Link>
  ) : (
    <div className="card p-5">{content}</div>
  )
}

export default function DashboardPage() {
  useDocumentTitle('Admin dashboard')
  const { can } = useAuth()
  const { data, loading, error, reload } = useAsync(() => adminApi.dashboard(), [])

  if (error) return <ErrorState error={error} onRetry={reload} />

  const canSeeSales = data?.sales_chart != null
  const last30Revenue = data?.sales_chart?.reduce((sum, day) => sum + day.revenue, 0) ?? 0
  const tiles = data
    ? [
        canSeeSales && { label: 'Total revenue', value: formatPrice(data.total_revenue), icon: 'cash', hint: `${formatPrice(last30Revenue)} in the last 30 days` },
        { label: 'Total orders', value: formatNumber(data.total_orders), icon: 'receipt', to: can(P.VIEW_ORDERS) ? '/admin/orders' : null },
        { label: 'Pending orders', value: formatNumber(data.pending_orders), icon: 'alert', to: can(P.VIEW_ORDERS) ? '/admin/orders?status=pending' : null, hint: 'Awaiting confirmation' },
        { label: 'Customers', value: formatNumber(data.total_customers), icon: 'users', to: can(P.VIEW_CUSTOMERS) ? '/admin/customers' : null },
        { label: 'Products', value: formatNumber(data.total_products), icon: 'box', to: can(P.VIEW_PRODUCTS) ? '/admin/products' : null },
        { label: 'Low stock', value: formatNumber(data.low_stock_products), icon: 'archive', to: can(P.VIEW_INVENTORY) ? '/admin/inventory?stock=low' : null, hint: 'At or below threshold' },
        { label: 'Out of stock', value: formatNumber(data.out_of_stock_products), icon: 'archive', to: can(P.VIEW_INVENTORY) ? '/admin/inventory?stock=out' : null },
      ].filter(Boolean)
    : []

  const maxStatus = Math.max(1, ...(data?.order_status_breakdown.map((s) => s.count) ?? [1]))

  return (
    <div>
      <PageHeader title="Dashboard" description="Live figures from your store." />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {loading ? Array.from({ length: 7 }).map((_, i) => <Skeleton key={i} className="h-28 w-full rounded-xl" />) : tiles.map((tile) => <StatTile key={tile.label} {...tile} />)}
      </div>

      {(loading || canSeeSales) && (
      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <Card title="Revenue — last 30 days (paid orders)">
          {loading ? <Skeleton className="h-64 w-full" /> : <TimeSeriesChart data={data.sales_chart} dataKey="revenue" valueLabel="Revenue" formatValue={(v) => formatPrice(v).replace(/\.00$/, '')} />}
        </Card>
        <Card title="Paid orders per day — last 30 days">
          {loading ? <Skeleton className="h-64 w-full" /> : <TimeSeriesChart type="bar" data={data.sales_chart} dataKey="orders" valueLabel="Orders" formatValue={(v) => formatNumber(v)} />}
        </Card>
      </div>
      )}

      <div className="mt-6 grid gap-6 xl:grid-cols-3">
        <Card title="Orders by status">
          {loading ? (
            <Skeleton className="h-56 w-full" />
          ) : (
            <ul className="space-y-3">
              {data.order_status_breakdown.map((row) => (
                <li key={row.status}>
                  <div className="flex items-center justify-between text-sm">
                    <StatusBadge value={row.status} tones={ORDER_STATUS_TONES} label={row.label} />
                    <span className="font-semibold text-slate-900">{row.count}</span>
                  </div>
                  <div className="mt-1.5 h-1.5 rounded-full bg-slate-100" aria-hidden="true">
                    <div className="h-full rounded-full bg-[#2a78d6]" style={{ width: `${(row.count / maxStatus) * 100}%` }} />
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <Card title="Recent orders" className="xl:col-span-2" bodyClassName="p-0">
          {loading ? (
            <div className="space-y-3 p-5">{[1, 2, 3, 4].map((i) => <Skeleton key={i} className="h-10 w-full" />)}</div>
          ) : data.recent_orders.length === 0 ? (
            <EmptyState icon="receipt" title="No orders yet" />
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.recent_orders.map((order) => (
                <li key={order.id}>
                  <Link to={can(P.VIEW_ORDERS) ? `/admin/orders/${order.id}` : '#'} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-slate-50">
                    <div>
                      <p className="font-medium text-slate-900">{order.order_number}</p>
                      <p className="text-slate-500">
                        {order.customer} · {formatDate(order.created_at)}
                      </p>
                    </div>
                    <div className="flex items-center gap-3">
                      <StatusBadge value={order.status} tones={ORDER_STATUS_TONES} />
                      <span className="w-24 text-right font-semibold text-slate-900">{formatPrice(order.grand_total, order.currency)}</span>
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <Card title="Newest customers" className="mt-6" bodyClassName="p-0">
        {loading ? (
          <div className="p-5">
            <Skeleton className="h-24 w-full" />
          </div>
        ) : (
          <ul className="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-3">
            {data.recent_customers.map((customer) => (
              <li key={customer.id} className="flex items-center gap-3 px-5 py-3">
                <span className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600" aria-hidden="true">
                  {customer.name.charAt(0)}
                </span>
                <div className="min-w-0 text-sm">
                  {can(P.VIEW_CUSTOMERS) ? (
                    <Link to={`/admin/customers/${customer.id}`} className="block truncate font-medium text-slate-900 hover:text-brand-600">
                      {customer.name}
                    </Link>
                  ) : (
                    <p className="truncate font-medium text-slate-900">{customer.name}</p>
                  )}
                  <p className="truncate text-slate-500">Joined {formatDate(customer.created_at)}</p>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}
