import { useState } from 'react'
import { Link } from 'react-router-dom'
import { accountApi } from '../../api/accountApi'
import { productApi } from '../../api/productApi'
import { Pagination } from '../../components/common/Pagination'
import { Rating } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { EmptyState, ErrorState } from '../../components/common/States'
import { useAuth } from '../../contexts/AuthContext'
import { useAsync } from '../../hooks/useAsync'
import { formatDate } from '../../utils/format'
import { ReviewForm } from './ReviewForm'

export function ReviewsSection({ product }) {
  const { isAuthenticated } = useAuth()
  const [page, setPage] = useState(1)
  const reviews = useAsync(() => productApi.reviews(product.slug, { page, per_page: 5 }), [product.slug, page])
  const eligibility = useAsync(() => (isAuthenticated ? accountApi.reviewEligibility(product.slug) : Promise.resolve(null)), [product.slug, isAuthenticated])

  const meta = reviews.data?.meta
  const distribution = meta?.distribution ?? {}
  const total = meta?.rating_count ?? 0

  return (
    <section aria-labelledby="reviews-heading" className="grid gap-10 lg:grid-cols-[300px_1fr]">
      <div className="space-y-6">
        <div>
          <h2 id="reviews-heading" className="text-2xl font-bold tracking-tight text-slate-900">
            Customer reviews
          </h2>
          <div className="mt-3 flex items-center gap-3">
            <Rating value={product.rating_average} size="h-5 w-5" />
            <p className="text-sm text-slate-600">
              {Number(product.rating_average).toFixed(1)} out of 5 · {product.rating_count} review{product.rating_count === 1 ? '' : 's'}
            </p>
          </div>
        </div>

        {total > 0 && (
          <dl className="space-y-2">
            {[5, 4, 3, 2, 1].map((star) => {
              const count = distribution[star] ?? 0
              const percent = total ? Math.round((count / total) * 100) : 0
              return (
                <div key={star} className="flex items-center gap-3 text-sm">
                  <dt className="w-12 text-slate-600">{star} star</dt>
                  <dd className="flex flex-1 items-center gap-3">
                    <span className="h-2 flex-1 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                      <span className="block h-full rounded-full bg-amber-400" style={{ width: `${percent}%` }} />
                    </span>
                    <span className="w-10 text-right text-slate-500">{percent}%</span>
                  </dd>
                </div>
              )
            })}
          </dl>
        )}

        {!isAuthenticated && (
          <p className="text-sm text-slate-600">
            <Link to={`/login?redirect=/products/${product.slug}`} className="font-medium text-brand-600 hover:underline">
              Sign in
            </Link>{' '}
            to review this product.
          </p>
        )}
        {eligibility.data?.can_review && <ReviewForm productSlug={product.slug} onSubmitted={() => eligibility.reload()} />}
        {eligibility.data && !eligibility.data.can_review && <p className="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-600">{eligibility.data.reason}</p>}
      </div>

      <div>
        {reviews.error ? (
          <ErrorState error={reviews.error} onRetry={reviews.reload} />
        ) : reviews.loading && !reviews.data ? (
          <div className="space-y-6">
            {[1, 2, 3].map((i) => (
              <div key={i} className="space-y-2">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-2/3" />
              </div>
            ))}
          </div>
        ) : reviews.data?.items.length === 0 ? (
          <EmptyState icon="chat" title="No reviews yet" description="Customers who have received this product can share their experience." />
        ) : (
          <>
            <ul className="divide-y divide-slate-200">
              {reviews.data.items.map((review) => (
                <li key={review.id} className="py-6 first:pt-0">
                  <div className="flex items-center justify-between gap-4">
                    <Rating value={review.rating} />
                    <time dateTime={review.created_at} className="text-xs text-slate-500">
                      {formatDate(review.created_at)}
                    </time>
                  </div>
                  {review.title && <h3 className="mt-2 font-semibold text-slate-900">{review.title}</h3>}
                  <p className="mt-1 text-sm whitespace-pre-line text-slate-600">{review.body}</p>
                  <p className="mt-2 text-xs font-medium text-slate-500">— {review.author}</p>
                </li>
              ))}
            </ul>
            <Pagination meta={meta} onChange={setPage} />
          </>
        )}
      </div>
    </section>
  )
}
