import { useEffect } from 'react'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { PageSpinner } from '../../components/common/Spinner'
import { EmptyState, ErrorState } from '../../components/common/States'
import { useAuth } from '../../contexts/AuthContext'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { CartLineItem } from '../../features/cart/CartLineItem'
import { CouponForm } from '../../features/cart/CouponForm'
import { OrderSummary } from '../../features/cart/OrderSummary'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'

export default function CartPage() {
  useDocumentTitle('Shopping cart')
  const { cart, loading, error, refresh, clear } = useCart()
  const { isAuthenticated } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()

  // Always show current prices/stock (and the default shipping estimate) when opening the cart.
  useEffect(() => {
    refresh()
  }, [refresh])

  if (loading && !cart) return <PageSpinner label="Loading your cart" />

  if (error && !cart) {
    return (
      <div className="container-page py-12">
        <ErrorState error={error} onRetry={refresh} />
      </div>
    )
  }

  if (!cart?.items?.length) {
    return (
      <div className="container-page py-12">
        <div className="card">
          <EmptyState icon="cart" title="Your cart is empty" description="Browse our catalogue and add something you love." action={<Button to="/products">Start shopping</Button>} />
        </div>
      </div>
    )
  }

  const clearCart = () =>
    confirm({
      title: 'Clear your cart?',
      message: 'All items will be removed from your cart.',
      confirmLabel: 'Clear cart',
      onConfirm: async () => {
        try {
          await clear()
          toast.success('Your cart has been cleared.')
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  return (
    <div className="container-page py-8">
      <div className="mb-6 flex items-end justify-between gap-4">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900">Shopping cart</h1>
        <Button variant="ghost" size="sm" onClick={clearCart}>
          Clear cart
        </Button>
      </div>

      <div className="grid gap-8 lg:grid-cols-[1fr_380px]">
        <section aria-label="Cart items" className="card px-5">
          <ul className="divide-y divide-slate-200">
            {cart.items.map((item) => (
              <CartLineItem key={item.id} item={item} currency={cart.currency} />
            ))}
          </ul>
        </section>

        <aside className="space-y-4 lg:sticky lg:top-32 lg:self-start">
          <OrderSummary totals={cart.totals} currency={cart.currency} couponCode={cart.coupon?.is_applied ? cart.coupon.code : null} taxRate={cart.tax_rate} shippingLabel="Shipping (estimate)">
            <CouponForm coupon={cart.coupon} />
            {cart.has_issues && (
              <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                Please resolve the highlighted items before checking out.
              </p>
            )}
            {isAuthenticated ? (
              <Button to={cart.has_issues ? undefined : '/checkout'} size="lg" block disabled={cart.has_issues}>
                Proceed to checkout
              </Button>
            ) : (
              <>
                <Button to="/login?redirect=/checkout" size="lg" block>
                  Sign in to check out
                </Button>
                <p className="text-center text-xs text-slate-500">Your cart will be saved to your account when you sign in.</p>
              </>
            )}
            <Button to="/products" variant="ghost" block>
              Continue shopping
            </Button>
          </OrderSummary>
        </aside>
      </div>
      {dialog}
    </div>
  )
}
