import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { productApi } from '../../api/productApi'
import { Button } from '../../components/common/Button'
import { Rating } from '../../components/common/Misc'
import { useAsync } from '../../hooks/useAsync'
import { useCategories } from '../../hooks/useCategories'

function FilterGroup({ title, children }) {
  return (
    <fieldset className="border-b border-slate-200 py-5 first:pt-0">
      <legend className="mb-3 text-sm font-semibold text-slate-900">{title}</legend>
      {children}
    </fieldset>
  )
}

/**
 * Facet controls. Every change updates URL params, which triggers a server-side
 * query — nothing is filtered in the browser.
 */
export function ProductFilters({ params, setParams, activeCategorySlug, lockedBrandSlug }) {
  const { categories } = useCategories()
  const { data: brands } = useAsync(() => productApi.brands(), [])
  const [price, setPrice] = useState({ min: params.min_price ?? '', max: params.max_price ?? '' })

  useEffect(() => {
    setPrice({ min: params.min_price ?? '', max: params.max_price ?? '' })
  }, [params.min_price, params.max_price])

  const selectedBrands = (params.brand ?? '').split(',').filter(Boolean)

  const toggleBrand = (slug) => {
    const next = selectedBrands.includes(slug) ? selectedBrands.filter((b) => b !== slug) : [...selectedBrands, slug]
    setParams({ brand: next.join(',') })
  }

  const applyPrice = (event) => {
    event.preventDefault()
    setParams({ min_price: price.min, max_price: price.max })
  }

  return (
    <div>
      <FilterGroup title="Categories">
        <ul className="space-y-1 text-sm">
          <li>
            <Link to="/products" className={`block rounded px-2 py-1 ${!activeCategorySlug ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:text-slate-900'}`}>
              All categories
            </Link>
          </li>
          {categories.map((category) => (
            <li key={category.id}>
              <Link
                to={`/categories/${category.slug}`}
                className={`flex justify-between rounded px-2 py-1 ${activeCategorySlug === category.slug ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:text-slate-900'}`}
              >
                {category.name}
              </Link>
              {category.children?.length > 0 && (
                <ul className="ml-3 border-l border-slate-200 pl-2">
                  {category.children.map((child) => (
                    <li key={child.id}>
                      <Link
                        to={`/categories/${child.slug}`}
                        className={`block rounded px-2 py-0.5 ${activeCategorySlug === child.slug ? 'font-medium text-brand-700' : 'text-slate-500 hover:text-slate-900'}`}
                      >
                        {child.name}
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </li>
          ))}
        </ul>
      </FilterGroup>

      {!lockedBrandSlug && brands?.length > 0 && (
        <FilterGroup title="Brand">
          <div className="max-h-56 space-y-2 overflow-y-auto pr-1">
            {brands.map((brand) => (
              <label key={brand.id} className="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" checked={selectedBrands.includes(brand.slug)} onChange={() => toggleBrand(brand.slug)} />
                <span className="flex-1">{brand.name}</span>
                <span className="text-xs text-slate-400">{brand.products_count}</span>
              </label>
            ))}
          </div>
        </FilterGroup>
      )}

      <FilterGroup title="Price">
        <form onSubmit={applyPrice} className="flex items-end gap-2">
          <label className="flex-1 text-xs text-slate-500">
            Min
            <input type="number" min="0" inputMode="decimal" value={price.min} onChange={(e) => setPrice((p) => ({ ...p, min: e.target.value }))} className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="0" />
          </label>
          <label className="flex-1 text-xs text-slate-500">
            Max
            <input type="number" min="0" inputMode="decimal" value={price.max} onChange={(e) => setPrice((p) => ({ ...p, max: e.target.value }))} className="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Any" />
          </label>
          <Button type="submit" variant="secondary" size="sm">
            Go
          </Button>
        </form>
      </FilterGroup>

      <FilterGroup title="Availability">
        <label className="flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" checked={params.in_stock === '1'} onChange={(e) => setParams({ in_stock: e.target.checked ? '1' : '' })} />
          In stock only
        </label>
      </FilterGroup>

      <FilterGroup title="Customer rating">
        <div className="space-y-2">
          {[4, 3, 2].map((rating) => (
            <label key={rating} className="flex items-center gap-2 text-sm text-slate-600">
              <input type="radio" name="rating" className="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" checked={params.rating === String(rating)} onChange={() => setParams({ rating })} />
              <Rating value={rating} /> <span>& up</span>
            </label>
          ))}
          {params.rating && (
            <button type="button" className="text-xs font-medium text-brand-600 hover:underline" onClick={() => setParams({ rating: '' })}>
              Clear rating
            </button>
          )}
        </div>
      </FilterGroup>
    </div>
  )
}
