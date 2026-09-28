import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Icon } from '../../components/common/Icon'
import { ProductImage, QuantitySelector } from '../../components/common/Misc'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { formatPrice } from '../../utils/format'

export function CartLineItem({ item, currency }) {
  const { updateItem, removeItem } = useCart()
  const toast = useToast()
  const [busy, setBusy] = useState(false)
  const name = item.product?.name ?? 'Unavailable product'

  const run = async (operation) => {
    setBusy(true)
    try {
      await operation()
    } catch (error) {
      toast.error(error.message)
    } finally {
      setBusy(false)
    }
  }

  const maxQuantity = Math.max(1, Math.min(item.available_stock || 1, 99))

  return (
    <li className={`flex gap-4 py-5 ${busy ? 'opacity-60' : ''}`}>
      <Link to={item.product ? `/products/${item.product.slug}` : '#'} className="shrink-0" tabIndex={-1} aria-hidden="true">
        <ProductImage src={item.product?.image?.url} alt="" className="h-24 w-24 rounded-lg" />
      </Link>

      <div className="flex min-w-0 flex-1 flex-col">
        <div className="flex justify-between gap-4">
          <div className="min-w-0">
            <h3 className="font-medium text-slate-900">
              {item.product ? (
                <Link to={`/products/${item.product.slug}`} className="hover:text-brand-600">
                  {name}
                </Link>
              ) : (
                name
              )}
            </h3>
            {item.variant && <p className="text-sm text-slate-500">{item.variant.name}</p>}
            <p className="mt-0.5 text-sm text-slate-500">{formatPrice(item.unit_price, currency)} each</p>
          </div>
          <p className="shrink-0 font-semibold text-slate-900">{formatPrice(item.line_total, currency)}</p>
        </div>

        {item.issue && (
          <p className="mt-2 flex items-center gap-1.5 text-sm font-medium text-amber-700" role="alert">
            <Icon name="alert" className="h-4 w-4" /> {item.issue}
          </p>
        )}

        <div className="mt-auto flex items-center justify-between gap-3 pt-3">
          {item.product ? (
            <QuantitySelector
              size="sm"
              label={`Quantity for ${name}`}
              value={item.quantity}
              max={Math.max(maxQuantity, item.quantity)}
              disabled={busy}
              onChange={(quantity) => quantity !== item.quantity && run(() => updateItem(item.id, quantity))}
            />
          ) : (
            <span />
          )}
          <button type="button" onClick={() => run(() => removeItem(item.id))} disabled={busy} className="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-red-600" aria-label={`Remove ${name} from cart`}>
            <Icon name="trash" className="h-4 w-4" /> Remove
          </button>
        </div>
      </div>
    </li>
  )
}
