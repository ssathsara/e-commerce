import { useState } from 'react'
import { Button } from '../../components/common/Button'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'

/** Coupon entry. Validation and discount calculation happen on the server. */
export function CouponForm({ coupon }) {
  const { applyCoupon, removeCoupon } = useCart()
  const toast = useToast()
  const [code, setCode] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const apply = async (event) => {
    event.preventDefault()
    if (!code.trim()) return
    setBusy(true)
    setError(null)
    try {
      await applyCoupon(code.trim())
      setCode('')
      toast.success('Coupon applied.')
    } catch (e) {
      setError(e.fieldError?.('coupon_code') ?? e.message)
    } finally {
      setBusy(false)
    }
  }

  const remove = async () => {
    setBusy(true)
    try {
      await removeCoupon()
    } catch (e) {
      toast.error(e.message)
    } finally {
      setBusy(false)
    }
  }

  if (coupon) {
    return (
      <div className={`rounded-lg border px-3 py-2.5 text-sm ${coupon.is_applied ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
        <div className="flex items-center justify-between gap-3">
          <p>
            <span className="font-mono font-semibold">{coupon.code}</span>
            <span className="ml-2 text-slate-600">{coupon.is_applied ? 'applied' : 'not applicable'}</span>
          </p>
          <Button variant="link" className="text-xs" onClick={remove} disabled={busy}>
            Remove
          </Button>
        </div>
        {coupon.error && <p className="mt-1 text-xs text-amber-800">{coupon.error}</p>}
      </div>
    )
  }

  return (
    <form onSubmit={apply} noValidate>
      <label htmlFor="coupon" className="mb-1.5 block text-sm font-medium text-slate-700">
        Discount code
      </label>
      <div className="flex gap-2">
        <input
          id="coupon"
          value={code}
          onChange={(e) => setCode(e.target.value.toUpperCase())}
          placeholder="e.g. WELCOME10"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? 'coupon-error' : undefined}
          className="block w-full rounded-lg border-slate-300 text-sm uppercase focus:border-brand-500 focus:ring-brand-500"
        />
        <Button type="submit" variant="secondary" loading={busy} disabled={!code.trim()}>
          Apply
        </Button>
      </div>
      {error && (
        <p id="coupon-error" className="mt-1 text-xs font-medium text-red-600">
          {error}
        </p>
      )}
    </form>
  )
}
