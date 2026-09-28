import { Link } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { ListToolbar, RowAction } from '../../components/admin/ListToolbar'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { DataTable } from '../../components/common/DataTable'
import { Icon } from '../../components/common/Icon'
import { PageHeader, ProductImage } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { P } from '../../constants/permissions'
import { PRODUCT_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useAdminOptions } from '../../hooks/useAdminOptions'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useResourceList } from '../../hooks/useResourceList'
import { formatPrice } from '../../utils/format'

const SORTS = [
  { value: 'newest', label: 'Newest' },
  { value: 'oldest', label: 'Oldest' },
  { value: 'name', label: 'Name' },
  { value: 'price_asc', label: 'Price ↑' },
  { value: 'price_desc', label: 'Price ↓' },
  { value: 'stock_asc', label: 'Stock ↑' },
  { value: 'popular', label: 'Best selling' },
]

export default function ProductsPage() {
  useDocumentTitle('Products')
  const { can } = useAuth()
  const toast = useToast()
  const options = useAdminOptions()
  const { confirm, dialog } = useConfirm()
  const list = useResourceList((query) => adminApi.products.list({ ...query, per_page: 15 }))

  const toggleStatus = async (product) => {
    const next = product.status === 'inactive' || product.status === 'draft' ? 'active' : 'inactive'
    try {
      const { message } = await adminApi.products.setStatus(product.id, next)
      toast.success(message)
      list.reload()
    } catch (error) {
      toast.error(error.message)
    }
  }

  const remove = (product) =>
    confirm({
      title: `Delete “${product.name}”?`,
      message: 'The product is removed from the store. Existing orders keep their records.',
      confirmLabel: 'Delete product',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.products.remove(product.id))
          list.reload()
        } catch (error) {
          toast.error(error.message)
          throw error
        }
      },
    })

  const columns = [
    {
      key: 'name',
      header: 'Product',
      render: (product) => (
        <div className="flex items-center gap-3">
          <ProductImage src={product.image?.url} alt="" className="h-10 w-10 shrink-0 rounded-md" />
          <div className="min-w-0">
            <Link to={`/admin/products/${product.id}`} className="block max-w-xs truncate font-medium text-slate-900 hover:text-brand-600">
              {product.name}
            </Link>
            <p className="text-xs text-slate-500">{product.sku}</p>
          </div>
        </div>
      ),
    },
    { key: 'category', header: 'Category', render: (p) => p.category?.name ?? '—', hideBelow: 'lg' },
    { key: 'price', header: 'Price', render: (p) => formatPrice(p.price) },
    {
      key: 'stock',
      header: 'Stock',
      render: (p) => <span className={p.stock_quantity === 0 ? 'font-medium text-red-600' : p.is_low_stock ? 'font-medium text-amber-700' : ''}>{p.stock_quantity}</span>,
      hideBelow: 'sm',
    },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge value={p.status} tones={PRODUCT_STATUS_TONES} label={p.status_label} /> },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-right whitespace-nowrap',
      render: (product) => (
        <div className="flex justify-end gap-1">
          <RowAction as={Link} to={`/admin/products/${product.id}`} label={`View ${product.name}`}>
            <Icon name="eye" className="h-4 w-4" />
          </RowAction>
          {can(P.EDIT_PRODUCTS) && (
            <>
              <RowAction as={Link} to={`/admin/products/${product.id}/edit`} label={`Edit ${product.name}`}>
                <Icon name="pencil" className="h-4 w-4" />
              </RowAction>
              <RowAction onClick={() => toggleStatus(product)} label={product.status === 'active' || product.status === 'out_of_stock' ? `Deactivate ${product.name}` : `Activate ${product.name}`}>
                <Icon name={product.status === 'active' || product.status === 'out_of_stock' ? 'archive' : 'check'} className="h-4 w-4" />
              </RowAction>
            </>
          )}
          {can(P.DELETE_PRODUCTS) && (
            <RowAction tone="danger" onClick={() => remove(product)} label={`Delete ${product.name}`}>
              <Icon name="trash" className="h-4 w-4" />
            </RowAction>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Products"
        description="Manage your catalogue, pricing and availability."
        actions={
          can(P.CREATE_PRODUCTS) && (
            <Button to="/admin/products/create">
              <Icon name="plus" className="h-4 w-4" /> Add product
            </Button>
          )
        }
      />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Search name or SKU…"
          params={list.params}
          setParams={list.setParams}
          filters={[
            { key: 'status', label: 'All statuses', options: options?.product_statuses ?? [] },
            { key: 'category_id', label: 'All categories', options: (options?.categories ?? []).map((c) => ({ value: String(c.id), label: c.name })) },
            { key: 'brand_id', label: 'All brands', options: (options?.brands ?? []).map((b) => ({ value: String(b.id), label: b.name })) },
            { key: 'stock', label: 'Any stock', options: [{ value: 'in', label: 'In stock' }, { value: 'low', label: 'Low stock' }, { value: 'out', label: 'Out of stock' }] },
            { key: 'sort', label: 'Sort: newest', options: SORTS.slice(1) },
          ]}
        />
        <DataTable
          caption="Products"
          columns={columns}
          rows={list.data?.items}
          loading={list.loading}
          error={list.error}
          onRetry={list.reload}
          empty={<EmptyState icon="box" title="No products found" description="Try different filters, or add your first product." />}
        />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
      {dialog}
    </div>
  )
}
