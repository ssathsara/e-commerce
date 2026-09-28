import { Link } from 'react-router-dom'
import { orderApi } from '../../api/orderApi'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { DataTable } from '../../components/common/DataTable'
import { Card } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { ORDER_STATUS_TONES, PAYMENT_STATUS_TONES } from '../../constants/statuses'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useQueryParams } from '../../hooks/useQueryParams'
import { formatDate, formatPrice } from '../../utils/format'

const STATUSES = ['', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']

export default function OrdersPage() {
  useDocumentTitle('My orders')
  const [params, setParams] = useQueryParams()
  const { data, loading, error, reload } = useAsync(() => orderApi.list({ status: params.status, page: params.page ?? 1, per_page: 10 }), [params.status, params.page])

  const columns = [
    {
      key: 'order_number',
      header: 'Order',
      render: (order) => (
        <Link to={`/account/orders/${order.id}`} className="font-medium text-brand-600 hover:underline">
          {order.order_number}
        </Link>
      ),
    },
    { key: 'created_at', header: 'Date', render: (order) => formatDate(order.created_at), hideBelow: 'sm' },
    { key: 'items_count', header: 'Items', hideBelow: 'md' },
    { key: 'status', header: 'Status', render: (order) => <StatusBadge value={order.status} tones={ORDER_STATUS_TONES} /> },
    { key: 'payment_status', header: 'Payment', render: (order) => <StatusBadge value={order.payment_status} tones={PAYMENT_STATUS_TONES} />, hideBelow: 'md' },
    { key: 'grand_total', header: 'Total', render: (order) => <span className="font-semibold">{formatPrice(order.grand_total, order.currency)}</span>, className: 'text-right', headerClassName: 'text-right' },
  ]

  return (
    <Card
      title="Order history"
      bodyClassName="p-0"
      actions={
        <select aria-label="Filter by status" value={params.status ?? ''} onChange={(e) => setParams({ status: e.target.value })} className="rounded-lg border-slate-300 py-1 text-sm focus:border-brand-500 focus:ring-brand-500">
          {STATUSES.map((status) => (
            <option key={status} value={status}>
              {status ? status.charAt(0).toUpperCase() + status.slice(1) : 'All statuses'}
            </option>
          ))}
        </select>
      }
    >
      <DataTable
        caption="Your orders"
        columns={columns}
        rows={data?.items}
        loading={loading}
        error={error}
        onRetry={reload}
        empty={<EmptyState icon="receipt" title="No orders found" description={params.status ? 'Try another status filter.' : 'Your orders will appear here.'} action={!params.status && <Button to="/products">Start shopping</Button>} />}
      />
      <div className="border-t border-slate-100 px-4">
        <Pagination meta={data?.meta} onChange={(page) => setParams({ page }, { resetPage: false })} />
      </div>
    </Card>
  )
}
