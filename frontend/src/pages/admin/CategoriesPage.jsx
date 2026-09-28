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
import { invalidateAdminOptions, useAdminOptions } from '../../hooks/useAdminOptions'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useResourceList } from '../../hooks/useResourceList'

export default function CategoriesPage() {
  useDocumentTitle('Categories')
  const { can } = useAuth()
  const toast = useToast()
  const options = useAdminOptions()
  const { confirm, dialog } = useConfirm()
  const list = useResourceList((query) => adminApi.categories.list(query))
  const [modal, setModal] = useState({ open: false, entity: null })

  const save = async (values) => {
    const { message } = modal.entity ? await adminApi.categories.update(modal.entity.id, values) : await adminApi.categories.create(values)
    toast.success(message)
    invalidateAdminOptions()
    setModal({ open: false, entity: null })
    list.reload()
  }

  const remove = (category) =>
    confirm({
      title: `Delete “${category.name}”?`,
      message: 'Categories that still contain products or subcategories cannot be deleted — move them first.',
      confirmLabel: 'Delete category',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.categories.remove(category.id))
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
      header: 'Category',
      render: (c) => (
        <div className="flex items-center gap-3">
          <ProductImage src={c.image_url} alt="" className="h-10 w-10 shrink-0 rounded-md" />
          <div>
            <p className="font-medium text-slate-900">{c.name}</p>
            <p className="text-xs text-slate-500">/{c.slug}</p>
          </div>
        </div>
      ),
    },
    { key: 'parent', header: 'Parent', render: (c) => c.parent?.name ?? '—', hideBelow: 'md' },
    { key: 'products_count', header: 'Products', hideBelow: 'sm' },
    { key: 'children_count', header: 'Subcategories', hideBelow: 'lg' },
    {
      key: 'status',
      header: 'Status',
      render: (c) => (
        <div className="flex gap-1">
          <Badge tone={c.is_active ? 'green' : 'slate'}>{c.is_active ? 'Active' : 'Inactive'}</Badge>
          {c.is_featured && <Badge tone="violet">Featured</Badge>}
        </div>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-right whitespace-nowrap',
      render: (c) => (
        <div className="flex justify-end gap-1">
          {can(P.EDIT_CATEGORIES) && (
            <RowAction label={`Edit ${c.name}`} onClick={() => setModal({ open: true, entity: c })}>
              <Icon name="pencil" className="h-4 w-4" />
            </RowAction>
          )}
          {can(P.DELETE_CATEGORIES) && (
            <RowAction tone="danger" label={`Delete ${c.name}`} onClick={() => remove(c)}>
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
        title="Categories"
        description="Organise products into a category hierarchy."
        actions={
          can(P.CREATE_CATEGORIES) && (
            <Button onClick={() => setModal({ open: true, entity: null })}>
              <Icon name="plus" className="h-4 w-4" /> Add category
            </Button>
          )
        }
      />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Search categories…"
          params={list.params}
          setParams={list.setParams}
          filters={[{ key: 'status', label: 'All statuses', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }] }]}
        />
        <DataTable caption="Categories" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="folder" title="No categories found" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
      <CatalogEntityModal kind="category" open={modal.open} entity={modal.entity} parents={options?.categories ?? []} onClose={() => setModal({ open: false, entity: null })} onSave={save} />
      {dialog}
    </div>
  )
}
