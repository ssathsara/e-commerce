import { Link, useParams } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { Badge, StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { DataTable } from '../../components/common/DataTable'
import { Icon } from '../../components/common/Icon'
import { Card, PageHeader, ProductImage, Rating } from '../../components/common/Misc'
import { PageSpinner } from '../../components/common/Spinner'
import { ErrorState } from '../../components/common/States'
import { P } from '../../constants/permissions'
import { PRODUCT_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatDateTime, formatPrice } from '../../utils/format'

export default function ProductViewPage() {
  const { productId } = useParams()
  const { can } = useAuth()
  const { data: product, loading, error, reload } = useAsync(() => adminApi.products.get(productId), [productId])
  const movements = useAsync(() => (can(P.VIEW_INVENTORY) ? adminApi.inventory.movements({ product_id: productId, per_page: 8 }) : Promise.resolve(null)), [productId])
  useDocumentTitle(product?.name ?? 'Product')

  if (loading) return <PageSpinner />
  if (error) return <ErrorState error={error} onRetry={reload} />

  const details = [
    ['SKU', product.sku],
    ['Slug', product.slug],
    ['Category', product.category?.name],
    ['Brand', product.brand?.name ?? '—'],
    ['Price', formatPrice(product.price)],
    ['Compare-at price', product.compare_at_price ? formatPrice(product.compare_at_price) : '—'],
    ['Stock', `${product.stock_quantity} (threshold ${product.low_stock_threshold})`],
    ['Units sold', product.sales_count],
    ['Created', formatDateTime(product.created_at)],
    ['Updated', formatDateTime(product.updated_at)],
  ]

  return (
    <div>
      <PageHeader
        title={product.name}
        description={product.short_description}
        actions={
          <>
            {['active', 'out_of_stock'].includes(product.status) && (
              <Button variant="secondary" to={`/products/${product.slug}`} target="_blank" rel="noreferrer">
                <Icon name="eye" className="h-4 w-4" /> View in store
              </Button>
            )}
            {can(P.EDIT_PRODUCTS) && (
              <Button to={`/admin/products/${product.id}/edit`}>
                <Icon name="pencil" className="h-4 w-4" /> Edit
              </Button>
            )}
          </>
        }
      >
        <div className="mb-2 flex items-center gap-2">
          <StatusBadge value={product.status} tones={PRODUCT_STATUS_TONES} label={product.status_label} />
          {product.is_featured && <Badge tone="violet">Featured</Badge>}
          {product.is_low_stock && <Badge tone="amber">Low stock</Badge>}
        </div>
      </PageHeader>

      <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <Card title="Images">
            {product.images.length ? (
              <ul className="grid grid-cols-3 gap-3 sm:grid-cols-5">
                {product.images.map((image) => (
                  <li key={image.id}>
                    <ProductImage src={image.url} alt={image.alt_text} className="aspect-square w-full rounded-lg" />
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-slate-500">No images uploaded.</p>
            )}
          </Card>

          <Card title={`Variants (${product.variants.length})`} bodyClassName="p-0">
            <DataTable
              columns={[
                { key: 'name', header: 'Variant' },
                { key: 'sku', header: 'SKU' },
                { key: 'price', header: 'Price', render: (v) => formatPrice(v.price) },
                { key: 'stock_quantity', header: 'Stock' },
                { key: 'is_active', header: 'Status', render: (v) => <Badge tone={v.is_active ? 'green' : 'slate'}>{v.is_active ? 'Active' : 'Inactive'}</Badge> },
              ]}
              rows={product.variants}
              empty={<p className="p-5 text-sm text-slate-500">This product has no variants.</p>}
            />
          </Card>

          {movements.data && (
            <Card title="Recent inventory movements" actions={<Link to={`/admin/inventory?search=${encodeURIComponent(product.sku)}`} className="text-sm font-medium text-brand-600 hover:underline">Inventory</Link>} bodyClassName="p-0">
              <DataTable
                columns={[
                  { key: 'created_at', header: 'Date', render: (m) => formatDateTime(m.created_at) },
                  { key: 'type', header: 'Type', render: (m) => m.type_label },
                  { key: 'variant', header: 'Variant', render: (m) => m.variant?.name ?? '—', hideBelow: 'md' },
                  { key: 'quantity', header: 'Change', render: (m) => <span className={m.quantity < 0 ? 'text-red-600' : 'text-emerald-700'}>{m.quantity > 0 ? `+${m.quantity}` : m.quantity}</span> },
                  { key: 'stock_after', header: 'Stock after' },
                ]}
                rows={movements.data.items}
                loading={movements.loading}
                error={movements.error}
                empty={<p className="p-5 text-sm text-slate-500">No movements recorded.</p>}
              />
            </Card>
          )}

          <Card title="Description">
            <p className="text-sm whitespace-pre-line text-slate-600">{product.description || 'No description.'}</p>
          </Card>
        </div>

        <div className="space-y-6">
          <Card title="Details">
            <dl className="divide-y divide-slate-100 text-sm">
              {details.map(([label, value]) => (
                <div key={label} className="flex justify-between gap-4 py-2">
                  <dt className="text-slate-500">{label}</dt>
                  <dd className="text-right font-medium text-slate-800">{value}</dd>
                </div>
              ))}
            </dl>
          </Card>
          <Card title="Reviews">
            <Rating value={product.rating_average} count={product.rating_count} showValue />
          </Card>
          <Card title="SEO">
            <p className="text-sm font-medium text-brand-700">{product.seo_title || product.name}</p>
            <p className="mt-1 text-sm text-slate-600">{product.seo_description || product.short_description || '—'}</p>
          </Card>
        </div>
      </div>
    </div>
  )
}
