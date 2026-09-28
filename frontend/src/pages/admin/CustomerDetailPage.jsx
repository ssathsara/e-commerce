import { Link, useNavigate, useParams } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { Badge, StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { DataTable } from '../../components/common/DataTable'
import { Card, PageHeader } from '../../components/common/Misc'
import { PageSpinner } from '../../components/common/Spinner'
import { ErrorState } from '../../components/common/States'
import { P } from '../../constants/permissions'
import { ORDER_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { AddressBlock } from '../../features/orders/OrderItemsTable'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatDate, formatDateTime, formatPrice } from '../../utils/format'

export default function CustomerDetailPage() {
  const { customerId } = useParams()
  const { can } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const { confirm, dialog } = useConfirm()
  const { data: customer, loading, error, reload } = useAsync(() => adminApi.customers.get(customerId), [customerId])
  useDocumentTitle(customer?.name ?? 'Customer')

  if (loading) return <PageSpinner />
  if (error) return <ErrorState error={error} onRetry={reload} />

  const toggleActive = () =>
    confirm({
      title: customer.is_active ? 'Deactivate this account?' : 'Reactivate this account?',
      message: customer.is_active ? 'The customer will be signed out and unable to sign in until reactivated.' : 'The customer will be able to sign in again.',
      confirmLabel: customer.is_active ? 'Deactivate' : 'Reactivate',
      tone: customer.is_active ? 'danger' : 'primary',
      onConfirm: async () => {
        try {
          const { message } = await adminApi.customers.setActive(customer.id, !customer.is_active)
          toast.success(message)
          reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  const remove = () =>
    confirm({
      title: 'Delete this customer?',
      message: 'The account is removed and signed out. Order history is retained for accounting.',
      confirmLabel: 'Delete customer',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.customers.remove(customer.id))
          navigate('/admin/customers')
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  return (
    <div>
      <PageHeader
        title={customer.name}
        description={customer.email}
        actions={
          <>
            {can(P.EDIT_CUSTOMERS) && (
              <Button variant="secondary" onClick={toggleActive}>
                {customer.is_active ? 'Deactivate' : 'Reactivate'}
              </Button>
            )}
            {can(P.DELETE_CUSTOMERS) && (
              <Button variant="danger" onClick={remove}>
                Delete
              </Button>
            )}
          </>
        }
      >
        <Link to="/admin/customers" className="mb-2 inline-block text-sm text-brand-600 hover:underline">
          ← All customers
        </Link>
      </PageHeader>

      <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
        <Card title="Recent orders" bodyClassName="p-0">
          <DataTable
            columns={[
              {
                key: 'order_number',
                header: 'Order',
                render: (o) =>
                  can(P.VIEW_ORDERS) ? (
                    <Link to={`/admin/orders/${o.id}`} className="font-medium text-brand-600 hover:underline">
                      {o.order_number}
                    </Link>
                  ) : (
                    o.order_number
                  ),
              },
              { key: 'created_at', header: 'Date', render: (o) => formatDate(o.created_at), hideBelow: 'sm' },
              { key: 'status', header: 'Status', render: (o) => <StatusBadge value={o.status} tones={ORDER_STATUS_TONES} /> },
              { key: 'grand_total', header: 'Total', render: (o) => formatPrice(o.grand_total, o.currency) },
            ]}
            rows={customer.recent_orders}
            empty={<p className="p-5 text-sm text-slate-500">This customer has not placed any orders.</p>}
          />
          {customer.orders_count > customer.recent_orders.length && can(P.VIEW_ORDERS) && (
            <div className="border-t border-slate-100 p-4">
              <Link to={`/admin/orders?customer_id=${customer.id}`} className="text-sm font-medium text-brand-600 hover:underline">
                View all {customer.orders_count} orders
              </Link>
            </div>
          )}
        </Card>

        <div className="space-y-6">
          <Card title="Profile">
            <dl className="divide-y divide-slate-100 text-sm">
              {[
                ['Status', <Badge key="s" tone={customer.is_active ? 'green' : 'red'}>{customer.is_active ? 'Active' : 'Deactivated'}</Badge>],
                ['Email verified', customer.email_verified ? 'Yes' : 'No'],
                ['Phone', customer.phone ?? '—'],
                ['Orders', customer.orders_count],
                ['Total spent', formatPrice(customer.total_spent ?? 0)],
                ['Customer since', formatDate(customer.created_at)],
                ['Last sign-in', customer.last_login_at ? formatDateTime(customer.last_login_at) : '—'],
              ].map(([label, value]) => (
                <div key={label} className="flex items-center justify-between gap-4 py-2">
                  <dt className="text-slate-500">{label}</dt>
                  <dd className="text-right font-medium text-slate-800">{value}</dd>
                </div>
              ))}
            </dl>
          </Card>
          <Card title="Addresses">
            {customer.addresses.length === 0 ? (
              <p className="text-sm text-slate-500">No saved addresses.</p>
            ) : (
              <div className="space-y-4">
                {customer.addresses.map((address) => (
                  <AddressBlock key={address.id} title={address.label ?? 'Address'} address={address} />
                ))}
              </div>
            )}
          </Card>
        </div>
      </div>
      {dialog}
    </div>
  )
}
