import { Link } from 'react-router-dom'
import { productApi } from '../../api/productApi'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { ProductImage } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { ErrorState } from '../../components/common/States'
import { ProductGrid } from '../../components/product/ProductGrid'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatPrice } from '../../utils/format'

function Section({ title, subtitle, link, children }) {
  return (
    <section className="container-page py-10" aria-labelledby={`section-${title}`}>
      <div className="mb-6 flex items-end justify-between gap-4">
        <div>
          <h2 id={`section-${title}`} className="text-2xl font-bold tracking-tight text-slate-900">
            {title}
          </h2>
          {subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}
        </div>
        {link && (
          <Link to={link} className="flex shrink-0 items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
            View all <Icon name="chevronRight" className="h-4 w-4" />
          </Link>
        )}
      </div>
      {children}
    </section>
  )
}

function Hero() {
  return (
    <section className="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-violet-600 text-white">
      <div className="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-white/10 blur-2xl" aria-hidden="true" />
      <div className="absolute -bottom-32 left-1/3 h-80 w-80 rounded-full bg-violet-400/20 blur-3xl" aria-hidden="true" />
      <div className="container-page relative grid items-center gap-10 py-16 sm:py-24 lg:grid-cols-2">
        <div>
          <p className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-wide uppercase">
            <Icon name="truck" className="h-4 w-4" /> Free shipping over $100
          </p>
          <h1 className="mt-5 text-4xl font-extrabold tracking-tight sm:text-5xl">Everyday essentials, thoughtfully chosen.</h1>
          <p className="mt-4 max-w-lg text-lg text-brand-100">Discover electronics, fashion, home goods and more — with honest prices and fast delivery.</p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button to="/products" size="lg" className="bg-white !text-brand-700 hover:bg-brand-50">
              Shop all products
            </Button>
            <Button to="/products?sort=popular" size="lg" variant="ghost" className="!text-white ring-1 ring-white/40 hover:bg-white/10">
              See best sellers
            </Button>
          </div>
        </div>
        <div className="hidden grid-cols-2 gap-4 lg:grid" aria-hidden="true">
          {[['Electronics', 'from-sky-300/40'], ['Fashion', 'from-rose-300/40'], ['Home', 'from-amber-300/40'], ['Outdoors', 'from-emerald-300/40']].map(([label, tone]) => (
            <div key={label} className={`flex h-36 items-end rounded-2xl bg-gradient-to-br ${tone} to-white/5 p-4 ring-1 ring-white/20`}>
              <span className="text-lg font-semibold">{label}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export default function HomePage() {
  useDocumentTitle(null)
  const { data, loading, error, reload } = useAsync(() => productApi.home(), [])

  if (error) {
    return (
      <>
        <Hero />
        <ErrorState error={error} onRetry={reload} />
      </>
    )
  }

  return (
    <>
      <Hero />

      <Section title="Shop by category" link="/products">
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          {loading
            ? Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="aspect-[4/3] w-full rounded-xl" />)
            : data.featured_categories.map((category) => (
                <Link key={category.id} to={`/categories/${category.slug}`} className="group card overflow-hidden transition-shadow hover:shadow-md">
                  <ProductImage src={category.image_url} alt="" className="aspect-[4/3] w-full transition-transform duration-300 group-hover:scale-105" />
                  <div className="p-3">
                    <p className="text-sm font-semibold text-slate-900">{category.name}</p>
                    <p className="text-xs text-slate-500">{category.products_count} products</p>
                  </div>
                </Link>
              ))}
        </div>
      </Section>

      <Section title="Featured products" subtitle="Hand-picked favourites from our team" link="/products?featured=1">
        <ProductGrid products={data?.featured_products ?? []} loading={loading} />
      </Section>

      {data?.on_sale?.length > 0 && (
        <section className="container-page py-6">
          <div className="grid items-center gap-8 overflow-hidden rounded-3xl bg-slate-900 p-8 text-white sm:p-12 lg:grid-cols-[1fr_2fr]">
            <div>
              <p className="text-sm font-semibold tracking-wide text-rose-300 uppercase">Limited-time offers</p>
              <h2 className="mt-2 text-3xl font-bold tracking-tight">Save on customer favourites</h2>
              <p className="mt-3 text-slate-300">Prices drop while stock lasts. Use code <span className="font-mono font-semibold text-white">WELCOME10</span> for an extra 10% off your first order.</p>
              <Button to="/products?sort=popular" className="mt-6 bg-white !text-slate-900 hover:bg-slate-100">
                Shop the sale
              </Button>
            </div>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 [&_article]:text-slate-900">
              {data.on_sale.map((product) => (
                <Link key={product.id} to={`/products/${product.slug}`} className="group rounded-xl bg-white/5 p-2 ring-1 ring-white/10 hover:bg-white/10">
                  <ProductImage src={product.image?.url} alt="" className="aspect-square w-full rounded-lg" />
                  <p className="mt-2 line-clamp-1 text-sm font-medium">{product.name}</p>
                  <p className="text-sm">
                    <span className="font-semibold text-rose-300">{formatPrice(product.price)}</span>{' '}
                    <span className="text-xs text-slate-400 line-through">{formatPrice(product.compare_at_price)}</span>
                  </p>
                </Link>
              ))}
            </div>
          </div>
        </section>
      )}

      <Section title="New arrivals" subtitle="The latest additions to our catalogue" link="/products?sort=newest">
        <ProductGrid products={data?.new_arrivals ?? []} loading={loading} />
      </Section>

      {(loading || data?.best_sellers?.length > 0) && (
        <Section title="Best sellers" subtitle="What other customers love" link="/products?sort=popular">
          <ProductGrid products={data?.best_sellers ?? []} loading={loading} />
        </Section>
      )}
    </>
  )
}
