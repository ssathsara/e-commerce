import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { DataTable } from '../../components/common/DataTable'
import { Card, PageHeader } from '../../components/common/Misc'
import { PageSpinner } from '../../components/common/Spinner'
import { ErrorState } from '../../components/common/States'
import { Input, Textarea } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { ORDER_STATUS_TONES, PAYMENT_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { AddressBlock, OrderItemsTable, OrderTotals } from '../../features/orders/OrderItemsTable'
import { OrderStatusTimeline } from '../../features/orders/OrderStatusTimeline'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatDateTime, formatPrice, titleCase } from '../../utils/format'

const TRANSITION_LABELS = {
  confirmed: 'Confirm order',
  processing: 'Start processing',
  shipped: 'Mark as shipped',
  delivered: 'Mark as delivered',
  cancelled: 'Cancel order',
  refunded: 'Refund order',
}

export default function OrderDetailPage() {
  const { orderId } = useParams()
  const { can } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const { data: order, loading, error, reload, setData } = useAsync(() => adminApi.orders.get(orderId), [orderId])
  const [tracking, setTracking] = useState('')
  const [notes, setNotes] = useState('')
  const [savingNotes, setSavingNotes] = useState(false)
  const [busyStatus, setBusyStatus] = useState(null)
  useDocumentTitle(order ? `Order ${order.order_number}` : 'Order')

  useEffect(() => {
    if (order) {
      setTracking(order.tracking_number ?? '')
      setNotes(order.admin_notes ?? '')
    }
  }, [order])

  if (loading) return <PageSpinner />
  if (error) return <ErrorState error={error} onRetry={reload} />

  const canTransition = (status) => (status === 'cancelled' ? can(P.CANCEL_ORDERS) : can(P.UPDATE_ORDERS))
  const transitions = order.allowed_transitions.filter(canTransition)

  const applyStatus = async (status) => {
    setBusyStatus(status)
    try {
      const { data, message } = await adminApi.orders.updateStatus(order.id, status, status === 'shipped' ? tracking : null)
      setData(data)
      toast.success(message)
    } catch (e) {
      toast.error(e.message)
      throw e
    } finally {
      setBusyStatus(null)
    }
  }

  const requestStatus = (status) => {
    if (status === 'cancelled' || status === 'refunded') {
      confirm({
        title: `${TRANSITION_LABELS[status]}?`,
        message:
          status === 'cancelled'
            ? 'Stock will be returned to inventory, the coupon released, and any captured payment refunded.'
            : 'The captured payment will be refunded to the customer. Stock is not restocked automatically — record a return in Inventory if items come back.',
        confirmLabel: TRANSITION_LABELS[status],
        onConfirm: () => applyStatus(status),
      })
    } else {
      applyStatus(status).catch(() => {})
    }
  }

  const updatePayment = async (status) => {
    try {
      const { data, message } = await adminApi.orders.updatePaymentStatus(order.id, status)
      setData(data)
      toast.success(message)
    } catch (e) {
      toast.error(e.message)
    }
  }

  const saveNotes = async () => {
    setSavingNotes(true)
    try {
      const { data, message } = await adminApi.orders.updateDetails(order.id, { admin_notes: notes, tracking_number: tracking || null })
      setData(data)
      toast.success(message)
    } catch (e) {
      toast.error(e.message)
    } finally {
      setSavingNotes(false)
    }
  }

  return (
    <div>
      <PageHeader title={`Order ${order.order_number}`} description={`Placed ${formatDateTime(order.created_at)}`}>
        <Link to="/admin/orders" className="mb-2 inline-block text-sm text-brand-600 hover:underline">
          ← All orders
        </Link>
        <div className="mb-2 flex gap-2">
          <StatusBadge value={order.status} tones={ORDER_STATUS_TONES} />
          <StatusBadge value={order.payment_status} tones={PAYMENT_STATUS_TONES} label={`Payment ${order.payment_status}`} />
        </div>
      </PageHeader>

      <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <Card>
            <OrderStatusTimeline order={order} />
          </Card>

          <Card title={`Items (${order.items.length})`} bodyClassName="px-5">
            <OrderItemsTable order={order} linkProducts={false} />
          </Card>

          <Card title="Payments" bodyClassName="p-0">
            <DataTable
              columns={[
                { key: 'created_at', header: 'Date', render: (p) => formatDateTime(p.created_at) },
                { key: 'method', header: 'Method', render: (p) => `${p.method_label}${p.card ? ` · ${titleCase(p.card.brand)} ${p.card.last4}` : ''}` },
                { key: 'status', header: 'Status', render: (p) => <StatusBadge value={p.status} tones={PAYMENT_STATUS_TONES} /> },
                { key: 'amount', header: 'Amount', render: (p) => formatPrice(p.amount, p.currency) },
                { key: 'reference', header: 'Reference', render: (p) => <span className="font-mono text-xs">{p.transaction_reference ?? p.failure_reason ?? '—'}</span>, hideBelow: 'md' },
              ]}
              rows={order.payments}
              empty={<p className="p-5 text-sm text-slate-500">No payment attempts.</p>}
            />
          </Card>

          <Card title="Internal notes & tracking">
            <div className="space-y-4">
              <Input label="Tracking number" value={tracking} onChange={(e) => setTracking(e.target.value)} disabled={!can(P.EDIT_ORDERS) && !can(P.UPDATE_ORDERS)} />
              <Textarea label="Admin notes" hint="Only visible to staff." rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} disabled={!can(P.EDIT_ORDERS)} />
              {can(P.EDIT_ORDERS) && (
                <Button variant="secondary" onClick={saveNotes} loading={savingNotes}>
                  Save notes
                </Button>
              )}
            </div>
          </Card>
        </div>

        <div className="space-y-6">
          <Card title="Actions">
            {transitions.length === 0 ? (
              <p className="text-sm text-slate-500">No further status changes are available{order.allowed_transitions.length ? ' for your role' : ''}.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {transitions.map((status) => (
                  <Button key={status} variant={status === 'cancelled' || status === 'refunded' ? 'secondary' : 'primary'} className={status === 'cancelled' || status === 'refunded' ? '!text-red-600' : ''} loading={busyStatus === status} disabled={busyStatus !== null} onClick={() => requestStatus(status)} block>
                    {TRANSITION_LABELS[status]}
                  </Button>
                ))}
              </div>
            )}
            {order.status === 'pending' && order.payment_method === 'card' && order.payment_status !== 'paid' && <p className="mt-3 text-xs text-slate-500">Card orders can be confirmed once payment is received.</p>}

            {can(P.EDIT_ORDERS) && order.allowed_payment_transitions.length > 0 && (
              <div className="mt-5 border-t border-slate-100 pt-4">
                <p className="mb-2 text-sm font-medium text-slate-700">Payment status (cash on delivery)</p>
                <div className="flex flex-wrap gap-2">
                  {order.allowed_payment_transitions.map((status) => (
                    <Button key={status} size="sm" variant="secondary" onClick={() => updatePayment(status)}>
                      Mark {status}
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </Card>

          <Card title="Summary">
            <OrderTotals order={order} />
          </Card>

          <Card title="Customer">
            <div className="space-y-4 text-sm">
              <div>
                {order.customer && can(P.VIEW_CUSTOMERS) ? (
                  <Link to={`/admin/customers/${order.customer.id}`} className="font-medium text-brand-600 hover:underline">
                    {order.customer.name}
                  </Link>
                ) : (
                  <p className="font-medium text-slate-900">{order.customer?.name}</p>
                )}
                <p className="text-slate-500">{order.customer_email}</p>
              </div>
              <AddressBlock title="Shipping address" address={order.shipping_address} />
              <AddressBlock title="Billing address" address={order.billing_address} />
              <p>
                <span className="font-semibold text-slate-900">Delivery:</span> {order.shipping_method_label}
              </p>
              {order.notes && (
                <div>
                  <h3 className="font-semibold text-slate-900">Customer notes</h3>
                  <p className="mt-1 text-slate-600">{order.notes}</p>
                </div>
              )}
            </div>
          </Card>
        </div>
      </div>
      {dialog}
    </div>
  )
}
