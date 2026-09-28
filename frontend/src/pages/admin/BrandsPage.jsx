import { useState } from 'react'
import { adminApi } from '../../api/adminApi'
import { ListToolbar, RowAction } from '../../components/admin/ListToolbar'
import { Badge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { DataTable } from '../../components/common/DataTable'
import { Icon } from '../../components/common/Icon'
import { PageHeader, ProductImage } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { P } from '../../constants/permissions'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { CatalogEntityModal } from '../../features/admin/CatalogEntityModal'
import { invalidateAdminOptions } from '../../hooks/useAdminOptions'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useResourceList } from '../../hooks/useResourceList'

export default function BrandsPage() {
  useDocumentTitle('Brands')
  const { can } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const list = useResourceList((query) => adminApi.brands.list(query))
  const [modal, setModal] = useState({ open: false, entity: null })

  const save = async (values) => {
    const { message } = modal.entity ? await adminApi.brands.update(modal.entity.id, values) : await adminApi.brands.create(values)
    toast.success(message)
    invalidateAdminOptions()
    setModal({ open: false, entity: null })
    list.reload()
  }

  const toggleActive = async (brand) => {
    try {
      const { message } = await adminApi.brands.update(brand.id, { name: brand.name, slug: brand.slug, description: brand.description, is_active: !brand.is_active })
      toast.success(message)
      list.reload()
    } catch (error) {
      toast.error(error.message)
    }
  }

  const remove = (brand) =>
    confirm({
      title: `Delete “${brand.name}”?`,
      message: 'Brands that still have products cannot be deleted — reassign them or deactivate the brand instead.',
      confirmLabel: 'Delete brand',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.brands.remove(brand.id))
          invalidateAdminOptions()
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
      header: 'Brand',
      render: (b) => (
        <div className="flex items-center gap-3">
          <ProductImage src={b.logo_url} alt="" className="h-10 w-10 shrink-0 rounded-md" />
          <div>
            <p className="font-medium text-slate-900">{b.name}</p>
            <p className="text-xs text-slate-500">/{b.slug}</p>
          </div>
        </div>
      ),
    },
    { key: 'products_count', header: 'Products', hideBelow: 'sm' },
    { key: 'status', header: 'Status', render: (b) => <Badge tone={b.is_active ? 'green' : 'slate'}>{b.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-right whitespace-nowrap',
      render: (b) => (
        <div className="flex justify-end gap-1">
          {can(P.EDIT_BRANDS) && (
            <>
              <RowAction label={`Edit ${b.name}`} onClick={() => setModal({ open: true, entity: b })}>
                <Icon name="pencil" className="h-4 w-4" />
              </RowAction>
              <RowAction label={b.is_active ? `Deactivate ${b.name}` : `Activate ${b.name}`} onClick={() => toggleActive(b)}>
                <Icon name={b.is_active ? 'archive' : 'check'} className="h-4 w-4" />
              </RowAction>
            </>
          )}
          {can(P.DELETE_BRANDS) && (
            <RowAction tone="danger" label={`Delete ${b.name}`} onClick={() => remove(b)}>
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
        title="Brands"
        description="Manage the brands your products belong to."
        actions={
          can(P.CREATE_BRANDS) && (
            <Button onClick={() => setModal({ open: true, entity: null })}>
              <Icon name="plus" className="h-4 w-4" /> Add brand
            </Button>
          )
        }
      />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Search brands…"
          params={list.params}
          setParams={list.setParams}
          filters={[{ key: 'status', label: 'All statuses', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }]}
        />
        <DataTable caption="Brands" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="tag" title="No brands found" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
      <CatalogEntityModal kind="brand" open={modal.open} entity={modal.entity} onClose={() => setModal({ open: false, entity: null })} onSave={save} />
      {dialog}
    </div>
  )
}
