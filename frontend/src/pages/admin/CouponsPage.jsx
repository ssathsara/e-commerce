import { useEffect, useState } from 'react'
import { adminApi } from '../../api/adminApi'
import { ListToolbar, RowAction } from '../../components/admin/ListToolbar'
import { Badge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { DataTable } from '../../components/common/DataTable'
import { Icon } from '../../components/common/Icon'
import { Modal } from '../../components/common/Modal'
import { PageHeader } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { Checkbox, Input, Select } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'
import { useResourceList } from '../../hooks/useResourceList'
import { formatDate, formatDateTime, formatPrice } from '../../utils/format'

/** ISO timestamp -> value for <input type="datetime-local"> in the browser's timezone. */
const toLocalInput = (iso) => {
  if (!iso) return ''
  const date = new Date(iso)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
}
const emptyToNull = (v) => (v === '' || v === undefined ? null : v)

function describeValue(coupon) {
  return coupon.type === 'percentage' ? `${coupon.value}% off` : `${formatPrice(coupon.value)} off`
}

function CouponFormModal({ open, coupon, onClose, onSaved }) {
  const toast = useToast()
  const form = useForm({})

  useEffect(() => {
    if (!open) return
    form.reset({
      code: coupon?.code ?? '',
      description: coupon?.description ?? '',
      type: coupon?.type ?? 'percentage',
      value: coupon?.value ?? '',
      min_order_amount: coupon?.min_order_amount ?? '',
      max_discount_amount: coupon?.max_discount_amount ?? '',
      starts_at: toLocalInput(coupon?.starts_at),
      expires_at: toLocalInput(coupon?.expires_at),
      usage_limit: coupon?.usage_limit ?? '',
      usage_limit_per_user: coupon?.usage_limit_per_user ?? '',
      is_active: coupon?.is_active ?? true,
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, coupon])

  const onSubmit = form.submit(
    async (values) => {
      const payload = {
        ...values,
        min_order_amount: emptyToNull(values.min_order_amount),
        max_discount_amount: emptyToNull(values.max_discount_amount),
        usage_limit: emptyToNull(values.usage_limit),
        usage_limit_per_user: emptyToNull(values.usage_limit_per_user),
        starts_at: values.starts_at ? new Date(values.starts_at).toISOString() : null,
        expires_at: values.expires_at ? new Date(values.expires_at).toISOString() : null,
      }
      const { message } = coupon ? await adminApi.coupons.update(coupon.id, payload) : await adminApi.coupons.create(payload)
      toast.success(message)
      onSaved()
    },
    { onError: (e) => !e.isValidation && toast.error(e.message) },
  )

  const isPercentage = form.values.type === 'percentage'

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={coupon ? `Edit ${coupon.code}` : 'New coupon'}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="coupon-form" loading={form.submitting}>
            {coupon ? 'Save changes' : 'Create coupon'}
          </Button>
        </>
      }
    >
      <form id="coupon-form" onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2" noValidate>
        <Input label="Code" required {...form.bind('code')} onChange={(e) => form.set('code', e.target.value.toUpperCase())} />
        <Select label="Type" required options={[{ value: 'percentage', label: 'Percentage' }, { value: 'fixed', label: 'Fixed amount' }]} {...form.bind('type')} />
        <Input label={isPercentage ? 'Discount (%)' : 'Discount amount'} type="number" step={isPercentage ? '1' : '0.01'} min="0" required {...form.bind('value')} />
        <Input label="Maximum discount" type="number" step="0.01" min="0" hint="Optional cap for percentage coupons." {...form.bind('max_discount_amount')} />
        <Input label="Minimum order amount" type="number" step="0.01" min="0" {...form.bind('min_order_amount')} />
        <Input label="Description" {...form.bind('description')} />
        <Input label="Starts at" type="datetime-local" {...form.bind('starts_at')} />
        <Input label="Expires at" type="datetime-local" {...form.bind('expires_at')} />
        <Input label="Total usage limit" type="number" min="1" hint="Leave blank for unlimited." {...form.bind('usage_limit')} />
        <Input label="Per-customer limit" type="number" min="1" hint="Leave blank for unlimited." {...form.bind('usage_limit_per_user')} />
        <Checkbox label="Active" className="sm:col-span-2" {...form.bindCheckbox('is_active')} />
      </form>
    </Modal>
  )
}

function UsageModal({ coupon, onClose }) {
  const [page, setPage] = useState(1)
  const usages = useAsync(() => (coupon ? adminApi.coupons.usages(coupon.id, { page, per_page: 10 }) : Promise.resolve(null)), [coupon?.id, page])

  return (
    <Modal open={Boolean(coupon)} onClose={onClose} title={`Usage of ${coupon?.code ?? ''}`} size="lg">
      <DataTable
        columns={[
          { key: 'created_at', header: 'Date', render: (u) => formatDateTime(u.created_at) },
          { key: 'customer', header: 'Customer', render: (u) => u.customer?.name },
          { key: 'order', header: 'Order', render: (u) => u.order?.order_number },
          { key: 'discount_amount', header: 'Discount', render: (u) => formatPrice(u.discount_amount) },
        ]}
        rows={usages.data?.items}
        loading={usages.loading}
        error={usages.error}
        empty={<p className="p-4 text-sm text-slate-500">This coupon has not been used yet.</p>}
      />
      <Pagination meta={usages.data?.meta} onChange={setPage} />
    </Modal>
  )
}

export default function CouponsPage() {
  useDocumentTitle('Coupons')
  const { can } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const list = useResourceList((query) => adminApi.coupons.list({ ...query, per_page: 20 }))
  const [editing, setEditing] = useState({ open: false, coupon: null })
  const [usageFor, setUsageFor] = useState(null)

  const toggle = async (coupon) => {
    try {
      toast.success((await adminApi.coupons.toggle(coupon.id)).message)
      list.reload()
    } catch (e) {
      toast.error(e.message)
    }
  }

  const remove = (coupon) =>
    confirm({
      title: `Delete ${coupon.code}?`,
      message: 'The code will stop working. Past orders keep their discount records.',
      confirmLabel: 'Delete coupon',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.coupons.remove(coupon.id))
          list.reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  const columns = [
    {
      key: 'code',
      header: 'Code',
      render: (c) => (
        <div>
          <p className="font-mono font-semibold text-slate-900">{c.code}</p>
          <p className="text-xs text-slate-500">{c.description}</p>
        </div>
      ),
    },
    { key: 'value', header: 'Discount', render: describeValue },
    { key: 'usage', header: 'Used', render: (c) => `${c.times_used}${c.usage_limit ? ` / ${c.usage_limit}` : ''}`, hideBelow: 'sm' },
    { key: 'expires_at', header: 'Expires', render: (c) => (c.expires_at ? formatDate(c.expires_at) : 'Never'), hideBelow: 'md' },
    {
      key: 'status',
      header: 'Status',
      render: (c) => (c.is_expired ? <Badge tone="slate">Expired</Badge> : <Badge tone={c.is_active ? 'green' : 'amber'}>{c.is_active ? 'Active' : 'Inactive'}</Badge>),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-right whitespace-nowrap',
      render: (c) => (
        <div className="flex justify-end gap-1">
          <RowAction label={`Usage of ${c.code}`} onClick={() => setUsageFor(c)}>
            <Icon name="receipt" className="h-4 w-4" />
          </RowAction>
          {can(P.EDIT_COUPONS) && (
            <>
              <RowAction label={`Edit ${c.code}`} onClick={() => setEditing({ open: true, coupon: c })}>
                <Icon name="pencil" className="h-4 w-4" />
              </RowAction>
              <RowAction label={c.is_active ? `Deactivate ${c.code}` : `Activate ${c.code}`} onClick={() => toggle(c)}>
                <Icon name={c.is_active ? 'archive' : 'check'} className="h-4 w-4" />
              </RowAction>
            </>
          )}
          {can(P.DELETE_COUPONS) && (
            <RowAction tone="danger" label={`Delete ${c.code}`} onClick={() => remove(c)}>
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
        title="Coupons"
        description="Discount codes, limits and usage."
        actions={
          can(P.CREATE_COUPONS) && (
            <Button onClick={() => setEditing({ open: true, coupon: null })}>
              <Icon name="plus" className="h-4 w-4" /> New coupon
            </Button>
          )
        }
      />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Search code…"
          params={list.params}
          setParams={list.setParams}
          filters={[{ key: 'status', label: 'All coupons', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'expired', label: 'Expired' }] }]}
        />
        <DataTable caption="Coupons" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="ticket" title="No coupons found" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
      <CouponFormModal
        open={editing.open}
        coupon={editing.coupon}
        onClose={() => setEditing({ open: false, coupon: null })}
        onSaved={() => {
          setEditing({ open: false, coupon: null })
          list.reload()
        }}
      />
      <UsageModal coupon={usageFor} onClose={() => setUsageFor(null)} />
      {dialog}
    </div>
  )
}
