import { useRef, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import { orderApi } from '../../api/orderApi'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { Card } from '../../components/common/Misc'
import { PageSpinner } from '../../components/common/Spinner'
import { ErrorState } from '../../components/common/States'
import { ORDER_STATUS_TONES, PAYMENT_STATUS_TONES, TEST_CARDS } from '../../constants/statuses'
import { useToast } from '../../contexts/ToastContext'
import { AddressBlock, OrderItemsTable, OrderTotals } from '../../features/orders/OrderItemsTable'
import { OrderStatusTimeline } from '../../features/orders/OrderStatusTimeline'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatDateTime, uuid } from '../../utils/format'

function RetryPayment({ order, onPaid }) {
  const toast = useToast()
  const [token, setToken] = useState('tok_visa')
  const [busy, setBusy] = useState(false)
  const key = useRef(uuid())

  const pay = async () => {
    setBusy(true)
    try {
      const updated = await orderApi.pay(order.id, { paymentToken: token, idempotencyKey: key.current })
      toast.success('Payment received. Thank you!')
      onPaid(updated)
    } catch (error) {
      toast.error(error.message)
      if (error.data?.data) onPaid(error.data.data)
    } finally {
      // Each attempt gets its own key; a repeated click during an attempt is blocked by `busy`.
      key.current = uuid()
      setBusy(false)
    }
  }

  return (
    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
      <p className="text-sm font-medium text-amber-900">This order has not been paid yet.</p>
      <div className="mt-3 flex flex-col gap-2 sm:flex-row">
        <label htmlFor="retry-card" className="sr-only">
          Test card
        </label>
        <select id="retry-card" value={token} onChange={(e) => setToken(e.target.value)} className="flex-1 rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
          {TEST_CARDS.map((card) => (
            <option key={card.token} value={card.token}>
              {card.label} — {card.outcome}
            </option>
          ))}
        </select>
        <Button onClick={pay} loading={busy}>
          Pay now
        </Button>
      </div>
    </div>
  )
}

export default function OrderDetailPage() {
  const { orderId } = useParams()
  const location = useLocation()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const { data: order, loading, error, reload, setData } = useAsync(() => orderApi.get(orderId), [orderId])
  useDocumentTitle(order ? `Order ${order.order_number}` : 'Order')

  if (loading) return <PageSpinner />
  if (error) return <ErrorState error={error} onRetry={reload} />

  const cancel = () =>
    confirm({
      title: 'Cancel this order?',
      message: 'Your items will be released and any payment will be refunded.',
      confirmLabel: 'Cancel order',
      onConfirm: async () => {
        try {
          setData(await orderApi.cancel(order.id))
          toast.success('Your order has been cancelled.')
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  const delivered = order.status === 'delivered'

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Link to="/account/orders" className="text-sm text-brand-600 hover:underline">
            ← All orders
          </Link>
          <h2 className="mt-1 text-xl font-bold text-slate-900">Order {order.order_number}</h2>
          <p className="text-sm text-slate-500">Placed {formatDateTime(order.created_at)}</p>
        </div>
        <div className="flex items-center gap-2">
          <StatusBadge value={order.status} tones={ORDER_STATUS_TONES} />
          <StatusBadge value={order.payment_status} tones={PAYMENT_STATUS_TONES} label={`Payment ${order.payment_status}`} />
        </div>
      </div>

      {location.state?.paymentFailed && order.can_pay && <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">Your payment did not go through. You can try again below.</p>}
      {order.can_pay && <RetryPayment order={order} onPaid={setData} />}

      <Card>
        <OrderStatusTimeline order={order} />
        {order.tracking_number && (
          <p className="mt-4 text-sm text-slate-600">
            Tracking number: <span className="font-mono font-medium text-slate-900">{order.tracking_number}</span>
          </p>
        )}
      </Card>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card title="Items" bodyClassName="px-5">
          <OrderItemsTable
            order={order}
            renderAction={(item) =>
              delivered &&
              item.product_slug && (
                <Link to={`/products/${item.product_slug}#reviews`} className="mt-1 inline-block text-xs font-medium text-brand-600 hover:underline">
                  Write a review
                </Link>
              )
            }
          />
        </Card>
        <div className="space-y-6">
          <Card title="Summary">
            <OrderTotals order={order} />
          </Card>
          <Card title="Delivery">
            <div className="space-y-4">
              <AddressBlock title="Shipping address" address={order.shipping_address} />
              <AddressBlock title="Billing address" address={order.billing_address} />
              <div className="text-sm">
                <h3 className="font-semibold text-slate-900">Payment method</h3>
                <p className="mt-1 text-slate-600">{order.payment_method_label}</p>
              </div>
              {order.notes && (
                <div className="text-sm">
                  <h3 className="font-semibold text-slate-900">Notes</h3>
                  <p className="mt-1 text-slate-600">{order.notes}</p>
                </div>
              )}
            </div>
          </Card>
          {order.can_cancel && (
            <Button variant="secondary" block onClick={cancel}>
              Cancel order
            </Button>
          )}
        </div>
      </div>
      {dialog}
    </div>
  )
}
