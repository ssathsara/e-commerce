import { useState } from 'react'
import { Link } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { ListToolbar } from '../../components/admin/ListToolbar'
import { Badge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { DataTable } from '../../components/common/DataTable'
import { Icon } from '../../components/common/Icon'
import { Modal } from '../../components/common/Modal'
import { PageHeader } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { Input } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'
import { useResourceList } from '../../hooks/useResourceList'
import { formatDate, formatPrice } from '../../utils/format'

function CreateCustomerModal({ open, onClose, onCreated }) {
  const toast = useToast()
  const form = useForm({ name: '', email: '', phone: '', password: '' })

  const onSubmit = form.submit(
    async (values) => {
      const { message } = await adminApi.customers.create(values)
      toast.success(message)
      form.reset()
      onCreated()
    },
    { onError: (e) => !e.isValidation && toast.error(e.message) },
  )

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New customer"
      description="Accounts created here are marked as email-verified."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="customer-form" loading={form.submitting}>
            Create customer
          </Button>
        </>
      }
    >
      <form id="customer-form" onSubmit={onSubmit} className="space-y-4" noValidate>
        <Input label="Full name" required {...form.bind('name')} />
        <Input label="Email" type="email" required {...form.bind('email')} />
        <Input label="Phone" type="tel" {...form.bind('phone')} />
        <Input label="Temporary password" type="password" required autoComplete="new-password" hint="Share it securely; the customer should change it after signing in." {...form.bind('password')} />
      </form>
    </Modal>
  )
}

export default function CustomersPage() {
  useDocumentTitle('Customers')
  const { can } = useAuth()
  const [creating, setCreating] = useState(false)
  const list = useResourceList((query) => adminApi.customers.list({ ...query, per_page: 20 }))

  const columns = [
    {
      key: 'name',
      header: 'Customer',
      render: (c) => (
        <div className="min-w-0">
          <Link to={`/admin/customers/${c.id}`} className="font-medium text-slate-900 hover:text-brand-600">
            {c.name}
          </Link>
          <p className="truncate text-xs text-slate-500">{c.email}</p>
        </div>
      ),
    },
    { key: 'orders_count', header: 'Orders', hideBelow: 'sm' },
    { key: 'total_spent', header: 'Total spent', render: (c) => formatPrice(c.total_spent ?? 0), hideBelow: 'md' },
    { key: 'created_at', header: 'Joined', render: (c) => formatDate(c.created_at), hideBelow: 'lg' },
    { key: 'is_active', header: 'Status', render: (c) => <Badge tone={c.is_active ? 'green' : 'red'}>{c.is_active ? 'Active' : 'Deactivated'}</Badge> },
  ]

  return (
    <div>
      <PageHeader
        title="Customers"
        description="Customer accounts and their purchase history."
        actions={
          can(P.CREATE_CUSTOMERS) && (
            <Button onClick={() => setCreating(true)}>
              <Icon name="plus" className="h-4 w-4" /> Add customer
            </Button>
          )
        }
      />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Search name or email…"
          params={list.params}
          setParams={list.setParams}
          filters={[
            { key: 'status', label: 'All accounts', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Deactivated' }] },
            { key: 'sort', label: 'Newest first', options: [{ value: 'oldest', label: 'Oldest first' }, { value: 'name', label: 'Name' }, { value: 'spent', label: 'Top spenders' }] },
          ]}
        />
        <DataTable caption="Customers" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="users" title="No customers found" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
      <CreateCustomerModal
        open={creating}
        onClose={() => setCreating(false)}
        onCreated={() => {
          setCreating(false)
          list.reload()
        }}
      />
    </div>
  )
}
