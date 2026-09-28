import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { productApi } from '../../api/productApi'
import { Breadcrumbs } from '../../components/common/Breadcrumbs'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { Modal } from '../../components/common/Modal'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState, ErrorState } from '../../components/common/States'
import { ProductGrid } from '../../components/product/ProductGrid'
import { PRODUCT_SORTS } from '../../constants/statuses'
import { ProductFilters } from '../../features/products/ProductFilters'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useQueryParams } from '../../hooks/useQueryParams'

const FILTER_KEYS = ['brand', 'min_price', 'max_price', 'in_stock', 'rating', 'featured']

export default function ProductListPage() {
  const { categorySlug, brandSlug } = useParams()
  const [params, setParams, resetParams] = useQueryParams({ sort: 'newest' })
  const [filtersOpen, setFiltersOpen] = useState(false)

  const { data: category } = useAsync(() => (categorySlug ? productApi.category(categorySlug) : Promise.resolve(null)), [categorySlug])
  const { data: brand } = useAsync(() => (brandSlug ? productApi.brand(brandSlug) : Promise.resolve(null)), [brandSlug])

  const query = {
    ...params,
    category: categorySlug ?? params.category,
    brand: brandSlug ?? params.brand,
    page: params.page ?? 1,
    per_page: 12,
  }

  const { data, loading, error, reload } = useAsync(() => productApi.list(query), [JSON.stringify(query)])

  const title = category?.name ?? brand?.name ?? (params.search ? `Results for “${params.search}”` : 'All products')
  useDocumentTitle(title)

  const activeFilterCount = FILTER_KEYS.filter((key) => params[key]).length
  const crumbs = [{ label: 'Home', to: '/' }, { label: 'Products', to: '/products' }]
  if (category?.parent) crumbs.push({ label: category.parent.name, to: `/categories/${category.parent.slug}` })
  if (category || brand) crumbs.push({ label: title })

  const filters = <ProductFilters params={params} setParams={setParams} activeCategorySlug={categorySlug} lockedBrandSlug={brandSlug} />

  return (
    <div className="container-page py-8">
      <Breadcrumbs items={crumbs} className="mb-4" />

      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-slate-900">{title}</h1>
          {(category?.description || brand?.description) && <p className="mt-1 max-w-2xl text-slate-500">{category?.description ?? brand?.description}</p>}
          {data?.meta && (
            <p className="mt-1 text-sm text-slate-500" aria-live="polite">
              {data.meta.total} product{data.meta.total === 1 ? '' : 's'}
            </p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <Button variant="secondary" size="sm" className="lg:hidden" onClick={() => setFiltersOpen(true)}>
            <Icon name="filter" className="h-4 w-4" /> Filters {activeFilterCount > 0 && `(${activeFilterCount})`}
          </Button>
          <label htmlFor="sort" className="sr-only">
            Sort products
          </label>
          <select id="sort" value={params.sort ?? 'newest'} onChange={(e) => setParams({ sort: e.target.value })} className="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            {PRODUCT_SORTS.map((sort) => (
              <option key={sort.value} value={sort.value}>
                {sort.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="grid gap-8 lg:grid-cols-[240px_1fr]">
        <aside className="hidden lg:block" aria-label="Product filters">
          {filters}
          {activeFilterCount > 0 && (
            <Button variant="link" className="mt-4 text-sm" onClick={resetParams}>
              Clear all filters
            </Button>
          )}
        </aside>

        <div className="min-w-0">
          {error ? (
            <ErrorState error={error} onRetry={reload} />
          ) : !loading && data?.items.length === 0 ? (
            <div className="card">
              <EmptyState
                icon="search"
                title="No products found"
                description="Try adjusting your search or filters to find what you are looking for."
                action={
                  <Button variant="secondary" onClick={resetParams}>
                    Clear filters
                  </Button>
                }
              />
            </div>
          ) : (
            <>
              <ProductGrid products={data?.items ?? []} loading={loading} columns="sm:grid-cols-2 xl:grid-cols-3" skeletonCount={6} />
              <div className="mt-6">
                <Pagination meta={data?.meta} onChange={(page) => setParams({ page }, { resetPage: false })} />
              </div>
            </>
          )}
        </div>
      </div>

      <Modal
        open={filtersOpen}
        onClose={() => setFiltersOpen(false)}
        title="Filters"
        footer={
          <>
            <Button variant="secondary" onClick={resetParams}>
              Clear all
            </Button>
            <Button onClick={() => setFiltersOpen(false)}>Show results</Button>
          </>
        }
      >
        {filters}
      </Modal>
    </div>
  )
}
