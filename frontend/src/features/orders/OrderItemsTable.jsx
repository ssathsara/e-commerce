import { Link } from 'react-router-dom'
import { ProductImage } from '../../components/common/Misc'
import { formatPrice } from '../../utils/format'

/** Order lines are snapshots taken at purchase time. */
export function OrderItemsTable({ order, linkProducts = true, renderAction }) {
  return (
    <ul className="divide-y divide-slate-100">
      {order.items.map((item) => (
        <li key={item.id} className="flex items-center gap-4 py-4">
          <ProductImage src={item.image?.url} alt="" className="h-16 w-16 shrink-0 rounded-lg" />
          <div className="min-w-0 flex-1 text-sm">
            {linkProducts && item.product_slug ? (
              <Link to={`/products/${item.product_slug}`} className="font-medium text-slate-900 hover:text-brand-600">
                {item.product_name}
              </Link>
            ) : (
              <p className="font-medium text-slate-900">{item.product_name}</p>
            )}
            <p className="text-slate-500">
              {item.variant_name ? `${item.variant_name} · ` : ''}SKU {item.sku}
            </p>
            <p className="text-slate-500">
              {item.quantity} × {formatPrice(item.unit_price, order.currency)}
            </p>
            {renderAction?.(item)}
          </div>
          <p className="text-sm font-semibold text-slate-900">{formatPrice(item.line_total, order.currency)}</p>
        </li>
      ))}
    </ul>
  )
}

export function OrderTotals({ order }) {
  const rows = [
    ['Subtotal', order.subtotal],
    order.discount_total > 0 ? [`Discount${order.coupon_code ? ` (${order.coupon_code})` : ''}`, -order.discount_total] : null,
    [`Shipping (${order.shipping_method_label})`, order.shipping_total],
    ['Tax', order.tax_total],
  ].filter(Boolean)

  return (
    <dl className="space-y-2 text-sm">
      {rows.map(([label, value]) => (
        <div key={label} className="flex justify-between gap-4">
          <dt className="text-slate-600">{label}</dt>
          <dd className={value < 0 ? 'text-emerald-700' : 'text-slate-900'}>{value < 0 ? `−${formatPrice(-value, order.currency)}` : formatPrice(value, order.currency)}</dd>
        </div>
      ))}
      <div className="flex justify-between gap-4 border-t border-slate-200 pt-2 text-base font-semibold">
        <dt>Total</dt>
        <dd>{formatPrice(order.grand_total, order.currency)}</dd>
      </div>
    </dl>
  )
}

export function AddressBlock({ title, address }) {
  if (!address) return null
  return (
    <div className="text-sm">
      <h3 className="font-semibold text-slate-900">{title}</h3>
      <address className="mt-1 whitespace-pre-line text-slate-600 not-italic">
        {[
          `${address.first_name} ${address.last_name}`,
          address.company,
          address.line1,
          address.line2,
          [address.city, address.state, address.postal_code].filter(Boolean).join(', '),
          address.country,
          address.phone,
        ]
          .filter(Boolean)
          .join('\n')}
      </address>
    </div>
  )
}
