import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { accountApi } from '../../api/accountApi'
import { cartApi } from '../../api/cartApi'
import { orderApi } from '../../api/orderApi'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { Modal } from '../../components/common/Modal'
import { ProductImage } from '../../components/common/Misc'
import { PageSpinner } from '../../components/common/Spinner'
import { EmptyState, ErrorState } from '../../components/common/States'
import { Checkbox, Textarea } from '../../components/forms/Fields'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { AddressForm } from '../../features/account/AddressForm'
import { CouponForm } from '../../features/cart/CouponForm'
import { OrderSummary } from '../../features/cart/OrderSummary'
import { AddressPicker } from '../../features/checkout/AddressPicker'
import { PaymentMethodPicker } from '../../features/checkout/PaymentMethodPicker'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatAddress, formatPrice, uuid } from '../../utils/format'

const STEPS = ['Addresses', 'Delivery', 'Payment', 'Review']

function StepIndicator({ current, onSelect }) {
  return (
    <ol className="mb-8 flex items-center gap-2 text-sm" aria-label="Checkout progress">
      {STEPS.map((label, index) => {
        const done = index < current
        const active = index === current
        return (
          <li key={label} className="flex flex-1 items-center gap-2">
            <button
              type="button"
              disabled={index > current}
              onClick={() => onSelect(index)}
              aria-current={active ? 'step' : undefined}
              className={`flex items-center gap-2 font-medium ${active ? 'text-brand-700' : done ? 'text-slate-700 hover:text-brand-700' : 'text-slate-400'}`}
            >
              <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs ${active ? 'bg-brand-600 text-white' : done ? 'bg-brand-100 text-brand-700' : 'bg-slate-200 text-slate-500'}`}>
                {done ? <Icon name="check" className="h-4 w-4" strokeWidth={2.5} /> : index + 1}
              </span>
              <span className="hidden sm:inline">{label}</span>
            </button>
            {index < STEPS.length - 1 && <span className="h-px flex-1 bg-slate-200" aria-hidden="true" />}
          </li>
        )
      })}
    </ol>
  )
}

function StepCard({ title, children, footer }) {
  return (
    <section className="card p-5 sm:p-6">
      <h2 className="mb-4 text-lg font-semibold text-slate-900">{title}</h2>
      {children}
      {footer && <div className="mt-6 flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">{footer}</div>}
    </section>
  )
}

export default function CheckoutPage() {
  useDocumentTitle('Checkout')
  const navigate = useNavigate()
  const toast = useToast()
  const { cart, loading: cartLoading, setShippingMethod: priceWithShipping, refresh: refreshCart } = useCart()

  const addresses = useAsync(() => accountApi.addresses(), [])
  const options = useAsync(() => cartApi.checkoutOptions(), [])

  const [step, setStep] = useState(0)
  const [shippingAddressId, setShippingAddressId] = useState(null)
  const [billingSame, setBillingSame] = useState(true)
  const [billingAddressId, setBillingAddressId] = useState(null)
  const [shippingMethod, setShippingMethod] = useState('standard')
  const [paymentMethod, setPaymentMethod] = useState('card')
  const [cardToken, setCardToken] = useState('tok_visa')
  const [notes, setNotes] = useState('')
  const [addressModal, setAddressModal] = useState(false)
  const [placing, setPlacing] = useState(false)

  // One idempotency key per checkout attempt, reused on every retry of the same attempt.
  const idempotencyKey = useRef(uuid())

  // Pre-select default addresses once loaded.
  useEffect(() => {
    const list = addresses.data ?? []
    if (!list.length) return
    setShippingAddressId((current) => current ?? (list.find((a) => a.is_default_shipping) ?? list[0]).id)
    setBillingAddressId((current) => current ?? (list.find((a) => a.is_default_billing) ?? list[0]).id)
  }, [addresses.data])

  // Re-price the cart whenever the shipping method changes (server computes shipping + tax).
  // The method stays selected for every cart call on this page (e.g. applying a coupon).
  useEffect(() => {
    priceWithShipping(shippingMethod).catch(() => {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [shippingMethod])

  // Leaving checkout restores the default shipping estimate for the cart page.
  useEffect(() => () => void priceWithShipping(null).catch(() => {}), [])

  if ((cartLoading && !cart) || addresses.loading || options.loading) return <PageSpinner label="Preparing checkout" />
  if (addresses.error || options.error) {
    return (
      <div className="container-page py-12">
        <ErrorState error={addresses.error ?? options.error} onRetry={() => (addresses.reload(), options.reload())} />
      </div>
    )
  }
  if (!cart?.items?.length) {
    return (
      <div className="container-page py-12">
        <div className="card">
          <EmptyState icon="cart" title="Your cart is empty" action={<Button to="/products">Continue shopping</Button>} />
        </div>
      </div>
    )
  }

  const addressList = addresses.data ?? []
  const shippingAddress = addressList.find((a) => a.id === shippingAddressId)
  const billingAddress = billingSame ? shippingAddress : addressList.find((a) => a.id === billingAddressId)
  const method = options.data.shipping_methods.find((m) => m.code === shippingMethod)
  const canContinueAddresses = Boolean(shippingAddress && billingAddress)

  const saveAddress = async (values) => {
    const created = await accountApi.createAddress(values)
    await addresses.reload()
    setShippingAddressId(created.id)
    setAddressModal(false)
    toast.success('Address saved.')
  }

  const placeOrder = async () => {
    if (placing) return
    setPlacing(true)

    try {
      const result = await orderApi.place({
        idempotency_key: idempotencyKey.current,
        shipping_address_id: shippingAddressId,
        billing_same_as_shipping: billingSame,
        billing_address_id: billingSame ? null : billingAddressId,
        shipping_method: shippingMethod,
        payment_method: paymentMethod,
        payment_token: paymentMethod === 'card' ? cardToken : null,
        notes: notes || null,
      })

      // The order exists now; a new attempt must use a new key.
      idempotencyKey.current = uuid()

      if (result.paymentError) {
        toast.error(result.paymentError)
        navigate(`/account/orders/${result.order.id}`, { state: { paymentFailed: true } })
      } else {
        navigate(`/checkout/complete/${result.order.id}`, { replace: true })
      }

      // Navigate first so this page never flashes an "empty cart" state.
      refreshCart()
    } catch (error) {
      toast.error(error.message)
      // Cart problems (stock changed, coupon invalid) need the customer's attention.
      if (error.isConflict || error.fieldError?.('coupon_code')) await refreshCart()
      if (error.isValidation && (error.fieldError('shipping_address_id') || error.fieldError('billing_address_id'))) setStep(0)
    } finally {
      setPlacing(false)
    }
  }

  return (
    <div className="container-page py-8">
      <h1 className="mb-6 text-3xl font-bold tracking-tight text-slate-900">Checkout</h1>
      <StepIndicator current={step} onSelect={setStep} />

      <div className="grid gap-8 lg:grid-cols-[1fr_380px]">
        <div className="space-y-6">
          {step === 0 && (
            <StepCard
              title="Shipping & billing address"
              footer={
                <>
                  <Button variant="ghost" to="/cart">
                    Back to cart
                  </Button>
                  <Button onClick={() => setStep(1)} disabled={!canContinueAddresses}>
                    Continue to delivery
                  </Button>
                </>
              }
            >
              {addressList.length === 0 ? (
                <EmptyState icon="mapPin" title="No saved addresses" description="Add an address to continue." action={<Button onClick={() => setAddressModal(true)}>Add address</Button>} />
              ) : (
                <div className="space-y-6">
                  <div>
                    <h3 className="mb-3 text-sm font-semibold text-slate-700">Ship to</h3>
                    <AddressPicker name="shipping_address" legend="Shipping address" addresses={addressList} value={shippingAddressId} onChange={setShippingAddressId} />
                    <Button variant="link" className="mt-3 text-sm" onClick={() => setAddressModal(true)}>
                      + Add a new address
                    </Button>
                  </div>
                  <div>
                    <Checkbox label="Billing address is the same as shipping" checked={billingSame} onChange={(e) => setBillingSame(e.target.checked)} />
                    {!billingSame && (
                      <div className="mt-4">
                        <h3 className="mb-3 text-sm font-semibold text-slate-700">Bill to</h3>
                        <AddressPicker name="billing_address" legend="Billing address" addresses={addressList} value={billingAddressId} onChange={setBillingAddressId} />
                      </div>
                    )}
                  </div>
                </div>
              )}
            </StepCard>
          )}

          {step === 1 && (
            <StepCard
              title="Delivery method"
              footer={
                <>
                  <Button variant="ghost" onClick={() => setStep(0)}>
                    Back
                  </Button>
                  <Button onClick={() => setStep(2)}>Continue to payment</Button>
                </>
              }
            >
              <fieldset>
                <legend className="sr-only">Shipping method</legend>
                <div className="space-y-3">
                  {options.data.shipping_methods.map((option) => (
                    <label
                      key={option.code}
                      className={`flex cursor-pointer items-center justify-between gap-4 rounded-xl border p-4 text-sm focus-within:ring-2 focus-within:ring-brand-500 ${shippingMethod === option.code ? 'border-brand-600 bg-brand-50/50' : 'border-slate-200 hover:border-slate-300'}`}
                    >
                      <span className="flex items-center gap-3">
                        <input type="radio" name="shipping_method" value={option.code} checked={shippingMethod === option.code} onChange={() => setShippingMethod(option.code)} className="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" />
                        <span>
                          <span className="block font-medium text-slate-900">{option.name}</span>
                          <span className="block text-slate-500">{option.description}</span>
                        </span>
                      </span>
                      <span className="font-semibold text-slate-900">{option.is_free ? 'Free' : formatPrice(option.cost, options.data.currency)}</span>
                    </label>
                  ))}
                </div>
              </fieldset>
              <p className="mt-3 text-xs text-slate-500">Standard shipping is free on orders of {formatPrice(options.data.free_shipping_threshold)} or more.</p>
            </StepCard>
          )}

          {step === 2 && (
            <StepCard
              title="Payment"
              footer={
                <>
                  <Button variant="ghost" onClick={() => setStep(1)}>
                    Back
                  </Button>
                  <Button onClick={() => setStep(3)}>Review order</Button>
                </>
              }
            >
              <div className="space-y-6">
                <CouponForm coupon={cart.coupon} />
                <PaymentMethodPicker methods={options.data.payment_methods} value={paymentMethod} onChange={setPaymentMethod} cardToken={cardToken} onCardTokenChange={setCardToken} />
              </div>
            </StepCard>
          )}

          {step === 3 && (
            <StepCard
              title="Review your order"
              footer={
                <>
                  <Button variant="ghost" onClick={() => setStep(2)} disabled={placing}>
                    Back
                  </Button>
                  <Button size="lg" onClick={placeOrder} loading={placing} disabled={cart.has_issues}>
                    {paymentMethod === 'card' ? `Pay ${formatPrice(cart.totals.total, cart.currency)}` : 'Place order'}
                  </Button>
                </>
              }
            >
              <div className="grid gap-4 text-sm sm:grid-cols-3">
                <div>
                  <h3 className="font-semibold text-slate-700">Shipping to</h3>
                  <p className="mt-1 whitespace-pre-line text-slate-600">
                    {shippingAddress?.first_name} {shippingAddress?.last_name}
                    {'\n'}
                    {formatAddress(shippingAddress)}
                  </p>
                </div>
                <div>
                  <h3 className="font-semibold text-slate-700">Billing</h3>
                  <p className="mt-1 whitespace-pre-line text-slate-600">{billingSame ? 'Same as shipping' : formatAddress(billingAddress)}</p>
                </div>
                <div>
                  <h3 className="font-semibold text-slate-700">Delivery & payment</h3>
                  <p className="mt-1 text-slate-600">{method?.name}</p>
                  <p className="text-slate-600">{options.data.payment_methods.find((m) => m.code === paymentMethod)?.name}</p>
                </div>
              </div>

              <ul className="mt-6 divide-y divide-slate-100 border-y border-slate-100">
                {cart.items.map((item) => (
                  <li key={item.id} className="flex items-center gap-4 py-3">
                    <ProductImage src={item.product?.image?.url} alt="" className="h-14 w-14 rounded-lg" />
                    <div className="min-w-0 flex-1 text-sm">
                      <p className="truncate font-medium text-slate-900">{item.product?.name}</p>
                      <p className="text-slate-500">
                        {item.variant ? `${item.variant.name} · ` : ''}Qty {item.quantity}
                      </p>
                      {item.issue && <p className="font-medium text-amber-700">{item.issue}</p>}
                    </div>
                    <p className="text-sm font-semibold text-slate-900">{formatPrice(item.line_total, cart.currency)}</p>
                  </li>
                ))}
              </ul>

              <Textarea label="Order notes (optional)" className="mt-6" rows={3} maxLength={1000} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Delivery instructions, gift message…" />
              {cart.has_issues && (
                <p className="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                  Some items need attention. Please return to your cart.
                </p>
              )}
            </StepCard>
          )}
        </div>

        <aside className="lg:sticky lg:top-32 lg:self-start">
          <OrderSummary totals={cart.totals} currency={cart.currency} couponCode={cart.coupon?.is_applied ? cart.coupon.code : null} taxRate={cart.tax_rate} shippingLabel={method ? `Shipping (${method.name})` : 'Shipping'}>
            <p className="flex items-center gap-2 text-xs text-slate-500">
              <Icon name="lock" className="h-4 w-4" /> Prices and totals are calculated securely on our servers.
            </p>
          </OrderSummary>
        </aside>
      </div>

      <Modal open={addressModal} onClose={() => setAddressModal(false)} title="Add a new address" size="lg">
        <AddressForm onSave={saveAddress} onCancel={() => setAddressModal(false)} />
      </Modal>
    </div>
  )
}
