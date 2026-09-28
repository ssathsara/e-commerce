import { Link } from 'react-router-dom'
import { accountApi } from '../../api/accountApi'
import { authApi } from '../../api/authApi'
import { orderApi } from '../../api/orderApi'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { Card } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { EmptyState, ErrorState } from '../../components/common/States'
import { ORDER_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatDate, formatPrice } from '../../utils/format'

function VerificationBanner() {
  const toast = useToast()

  const resend = async () => {
    try {
      toast.success(await authApi.resendVerification())
    } catch (error) {
      toast.error(error.message)
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between" role="status">
      <p>Please verify your email address. We sent you a link when you signed up.</p>
      <Button size="sm" variant="secondary" onClick={resend}>
        Resend link
      </Button>
    </div>
  )
}

export default function AccountOverviewPage() {
  useDocumentTitle('My account')
  const { user } = useAuth()
  const profile = useAsync(() => accountApi.profile(), [])
  const orders = useAsync(() => orderApi.list({ per_page: 3 }), [])

  const stats = profile.data?.stats
  const tiles = [
    { label: 'Orders', value: stats?.orders, icon: 'receipt', to: '/account/orders' },
    { label: 'Wishlist items', value: stats?.wishlist, icon: 'heart', to: '/account/wishlist' },
    { label: 'Saved addresses', value: stats?.addresses, icon: 'mapPin', to: '/account/addresses' },
  ]

  return (
    <div className="space-y-6">
      {user && !user.email_verified && <VerificationBanner />}

      <div className="grid gap-4 sm:grid-cols-3">
        {tiles.map((tile) => (
          <Link key={tile.label} to={tile.to} className="card flex items-center gap-4 p-5 transition-shadow hover:shadow-md">
            <span className="rounded-lg bg-brand-50 p-2.5 text-brand-600">
              <Icon name={tile.icon} className="h-5 w-5" />
            </span>
            <span>
              {profile.loading ? <Skeleton className="h-6 w-8" /> : <span className="block text-2xl font-bold text-slate-900">{tile.value ?? 0}</span>}
              <span className="text-sm text-slate-500">{tile.label}</span>
            </span>
          </Link>
        ))}
      </div>

      <Card title="Recent orders" actions={<Button to="/account/orders" variant="link" className="text-sm">View all</Button>} bodyClassName="p-0">
        {orders.error ? (
          <ErrorState error={orders.error} onRetry={orders.reload} />
        ) : orders.loading ? (
          <div className="space-y-3 p-5">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-12 w-full" />
            ))}
          </div>
        ) : orders.data.items.length === 0 ? (
          <EmptyState icon="receipt" title="No orders yet" description="When you place an order it will appear here." action={<Button to="/products">Start shopping</Button>} />
        ) : (
          <ul className="divide-y divide-slate-100">
            {orders.data.items.map((order) => (
              <li key={order.id}>
                <Link to={`/account/orders/${order.id}`} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50">
                  <div>
                    <p className="font-medium text-slate-900">{order.order_number}</p>
                    <p className="text-sm text-slate-500">
                      {formatDate(order.created_at)} · {order.items_count} item{order.items_count === 1 ? '' : 's'}
                    </p>
                  </div>
                  <div className="flex items-center gap-4">
                    <StatusBadge value={order.status} tones={ORDER_STATUS_TONES} />
                    <span className="font-semibold text-slate-900">{formatPrice(order.grand_total, order.currency)}</span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}
