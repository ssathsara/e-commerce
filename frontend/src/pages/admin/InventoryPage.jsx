import { useState } from 'react'
import { Link } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { ListToolbar } from '../../components/admin/ListToolbar'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { DataTable } from '../../components/common/DataTable'
import { Modal } from '../../components/common/Modal'
import { PageHeader, ProductImage } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { Input, Select } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { PRODUCT_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useAdminOptions } from '../../hooks/useAdminOptions'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'
import { useResourceList } from '../../hooks/useResourceList'
import { formatDateTime } from '../../utils/format'

function AdjustStockModal({ product, onClose, onDone }) {
  const toast = useToast()
  const options = useAdminOptions()
  const form = useForm({ variant_id: product?.variants?.[0]?.id ? String(product.variants[0].id) : '', type: 'purchase', quantity: '', note: '' })
  const hasVariants = product?.variants?.length > 0

  const onSubmit = form.submit(
    async (values) => {
      const { message } = await adminApi.inventory.adjust({
        product_id: product.id,
        variant_id: hasVariants ? Number(values.variant_id) : null,
        type: values.type,
        quantity: Number(values.quantity),
        note: values.note || null,
      })
      toast.success(message)
      onDone()
    },
    { onError: (e) => !e.isValidation && toast.error(e.message) },
  )

  return (
    <Modal
      open={Boolean(product)}
      onClose={onClose}
      title="Adjust stock"
      description={product?.name}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="adjust-form" loading={form.submitting}>
            Record movement
          </Button>
        </>
      }
    >
      <form id="adjust-form" onSubmit={onSubmit} className="space-y-4" noValidate>
        {hasVariants && (
          <Select label="Variant" required options={product.variants.map((v) => ({ value: String(v.id), label: `${v.name} — ${v.stock_quantity} in stock` }))} {...form.bind('variant_id')} />
        )}
        <Select label="Movement type" required options={options?.manual_movement_types ?? []} {...form.bind('type')} hint="Purchases and returns add stock. Adjustments can add or remove." />
        <Input label="Quantity change" type="number" required hint="Use a negative number to remove stock (adjustments only)." {...form.bind('quantity')} />
        <Input label="Note" maxLength={255} hint={form.values.type === 'adjustment' ? 'Required for adjustments.' : 'Optional, e.g. supplier invoice number.'} {...form.bind('note')} />
      </form>
    </Modal>
  )
}

function MovementsModal({ product, onClose }) {
  const [page, setPage] = useState(1)
  const movements = useAsync(() => (product ? adminApi.inventory.movements({ product_id: product.id, page, per_page: 10 }) : Promise.resolve(null)), [product?.id, page])

  return (
    <Modal open={Boolean(product)} onClose={onClose} title="Inventory history" description={product?.name} size="xl">
      <DataTable
        columns={[
          { key: 'created_at', header: 'Date', render: (m) => formatDateTime(m.created_at) },
          { key: 'type', header: 'Type', render: (m) => m.type_label },
          { key: 'variant', header: 'Variant', render: (m) => m.variant?.name ?? '—', hideBelow: 'md' },
          { key: 'quantity', header: 'Change', render: (m) => <span className={m.quantity < 0 ? 'font-medium text-red-600' : 'font-medium text-emerald-700'}>{m.quantity > 0 ? `+${m.quantity}` : m.quantity}</span> },
          { key: 'stock', header: 'Stock', render: (m) => `${m.stock_before} → ${m.stock_after}`, hideBelow: 'sm' },
          { key: 'ref', header: 'Reference', render: (m) => m.order?.order_number ?? m.note ?? '—', hideBelow: 'md' },
          { key: 'user', header: 'By', render: (m) => m.user ?? 'System', hideBelow: 'lg' },
        ]}
        rows={movements.data?.items}
        loading={movements.loading}
        error={movements.error}
        onRetry={movements.reload}
        empty={<p className="p-4 text-sm text-slate-500">No movements recorded.</p>}
      />
      <Pagination meta={movements.data?.meta} onChange={setPage} />
    </Modal>
  )
}

export default function InventoryPage() {
  useDocumentTitle('Inventory')
  const { can } = useAuth()
  const list = useResourceList((query) => adminApi.inventory.list({ ...query, per_page: 20 }))
  const [adjusting, setAdjusting] = useState(null)
  const [history, setHistory] = useState(null)
  const meta = list.data?.meta

  const columns = [
    {
      key: 'product',
      header: 'Product',
      render: (p) => (
        <div className="flex items-center gap-3">
          <ProductImage src={p.image?.url} alt="" className="h-10 w-10 shrink-0 rounded-md" />
          <div className="min-w-0">
            <Link to={`/admin/products/${p.id}`} className="block max-w-xs truncate font-medium text-slate-900 hover:text-brand-600">
              {p.name}
            </Link>
            <p className="text-xs text-slate-500">{p.sku}</p>
            {p.variants?.length > 0 && <p className="text-xs text-slate-500">{p.variants.map((v) => `${v.name}: ${v.stock_quantity}`).join(' · ')}</p>}
          </div>
        </div>
      ),
    },
    {
      key: 'stock_quantity',
      header: 'Stock',
      render: (p) => <span className={`text-base font-semibold ${p.stock_quantity === 0 ? 'text-red-600' : p.is_low_stock ? 'text-amber-700' : 'text-slate-900'}`}>{p.stock_quantity}</span>,
    },
    { key: 'low_stock_threshold', header: 'Threshold', hideBelow: 'sm' },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge value={p.status} tones={PRODUCT_STATUS_TONES} label={p.status_label} />, hideBelow: 'md' },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-right whitespace-nowrap',
      render: (p) => (
        <div className="flex justify-end gap-2">
          <Button size="xs" variant="ghost" onClick={() => setHistory(p)}>
            History
          </Button>
          {can(P.MANAGE_INVENTORY) && (
            <Button size="xs" variant="secondary" onClick={() => setAdjusting(p)}>
              Adjust
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader title="Inventory" description="Stock levels across your catalogue. Every change is recorded as a movement." />

      <div className="mb-4 flex flex-wrap gap-2">
        {[
          ['', 'All products'],
          ['low', `Low stock${meta ? ` (${meta.low_stock_count})` : ''}`],
          ['out', `Out of stock${meta ? ` (${meta.out_of_stock_count})` : ''}`],
        ].map(([value, label]) => (
          <button
            key={label}
            type="button"
            onClick={() => list.setParams({ stock: value })}
            aria-pressed={(list.params.stock ?? '') === value}
            className={`rounded-full px-3 py-1.5 text-sm font-medium ${(list.params.stock ?? '') === value ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'}`}
          >
            {label}
          </button>
        ))}
      </div>

      <div className="card overflow-hidden">
        <ListToolbar search={list.search} onSearch={list.setSearch} searchPlaceholder="Search name or SKU…" params={list.params} setParams={list.setParams} />
        <DataTable caption="Inventory" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="archive" title="No products match" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={meta} onChange={list.setPage} />
        </div>
      </div>

      {adjusting && (
        <AdjustStockModal
          product={adjusting}
          onClose={() => setAdjusting(null)}
          onDone={() => {
            setAdjusting(null)
            list.reload()
          }}
        />
      )}
      <MovementsModal product={history} onClose={() => setHistory(null)} />
    </div>
  )
}
