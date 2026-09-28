import { useState } from 'react'
import { Link } from 'react-router-dom'
import { accountApi } from '../../api/accountApi'
import { Button } from '../../components/common/Button'
import { Price, ProductImage } from '../../components/common/Misc'
import { ProductCardSkeleton } from '../../components/common/Skeleton'
import { EmptyState, ErrorState } from '../../components/common/States'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { useWishlist } from '../../contexts/WishlistContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'

function WishlistItem({ entry, onRemoved }) {
  const { product } = entry
  const { refresh } = useCart()
  const { remove } = useWishlist()
  const toast = useToast()
  const [busy, setBusy] = useState(null)

  const run = async (action, operation, successMessage) => {
    setBusy(action)
    try {
      await operation()
      remove(product.id)
      toast.success(successMessage)
      onRemoved(product.id)
    } catch (error) {
      toast.error(error.message)
    } finally {
      setBusy(null)
    }
  }

  const moveToCart = () =>
    run(
      'move',
      async () => {
        await accountApi.moveWishlistItemToCart(product.id)
        await refresh()
      },
      `${product.name} moved to your cart.`,
    )

  return (
    <li className="card flex flex-col overflow-hidden">
      <Link to={`/products/${product.slug}`} tabIndex={-1} aria-hidden="true">
        <ProductImage src={product.image?.url} alt="" className="aspect-square w-full" />
      </Link>
      <div className="flex flex-1 flex-col p-4">
        <Link to={`/products/${product.slug}`} className="line-clamp-2 text-sm font-medium text-slate-900 hover:text-brand-600">
          {product.name}
        </Link>
        <Price amount={product.price} compareAt={product.compare_at_price} className="mt-2" />
        {!product.in_stock && <p className="mt-1 text-xs font-medium text-red-600">Out of stock</p>}
        <div className="mt-auto flex flex-col gap-2 pt-4">
          {product.has_variants ? (
            <Button size="sm" to={`/products/${product.slug}`}>
              Choose options
            </Button>
          ) : (
            <Button size="sm" onClick={moveToCart} loading={busy === 'move'} disabled={!product.in_stock || busy !== null}>
              Move to cart
            </Button>
          )}
          <Button size="sm" variant="ghost" onClick={() => run('remove', () => accountApi.removeFromWishlist(product.id), 'Removed from your wishlist.')} loading={busy === 'remove'} disabled={busy !== null}>
            Remove
          </Button>
        </div>
      </div>
    </li>
  )
}

export default function WishlistPage() {
  useDocumentTitle('Wishlist')
  const { data, loading, error, reload, setData } = useAsync(() => accountApi.wishlist(), [])

  if (error) return <ErrorState error={error} onRetry={reload} />

  return (
    <div>
      <h2 className="mb-4 text-lg font-semibold text-slate-900">Wishlist</h2>
      {loading ? (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
          {[1, 2, 3].map((i) => (
            <ProductCardSkeleton key={i} />
          ))}
        </div>
      ) : data.length === 0 ? (
        <div className="card">
          <EmptyState icon="heart" title="Your wishlist is empty" description="Save products you love and come back to them later." action={<Button to="/products">Browse products</Button>} />
        </div>
      ) : (
        <ul className="grid grid-cols-2 gap-4 lg:grid-cols-3">
          {data.map((entry) => (
            <WishlistItem key={entry.id} entry={entry} onRemoved={(productId) => setData((items) => items.filter((i) => i.product.id !== productId))} />
          ))}
        </ul>
      )}
    </div>
  )
}
