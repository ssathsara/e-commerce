import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { Badge } from '../common/Badge'
import { Button } from '../common/Button'
import { Icon } from '../common/Icon'
import { Price, ProductImage, Rating } from '../common/Misc'
import { WishlistButton } from './WishlistButton'

export function ProductCard({ product }) {
  const { addItem } = useCart()
  const toast = useToast()
  const [adding, setAdding] = useState(false)
  const href = `/products/${product.slug}`

  const quickAdd = async () => {
    setAdding(true)
    try {
      await addItem({ productId: product.id, quantity: 1 })
      toast.success(`${product.name} added to your cart.`)
    } catch (error) {
      toast.error(error.message)
    } finally {
      setAdding(false)
    }
  }

  return (
    <article className="group card flex flex-col overflow-hidden transition-shadow hover:shadow-md">
      <div className="relative">
        <Link to={href} tabIndex={-1} aria-hidden="true">
          <ProductImage src={product.image?.url} alt="" className="aspect-square w-full transition-transform duration-300 group-hover:scale-[1.03]" />
        </Link>
        <div className="absolute top-3 left-3 flex flex-col gap-1">
          {product.discount_percentage > 0 && <Badge tone="rose">-{product.discount_percentage}%</Badge>}
          {!product.in_stock && <Badge tone="slate">Out of stock</Badge>}
          {product.in_stock && product.is_low_stock && <Badge tone="amber">Low stock</Badge>}
        </div>
        <WishlistButton productId={product.id} productName={product.name} className="absolute top-3 right-3 rounded-full bg-white/90 p-2 shadow-sm" />
      </div>

      <div className="flex flex-1 flex-col p-4">
        {product.brand && <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">{product.brand.name}</p>}
        <h3 className="mt-1 line-clamp-2 text-sm font-medium text-slate-900">
          <Link to={href} className="hover:text-brand-600 focus-visible:underline">
            {product.name}
          </Link>
        </h3>
        {product.rating_count > 0 && (
          <div className="mt-1.5">
            <Rating value={product.rating_average} count={product.rating_count} size="h-3.5 w-3.5" />
          </div>
        )}
        <div className="mt-auto flex items-end justify-between gap-2 pt-3">
          <Price amount={product.price} compareAt={product.compare_at_price} />
          {product.in_stock &&
            (product.has_variants ? (
              <Button to={href} variant="secondary" size="xs" aria-label={`Choose options for ${product.name}`}>
                Options
              </Button>
            ) : (
              <Button size="xs" onClick={quickAdd} loading={adding} aria-label={`Add ${product.name} to cart`}>
                <Icon name="plus" className="h-3.5 w-3.5" /> Add
              </Button>
            ))}
        </div>
      </div>
    </article>
  )
}
