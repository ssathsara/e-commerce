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
import { Checkbox, Input } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'
import { useResourceList } from '../../hooks/useResourceList'
import { formatDateTime } from '../../utils/format'

/**
 * The role picker only lists roles the current user may assign (from the API);
 * the API re-checks every assignment to prevent privilege escalation.
 */
function UserFormModal({ open, userId, onClose, onSaved }) {
  const toast = useToast()
  const { can, user: me } = useAuth()
  const canAssign = can(P.ASSIGN_ROLES)
  const detail = useAsync(() => (open && userId ? adminApi.users.get(userId) : Promise.resolve(null)), [open, userId])
  const assignable = useAsync(() => (open && canAssign ? adminApi.users.assignableRoles() : Promise.resolve([])), [open, canAssign])
  const form = useForm({})
  const [roles, setRoles] = useState([])
  const editing = detail.data
  const isSelf = editing && editing.id === me.id

  useEffect(() => {
    if (!open) return
    form.reset({ name: editing?.name ?? '', email: editing?.email ?? '', phone: editing?.phone ?? '', password: '', is_active: editing?.is_active ?? true })
    setRoles(editing?.roles ?? [])
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, editing])

  const onSubmit = form.submit(
    async (values) => {
      const payload = { ...values, password: values.password || null }
      if (canAssign && !isSelf) payload.roles = roles
      const { message } = userId ? await adminApi.users.update(userId, payload) : await adminApi.users.create(payload)
      toast.success(message)
      onSaved()
    },
    { onError: (e) => !e.isValidation && toast.error(e.message) },
  )

  const toggleRole = (name) => setRoles((current) => (current.includes(name) ? current.filter((r) => r !== name) : [...current, name]))
  const assignableNames = (assignable.data ?? []).map((r) => r.name)

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={userId ? 'Edit user' : 'New staff user'}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="user-form" loading={form.submitting} disabled={detail.loading}>
            {userId ? 'Save changes' : 'Create user'}
          </Button>
        </>
      }
    >
      <form id="user-form" onSubmit={onSubmit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Full name" required {...form.bind('name')} />
          <Input label="Email" type="email" required {...form.bind('email')} />
          <Input label="Phone" type="tel" {...form.bind('phone')} />
          <Input label={userId ? 'New password' : 'Password'} type="password" autoComplete="new-password" required={!userId} hint={userId ? 'Leave blank to keep the current password.' : undefined} {...form.bind('password')} />
        </div>

        <fieldset>
          <legend className="mb-2 text-sm font-medium text-slate-700">Roles</legend>
          {!canAssign ? (
            <p className="text-sm text-slate-500">You do not have permission to assign roles.</p>
          ) : isSelf ? (
            <p className="text-sm text-slate-500">You cannot change your own roles.</p>
          ) : (
            <div className="grid gap-2 sm:grid-cols-2">
              {[...new Set([...assignableNames, ...roles])].map((name) => {
                const allowed = assignableNames.includes(name)
                return (
                  <Checkbox
                    key={name}
                    label={name}
                    description={allowed ? undefined : 'Beyond your privileges'}
                    checked={roles.includes(name)}
                    disabled={!allowed}
                    onChange={() => toggleRole(name)}
                  />
                )
              })}
            </div>
          )}
          {form.error('roles') && <p className="mt-1 text-xs font-medium text-red-600">{form.error('roles')}</p>}
        </fieldset>

        {editing?.permissions && (
          <details className="rounded-lg bg-slate-50 p-3 text-sm">
            <summary className="cursor-pointer font-medium text-slate-700">Effective permissions ({editing.permissions.length})</summary>
            <div className="mt-2 flex flex-wrap gap-1">
              {editing.is_super_admin ? <Badge tone="violet">All permissions (Super Admin)</Badge> : editing.permissions.map((p) => <Badge key={p}>{p}</Badge>)}
            </div>
          </details>
        )}
      </form>
    </Modal>
  )
}

export default function UsersPage() {
  useDocumentTitle('Users')
  const { can, user: me } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const list = useResourceList((query) => adminApi.users.list({ ...query, per_page: 20 }))
  const roles = useAsync(() => (can(P.VIEW_ROLES) ? adminApi.roles.all() : Promise.resolve([])), [])
  const [modal, setModal] = useState({ open: false, userId: null })

  const toggleActive = (user) =>
    confirm({
      title: user.is_active ? `Deactivate ${user.name}?` : `Reactivate ${user.name}?`,
      message: user.is_active ? 'They will be signed out immediately and cannot sign in until reactivated.' : 'They will be able to sign in again.',
      confirmLabel: user.is_active ? 'Deactivate' : 'Reactivate',
      tone: user.is_active ? 'danger' : 'primary',
      onConfirm: async () => {
        try {
          toast.success((await adminApi.users.setActive(user.id, !user.is_active)).message)
          list.reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  const remove = (user) =>
    confirm({
      title: `Delete ${user.name}?`,
      message: 'The account will be removed and signed out.',
      confirmLabel: 'Delete user',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.users.remove(user.id))
          list.reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  const columns = [
    {
      key: 'name',
      header: 'User',
      render: (u) => (
        <div>
          <p className="font-medium text-slate-900">
            {u.name} {u.id === me.id && <span className="text-xs text-slate-500">(you)</span>}
          </p>
          <p className="text-xs text-slate-500">{u.email}</p>
        </div>
      ),
    },
    {
      key: 'roles',
      header: 'Roles',
      render: (u) => (
        <div className="flex flex-wrap gap-1">
          {u.roles?.map((r) => (
            <Badge key={r} tone={r === 'Super Admin' ? 'violet' : 'indigo'}>
              {r}
            </Badge>
          ))}
        </div>
      ),
    },
    { key: 'last_login_at', header: 'Last sign-in', render: (u) => (u.last_login_at ? formatDateTime(u.last_login_at) : 'Never'), hideBelow: 'lg' },
    { key: 'is_active', header: 'Status', render: (u) => <Badge tone={u.is_active ? 'green' : 'red'}>{u.is_active ? 'Active' : 'Deactivated'}</Badge>, hideBelow: 'sm' },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'text-right whitespace-nowrap',
      render: (u) => (
        <div className="flex justify-end gap-1">
          {can(P.EDIT_USERS) && (
            <RowAction label={`Edit ${u.name}`} onClick={() => setModal({ open: true, userId: u.id })}>
              <Icon name="pencil" className="h-4 w-4" />
            </RowAction>
          )}
          {can(P.EDIT_USERS) && u.id !== me.id && (
            <RowAction label={u.is_active ? `Deactivate ${u.name}` : `Reactivate ${u.name}`} onClick={() => toggleActive(u)}>
              <Icon name={u.is_active ? 'lock' : 'check'} className="h-4 w-4" />
            </RowAction>
          )}
          {can(P.DELETE_USERS) && u.id !== me.id && (
            <RowAction tone="danger" label={`Delete ${u.name}`} onClick={() => remove(u)}>
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
        title="Users"
        description="Back-office accounts and their roles. Customers are managed under Customers."
        actions={
          can(P.CREATE_USERS) &&
          can(P.ASSIGN_ROLES) && (
            <Button onClick={() => setModal({ open: true, userId: null })}>
              <Icon name="plus" className="h-4 w-4" /> Add user
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
            { key: 'role', label: 'All roles', options: (roles.data ?? []).filter((r) => r.name !== 'Customer').map((r) => ({ value: r.name, label: r.name })) },
            { key: 'status', label: 'All statuses', options: [{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Deactivated' }] },
          ]}
        />
        <DataTable caption="Users" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="users" title="No users found" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>
      <UserFormModal
        open={modal.open}
        userId={modal.userId}
        onClose={() => setModal({ open: false, userId: null })}
        onSaved={() => {
          setModal({ open: false, userId: null })
          list.reload()
        }}
      />
      {dialog}
    </div>
  )
}
