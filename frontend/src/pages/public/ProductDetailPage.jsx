import { Link, useParams } from 'react-router-dom'
import { productApi } from '../../api/productApi'
import { Breadcrumbs } from '../../components/common/Breadcrumbs'
import { Rating } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { ErrorState } from '../../components/common/States'
import { ProductGrid } from '../../components/product/ProductGrid'
import { AddToCartPanel } from '../../features/products/AddToCartPanel'
import { ProductGallery } from '../../features/products/ProductGallery'
import { ReviewsSection } from '../../features/reviews/ReviewsSection'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'

function DetailSkeleton() {
  return (
    <div className="grid gap-10 lg:grid-cols-2" role="status" aria-label="Loading product">
      <Skeleton className="aspect-square w-full rounded-xl" />
      <div className="space-y-4">
        <Skeleton className="h-4 w-24" />
        <Skeleton className="h-9 w-3/4" />
        <Skeleton className="h-4 w-40" />
        <Skeleton className="h-8 w-32" />
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-11 w-60" />
      </div>
    </div>
  )
}

export default function ProductDetailPage() {
  const { slug } = useParams()
  const { data: product, loading, error, reload } = useAsync(() => productApi.get(slug), [slug])
  const related = useAsync(() => productApi.related(slug), [slug])

  useDocumentTitle(product?.seo?.title ?? product?.name)

  if (error) {
    return (
      <div className="container-page py-12">
        <ErrorState error={error} onRetry={reload} />
      </div>
    )
  }

  const crumbs = [{ label: 'Home', to: '/' }, { label: 'Products', to: '/products' }]
  if (product?.category?.parent) crumbs.push({ label: product.category.parent.name, to: `/categories/${product.category.parent.slug}` })
  if (product?.category) crumbs.push({ label: product.category.name, to: `/categories/${product.category.slug}` })
  if (product) crumbs.push({ label: product.name })

  return (
    <div className="container-page py-8">
      <Breadcrumbs items={crumbs} className="mb-6" />

      {loading || !product ? (
        <DetailSkeleton />
      ) : (
        <>
          <div className="grid gap-10 lg:grid-cols-2">
            <ProductGallery images={product.images} name={product.name} />

            <div>
              {product.brand && (
                <Link to={`/brands/${product.brand.slug}`} className="text-sm font-semibold tracking-wide text-brand-600 uppercase hover:underline">
                  {product.brand.name}
                </Link>
              )}
              <h1 className="mt-1 text-3xl font-bold tracking-tight text-slate-900">{product.name}</h1>
              <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                <span>SKU: {product.sku}</span>
                {product.category && <span>Category: {product.category.name}</span>}
                {product.rating_count > 0 && (
                  <a href="#reviews" className="hover:underline">
                    <Rating value={product.rating_average} count={product.rating_count} />
                  </a>
                )}
              </div>
              {product.short_description && <p className="mt-4 text-slate-600">{product.short_description}</p>}

              <div className="mt-6 border-t border-slate-200 pt-6">
                <AddToCartPanel key={product.id} product={product} />
              </div>
            </div>
          </div>

          <div className="mt-14 grid gap-10 lg:grid-cols-[2fr_1fr]">
            <section aria-labelledby="description-heading">
              <h2 id="description-heading" className="text-xl font-bold text-slate-900">
                Description
              </h2>
              <div className="prose-plain mt-3 text-sm leading-relaxed whitespace-pre-line text-slate-600">{product.description}</div>
            </section>
            <section aria-labelledby="specs-heading" className="card p-5">
              <h2 id="specs-heading" className="text-base font-semibold text-slate-900">
                Specifications
              </h2>
              <dl className="mt-3 divide-y divide-slate-100 text-sm">
                {[
                  ['SKU', product.sku],
                  ['Brand', product.brand?.name],
                  ['Weight', product.weight ? `${Number(product.weight)} kg` : null],
                  ['Dimensions', product.dimensions?.length ? `${Number(product.dimensions.length)} × ${Number(product.dimensions.width)} × ${Number(product.dimensions.height)} cm` : null],
                ]
                  .filter(([, value]) => value)
                  .map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4 py-2">
                      <dt className="text-slate-500">{label}</dt>
                      <dd className="text-right font-medium text-slate-800">{value}</dd>
                    </div>
                  ))}
              </dl>
            </section>
          </div>

          <div id="reviews" className="mt-14 scroll-mt-24 border-t border-slate-200 pt-10">
            <ReviewsSection product={product} />
          </div>

          {related.data?.length > 0 && (
            <section className="mt-14 border-t border-slate-200 pt-10" aria-labelledby="related-heading">
              <h2 id="related-heading" className="mb-6 text-2xl font-bold tracking-tight text-slate-900">
                You may also like
              </h2>
              <ProductGrid products={related.data.slice(0, 4)} />
            </section>
          )}
        </>
      )}
    </div>
  )
}
