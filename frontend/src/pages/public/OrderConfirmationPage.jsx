import { useParams } from 'react-router-dom'
import { orderApi } from '../../api/orderApi'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { PageSpinner } from '../../components/common/Spinner'
import { ErrorState } from '../../components/common/States'
import { PAYMENT_STATUS_TONES } from '../../constants/statuses'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatPrice } from '../../utils/format'

export default function OrderConfirmationPage() {
  useDocumentTitle('Order confirmed')
  const { orderId } = useParams()
  const { data: order, loading, error, reload } = useAsync(() => orderApi.get(orderId), [orderId])

  if (loading) return <PageSpinner />
  if (error) {
    return (
      <div className="container-page py-12">
        <ErrorState error={error} onRetry={reload} />
      </div>
    )
  }

  return (
    <div className="container-page max-w-2xl py-12">
      <div className="card p-8 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
          <Icon name="check" className="h-7 w-7" strokeWidth={2.5} />
        </div>
        <h1 className="mt-4 text-2xl font-bold text-slate-900">Thank you for your order!</h1>
        <p className="mt-2 text-slate-600">
          Order <span className="font-semibold text-slate-900">{order.order_number}</span> has been received. A confirmation email is on its way to {order.customer_email}.
        </p>

        <dl className="mx-auto mt-6 grid max-w-md grid-cols-2 gap-4 rounded-xl bg-slate-50 p-4 text-left text-sm">
          <div>
            <dt className="text-slate-500">Total</dt>
            <dd className="font-semibold text-slate-900">{formatPrice(order.grand_total, order.currency)}</dd>
          </div>
          <div>
            <dt className="text-slate-500">Payment</dt>
            <dd>
              <StatusBadge value={order.payment_status} tones={PAYMENT_STATUS_TONES} />
            </dd>
          </div>
          <div>
            <dt className="text-slate-500">Delivery</dt>
            <dd className="font-medium text-slate-900">{order.shipping_method_label}</dd>
          </div>
          <div>
            <dt className="text-slate-500">Items</dt>
            <dd className="font-medium text-slate-900">{order.items?.reduce((n, item) => n + item.quantity, 0)}</dd>
          </div>
        </dl>

        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <Button to={`/account/orders/${order.id}`}>View order details</Button>
          <Button to="/products" variant="secondary">
            Continue shopping
          </Button>
        </div>
      </div>
    </div>
  )
}
