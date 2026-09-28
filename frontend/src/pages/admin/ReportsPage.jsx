import { Link } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { TimeSeriesChart } from '../../components/admin/TimeSeriesChart'
import { StatusBadge } from '../../components/common/Badge'
import { DataTable } from '../../components/common/DataTable'
import { Card, PageHeader } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { Skeleton } from '../../components/common/Skeleton'
import { EmptyState, ErrorState } from '../../components/common/States'
import { SearchInput } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { ORDER_STATUS_TONES, PAYMENT_STATUS_TONES, PRODUCT_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useAsync } from '../../hooks/useAsync'
import { useDebounce } from '../../hooks/useDebounce'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useQueryParams } from '../../hooks/useQueryParams'
import { formatDate, formatNumber, formatPrice, titleCase } from '../../utils/format'

const TABS = [
  { key: 'sales', label: 'Sales', permission: P.VIEW_SALES_REPORTS },
  { key: 'orders', label: 'Orders', permission: P.VIEW_REPORTS },
  { key: 'products', label: 'Products', permission: P.VIEW_REPORTS },
  { key: 'customers', label: 'Customers', permission: P.VIEW_REPORTS },
  { key: 'inventory', label: 'Inventory', permission: P.VIEW_INVENTORY_REPORTS },
]

function Stat({ label, value }) {
  return (
    <div className="card p-4">
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-1 text-xl font-bold text-slate-900">{value}</p>
    </div>
  )
}

function SalesReport({ query }) {
  const { data, loading, error, reload } = useAsync(() => adminApi.reports.sales(query), [JSON.stringify(query)])
  if (error) return <ErrorState error={error} onRetry={reload} />
  if (loading) return <Skeleton className="h-96 w-full rounded-xl" />

  const series = data.series.map((row) => ({ ...row, date: row.period }))

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <Stat label="Revenue" value={formatPrice(data.totals.revenue)} />
        <Stat label="Paid orders" value={formatNumber(data.totals.orders)} />
        <Stat label="Avg. order value" value={formatPrice(data.totals.average_order_value)} />
        <Stat label="Discounts given" value={formatPrice(data.totals.discounts)} />
        <Stat label="Tax collected" value={formatPrice(data.totals.tax)} />
      </div>
      <Card title={`Revenue by ${data.grain} (paid orders)`}>
        <TimeSeriesChart data={series} dataKey="revenue" valueLabel="Revenue" type={data.grain === 'month' ? 'bar' : 'area'} formatValue={(v) => formatPrice(v).replace(/\.00$/, '')} />
      </Card>
      <Card title="Breakdown" bodyClassName="p-0">
        <DataTable
          rowKey="period"
          columns={[
            { key: 'period', header: data.grain === 'month' ? 'Month' : 'Day' },
            { key: 'orders', header: 'Orders' },
            { key: 'subtotal', header: 'Subtotal', render: (r) => formatPrice(r.subtotal), hideBelow: 'md' },
            { key: 'discounts', header: 'Discounts', render: (r) => formatPrice(r.discounts), hideBelow: 'lg' },
            { key: 'shipping', header: 'Shipping', render: (r) => formatPrice(r.shipping), hideBelow: 'lg' },
            { key: 'tax', header: 'Tax', render: (r) => formatPrice(r.tax), hideBelow: 'md' },
            { key: 'revenue', header: 'Revenue', render: (r) => <span className="font-semibold">{formatPrice(r.revenue)}</span> },
          ]}
          rows={[...data.series].reverse().filter((r) => r.orders > 0)}
          empty={<EmptyState icon="chart" title="No paid orders in this period" />}
        />
      </Card>
    </div>
  )
}

/** Generic paginated report table with optional summary renderer. */
function PagedReport({ loader, query, columns, rowKey = 'id', summary, searchable = true, setParams, search, setSearch }) {
  const { data, loading, error, reload } = useAsync(() => loader(query), [JSON.stringify(query)])

  return (
    <div className="space-y-6">
      {summary && data?.meta?.summary && summary(data.meta.summary)}
      <div className="card overflow-hidden">
        {searchable && (
          <div className="border-b border-slate-100 p-4">
            <SearchInput value={search} onChange={setSearch} placeholder="Search…" className="max-w-xs" />
          </div>
        )}
        <DataTable rowKey={rowKey} columns={columns} rows={data?.items} loading={loading} error={error} onRetry={reload} empty={<EmptyState icon="chart" title="No data for this period" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={data?.meta} onChange={(page) => setParams({ page }, { resetPage: false })} />
        </div>
      </div>
    </div>
  )
}

export default function ReportsPage() {
  useDocumentTitle('Reports')
  const { can } = useAuth()
  const tabs = TABS.filter((t) => can(t.permission))
  const [params, setParams] = useQueryParams()
  const tab = tabs.find((t) => t.key === params.tab) ?? tabs[0]
  const search = params.search ?? ''
  const debounced = useDebounce(search)
  const query = { from: params.from, to: params.to, grain: params.grain, page: params.page, search: debounced, per_page: 15 }

  const range = (
    <div className="flex flex-wrap items-end gap-3">
      {[
        ['from', 'From'],
        ['to', 'To'],
      ].map(([key, label]) => (
        <label key={key} className="text-sm text-slate-600">
          <span className="mb-1 block font-medium">{label}</span>
          <input type="date" value={params[key] ?? ''} onChange={(e) => setParams({ [key]: e.target.value })} className="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500" />
        </label>
      ))}
      {tab?.key === 'sales' && (
        <label className="text-sm text-slate-600">
          <span className="mb-1 block font-medium">Group by</span>
          <select value={params.grain ?? ''} onChange={(e) => setParams({ grain: e.target.value })} className="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">Automatic</option>
            <option value="day">Day</option>
            <option value="month">Month</option>
          </select>
        </label>
      )}
      <p className="pb-2 text-xs text-slate-500">Defaults to the last 30 days.</p>
    </div>
  )

  const shared = { query, setParams, search, setSearch: (value) => setParams({ search: value }) }

  return (
    <div>
      <PageHeader title="Reports" description="Figures are computed from live order, product and inventory data." />

      <div role="tablist" aria-label="Report type" className="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200">
        {tabs.map((t) => (
          <button
            key={t.key}
            role="tab"
            type="button"
            aria-selected={t.key === tab?.key}
            onClick={() => setParams({ tab: t.key, search: '' })}
            className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap ${t.key === tab?.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="mb-6">{range}</div>

      <div role="tabpanel" aria-label={tab?.label}>
        {tab?.key === 'sales' && <SalesReport query={query} />}

        {tab?.key === 'orders' && (
          <PagedReport
            {...shared}
            loader={adminApi.reports.orders}
            summary={(summary) => (
              <div className="grid gap-4 md:grid-cols-3">
                <Card title="By status">
                  <ul className="space-y-2 text-sm">
                    {summary.by_status.map((s) => (
                      <li key={s.status} className="flex justify-between">
                        <StatusBadge value={s.status} tones={ORDER_STATUS_TONES} />
                        <span>
                          {s.count} · {formatPrice(s.total)}
                        </span>
                      </li>
                    ))}
                  </ul>
                </Card>
                <Card title="By payment status">
                  <ul className="space-y-2 text-sm">
                    {summary.by_payment_status.map((s) => (
                      <li key={s.payment_status} className="flex justify-between">
                        <StatusBadge value={s.payment_status} tones={PAYMENT_STATUS_TONES} />
                        <span>{s.count}</span>
                      </li>
                    ))}
                  </ul>
                </Card>
                <Card title="By payment method">
                  <ul className="space-y-2 text-sm">
                    {summary.by_payment_method.map((s) => (
                      <li key={s.payment_method} className="flex justify-between">
                        <span className="text-slate-700">{titleCase(s.payment_method)}</span>
                        <span>{s.count}</span>
                      </li>
                    ))}
                  </ul>
                </Card>
              </div>
            )}
            columns={[
              { key: 'order_number', header: 'Order', render: (o) => (can(P.VIEW_ORDERS) ? <Link to={`/admin/orders/${o.id}`} className="font-medium text-brand-600 hover:underline">{o.order_number}</Link> : o.order_number) },
              { key: 'customer', header: 'Customer', render: (o) => o.customer?.name, hideBelow: 'md' },
              { key: 'created_at', header: 'Date', render: (o) => formatDate(o.created_at), hideBelow: 'sm' },
              { key: 'status', header: 'Status', render: (o) => <StatusBadge value={o.status} tones={ORDER_STATUS_TONES} /> },
              { key: 'grand_total', header: 'Total', render: (o) => formatPrice(o.grand_total, o.currency) },
            ]}
          />
        )}

        {tab?.key === 'products' && (
          <PagedReport
            {...shared}
            rowKey="product_id"
            loader={adminApi.reports.products}
            columns={[
              { key: 'product_name', header: 'Product', render: (r) => <span className="font-medium text-slate-900">{r.product_name}</span> },
              { key: 'sku', header: 'SKU', hideBelow: 'md' },
              { key: 'units_sold', header: 'Units sold' },
              { key: 'orders', header: 'Orders', hideBelow: 'sm' },
              { key: 'revenue', header: 'Revenue', render: (r) => formatPrice(r.revenue) },
            ]}
          />
        )}

        {tab?.key === 'customers' && (
          <PagedReport
            {...shared}
            loader={adminApi.reports.customers}
            columns={[
              { key: 'name', header: 'Customer', render: (r) => (can(P.VIEW_CUSTOMERS) ? <Link to={`/admin/customers/${r.id}`} className="font-medium text-brand-600 hover:underline">{r.name}</Link> : r.name) },
              { key: 'email', header: 'Email', hideBelow: 'md' },
              { key: 'orders', header: 'Orders' },
              { key: 'total_spent', header: 'Paid total', render: (r) => formatPrice(r.total_spent) },
              { key: 'customer_since', header: 'Since', render: (r) => formatDate(r.customer_since), hideBelow: 'lg' },
            ]}
          />
        )}

        {tab?.key === 'inventory' && (
          <PagedReport
            {...shared}
            loader={adminApi.reports.inventory}
            summary={(summary) => (
              <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <Stat label="Units in stock" value={formatNumber(summary.total_units_in_stock)} />
                  <Stat label="Stock value (at price)" value={formatPrice(summary.stock_value)} />
                  <Stat label="Low stock" value={summary.low_stock_products} />
                  <Stat label="Out of stock" value={summary.out_of_stock_products} />
                </div>
                <Card title="Movements in period" bodyClassName="p-0">
                  <DataTable
                    rowKey="type"
                    columns={[
                      { key: 'label', header: 'Type' },
                      { key: 'movements', header: 'Movements' },
                      { key: 'net_quantity', header: 'Net units', render: (r) => (r.net_quantity > 0 ? `+${r.net_quantity}` : r.net_quantity) },
                    ]}
                    rows={summary.movements}
                  />
                </Card>
              </div>
            )}
            columns={[
              { key: 'name', header: 'Product', render: (r) => <span className="font-medium text-slate-900">{r.name}</span> },
              { key: 'sku', header: 'SKU', hideBelow: 'md' },
              { key: 'stock_quantity', header: 'Stock', render: (r) => <span className={r.stock_quantity === 0 ? 'font-semibold text-red-600' : ''}>{r.stock_quantity}</span> },
              { key: 'low_stock_threshold', header: 'Threshold', hideBelow: 'sm' },
              { key: 'status', header: 'Status', render: (r) => <StatusBadge value={r.status} tones={PRODUCT_STATUS_TONES} />, hideBelow: 'md' },
              { key: 'stock_value', header: 'Value', render: (r) => formatPrice(r.stock_value), hideBelow: 'lg' },
            ]}
          />
        )}
      </div>
    </div>
  )
}
