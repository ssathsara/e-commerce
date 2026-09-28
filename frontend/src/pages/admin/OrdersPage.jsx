import { Link } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { ListToolbar } from '../../components/admin/ListToolbar'
import { StatusBadge } from '../../components/common/Badge'
import { DataTable } from '../../components/common/DataTable'
import { PageHeader } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { ORDER_STATUS_TONES, PAYMENT_STATUS_TONES } from '../../constants/statuses'
import { useAdminOptions } from '../../hooks/useAdminOptions'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useResourceList } from '../../hooks/useResourceList'
import { formatDateTime, formatPrice } from '../../utils/format'

export default function OrdersPage() {
  useDocumentTitle('Orders')
  const options = useAdminOptions()
  const list = useResourceList((query) => adminApi.orders.list({ ...query, per_page: 20 }))

  const columns = [
    {
      key: 'order_number',
      header: 'Order',
      render: (o) => (
        <Link to={`/admin/orders/${o.id}`} className="font-medium text-brand-600 hover:underline">
          {o.order_number}
        </Link>
      ),
    },
    {
      key: 'customer',
      header: 'Customer',
      render: (o) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-slate-800">{o.customer?.name}</p>
          <p className="truncate text-xs text-slate-500">{o.customer_email}</p>
        </div>
      ),
      hideBelow: 'md',
    },
    { key: 'created_at', header: 'Date', render: (o) => formatDateTime(o.created_at), hideBelow: 'lg' },
    { key: 'status', header: 'Status', render: (o) => <StatusBadge value={o.status} tones={ORDER_STATUS_TONES} /> },
    { key: 'payment_status', header: 'Payment', render: (o) => <StatusBadge value={o.payment_status} tones={PAYMENT_STATUS_TONES} />, hideBelow: 'sm' },
    { key: 'items_count', header: 'Items', hideBelow: 'lg' },
    { key: 'grand_total', header: 'Total', render: (o) => <span className="font-semibold">{formatPrice(o.grand_total, o.currency)}</span>, className: 'text-right', headerClassName: 'text-right' },
  ]

  return (
    <div>
      <PageHeader title="Orders" description="Review, fulfil and track customer orders." />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Order number, email or name…"
          params={list.params}
          setParams={list.setParams}
          filters={[
            { key: 'status', label: 'All statuses', options: options?.order_statuses ?? [] },
            { key: 'payment_status', label: 'All payments', options: options?.payment_statuses ?? [] },
            { key: 'payment_method', label: 'All methods', options: [{ value: 'card', label: 'Card' }, { value: 'cash_on_delivery', label: 'Cash on delivery' }] },
            { key: 'sort', label: 'Newest first', options: [{ value: 'oldest', label: 'Oldest first' }, { value: 'total_desc', label: 'Highest total' }, { value: 'total_asc', label: 'Lowest total' }] },
          ]}
        >
          <label className="flex items-center gap-2 text-sm text-slate-600">
            From
            <input type="date" value={list.params.from ?? ''} onChange={(e) => list.setParams({ from: e.target.value })} className="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500" />
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-600">
            To
            <input type="date" value={list.params.to ?? ''} onChange={(e) => list.setParams({ to: e.target.value })} className="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500" />
          </label>
        </ListToolbar>
        <DataTable caption="Orders" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="receipt" title="No orders match your filters" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
    </div>
  )
}
