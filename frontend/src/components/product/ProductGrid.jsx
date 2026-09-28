import { ProductCardSkeleton } from '../common/Skeleton'
import { ProductCard } from './ProductCard'

export function ProductGrid({ products, loading, skeletonCount = 8, columns = 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' }) {
  if (loading && !products?.length) {
    return (
      <div className={`grid grid-cols-2 gap-4 sm:gap-6 ${columns}`} role="status" aria-label="Loading products">
        {Array.from({ length: skeletonCount }).map((_, i) => (
          <ProductCardSkeleton key={i} />
        ))}
      </div>
    )
  }

  return (
    <div className={`grid grid-cols-2 gap-4 sm:gap-6 ${columns} ${loading ? 'opacity-60 transition-opacity' : ''}`} aria-busy={loading || undefined}>
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  )
}
