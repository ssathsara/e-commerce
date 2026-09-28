import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { Price, QuantitySelector } from '../../components/common/Misc'
import { WishlistButton } from '../../components/product/WishlistButton'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'

const MAX_LINE_QUANTITY = 99

/**
 * Variant + quantity selection. The quantity is capped by the selected option's
 * stock for a good UX; the API enforces the same limit authoritatively.
 */
export function AddToCartPanel({ product }) {
  const { addItem } = useCart()
  const toast = useToast()
  const variants = product.variants ?? []
  const firstAvailable = variants.find((v) => v.in_stock) ?? variants[0] ?? null
  const [variantId, setVariantId] = useState(firstAvailable?.id ?? null)
  const [quantity, setQuantity] = useState(1)
  const [adding, setAdding] = useState(false)
  const [added, setAdded] = useState(false)

  const variant = variants.find((v) => v.id === variantId) ?? null
  const price = variant?.price ?? product.price
  const compareAt = variant ? variant.compare_at_price : product.compare_at_price
  const stock = variant ? variant.stock_quantity : product.stock_quantity
  const purchasable = product.status === 'active' && stock > 0 && (variants.length === 0 || variant !== null)
  const maxQuantity = Math.max(1, Math.min(stock, MAX_LINE_QUANTITY))

  const selectVariant = (id) => {
    setVariantId(id)
    setQuantity(1)
    setAdded(false)
  }

  const add = async () => {
    setAdding(true)
    try {
      await addItem({ productId: product.id, variantId: variant?.id ?? null, quantity })
      setAdded(true)
      toast.success(`${product.name}${variant ? ` (${variant.name})` : ''} added to your cart.`)
    } catch (error) {
      toast.error(error.message)
    } finally {
      setAdding(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <Price amount={price} compareAt={compareAt} size="lg" />
        {compareAt > price && <p className="mt-1 text-sm font-medium text-rose-600">You save {Math.round((1 - price / compareAt) * 100)}%</p>}
      </div>

      {variants.length > 0 && (
        <fieldset>
          <legend className="mb-2 text-sm font-medium text-slate-900">Choose an option</legend>
          <div className="flex flex-wrap gap-2">
            {variants.map((v) => (
              <label
                key={v.id}
                className={`cursor-pointer rounded-lg border px-3 py-2 text-sm transition-colors focus-within:ring-2 focus-within:ring-brand-500 ${
                  v.id === variantId ? 'border-brand-600 bg-brand-50 font-medium text-brand-700' : 'border-slate-300 text-slate-700 hover:border-slate-400'
                } ${!v.in_stock ? 'cursor-not-allowed opacity-50 line-through' : ''}`}
              >
                <input type="radio" name="variant" value={v.id} checked={v.id === variantId} disabled={!v.in_stock} onChange={() => selectVariant(v.id)} className="sr-only" />
                {v.name}
                {!v.in_stock && <span className="sr-only"> (out of stock)</span>}
              </label>
            ))}
          </div>
        </fieldset>
      )}

      <p className={`flex items-center gap-2 text-sm font-medium ${stock > 0 ? (stock <= 5 ? 'text-amber-700' : 'text-emerald-700') : 'text-red-600'}`}>
        <span className={`h-2 w-2 rounded-full ${stock > 0 ? (stock <= 5 ? 'bg-amber-500' : 'bg-emerald-500') : 'bg-red-500'}`} aria-hidden="true" />
        {stock > 0 ? (stock <= 5 ? `Only ${stock} left in stock` : 'In stock') : 'Out of stock'}
      </p>

      <div className="flex flex-wrap items-center gap-3">
        <QuantitySelector value={quantity} onChange={setQuantity} max={maxQuantity} disabled={!purchasable} />
        <Button size="lg" className="flex-1 sm:flex-none" onClick={add} loading={adding} disabled={!purchasable}>
          <Icon name="cart" className="h-5 w-5" /> {purchasable ? 'Add to cart' : 'Unavailable'}
        </Button>
        <WishlistButton productId={product.id} productName={product.name} withLabel className="rounded-lg border border-slate-300 px-3 py-2.5 hover:bg-slate-50" />
      </div>

      {added && (
        <p className="text-sm text-slate-600" role="status">
          Added to cart.{' '}
          <Link to="/cart" className="font-medium text-brand-600 hover:underline">
            View cart
          </Link>
        </p>
      )}
    </div>
  )
}
