import { formatPrice } from '../../utils/format'

/** Server-computed totals. The client only displays these values. */
export function OrderSummary({ totals, currency, couponCode, taxRate, shippingLabel = 'Shipping', children }) {
  const rows = [
    ['Subtotal', formatPrice(totals.subtotal, currency)],
    totals.discount > 0 ? [`Discount${couponCode ? ` (${couponCode})` : ''}`, `−${formatPrice(totals.discount, currency)}`, 'text-emerald-700'] : null,
    [shippingLabel, totals.shipping === 0 && totals.subtotal > 0 ? 'Free' : formatPrice(totals.shipping, currency)],
    [`Tax${taxRate ? ` (${taxRate}%)` : ''}`, formatPrice(totals.tax, currency)],
  ].filter(Boolean)

  return (
    <div className="card p-5">
      <h2 className="text-base font-semibold text-slate-900">Order summary</h2>
      <dl className="mt-4 space-y-3 text-sm">
        {rows.map(([label, value, tone]) => (
          <div key={label} className="flex justify-between gap-4">
            <dt className="text-slate-600">{label}</dt>
            <dd className={`font-medium ${tone ?? 'text-slate-900'}`}>{value}</dd>
          </div>
        ))}
        <div className="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base">
          <dt className="font-semibold text-slate-900">Total</dt>
          <dd className="font-bold text-slate-900">{formatPrice(totals.total, currency)}</dd>
        </div>
      </dl>
      {children && <div className="mt-5 space-y-3">{children}</div>}
    </div>
  )
}
