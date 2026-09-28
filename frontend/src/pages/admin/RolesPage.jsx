import { useEffect, useState } from 'react'
import { adminApi } from '../../api/adminApi'
import { Badge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { Icon } from '../../components/common/Icon'
import { Modal } from '../../components/common/Modal'
import { PageHeader } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { ErrorState } from '../../components/common/States'
import { Input } from '../../components/forms/Fields'
import { P } from '../../constants/permissions'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'

/**
 * Permission checkboxes grouped by module. Permissions the current user does not
 * hold are disabled — they cannot be granted or revoked by them (the API enforces this).
 */
function RoleEditor({ open, role, groups, onClose, onSaved }) {
  const toast = useToast()
  const { can, isSuperAdmin } = useAuth()
  const form = useForm({ name: '' })
  const [selected, setSelected] = useState(new Set())
  const canAssign = can(P.ASSIGN_PERMISSIONS)

  useEffect(() => {
    if (!open) return
    form.reset({ name: role?.name ?? '' })
    setSelected(new Set(role?.permissions ?? []))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, role])

  const toggle = (name) =>
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(name)) next.delete(name)
      else next.add(name)
      return next
    })

  const onSubmit = form.submit(
    async (values) => {
      const payload = {}
      if (!role?.is_system) payload.name = values.name
      if (canAssign) payload.permissions = [...selected]
      const { message } = role ? await adminApi.roles.update(role.id, payload) : await adminApi.roles.create(payload)
      toast.success(message)
      onSaved()
    },
    { onError: (e) => !e.isValidation && toast.error(e.message) },
  )

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={role ? `Edit role: ${role.name}` : 'New role'}
      size="xl"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="role-form" loading={form.submitting}>
            {role ? 'Save role' : 'Create role'}
          </Button>
        </>
      }
    >
      <form id="role-form" onSubmit={onSubmit} className="space-y-5" noValidate>
        <Input label="Role name" required disabled={role?.is_system} hint={role?.is_system ? 'Built-in roles cannot be renamed.' : undefined} {...form.bind('name')} />
        {!canAssign && <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">You can rename roles but not change their permissions.</p>}
        {form.error('permissions') && <p className="text-sm text-red-600">{form.error('permissions')}</p>}
        <div className="grid gap-4 md:grid-cols-2">
          {groups.map((group) => (
            <fieldset key={group.group} className="rounded-xl border border-slate-200 p-4">
              <legend className="px-1 text-sm font-semibold text-slate-800">{group.group}</legend>
              <div className="space-y-2">
                {group.permissions.map((permission) => {
                  const grantable = canAssign && (isSuperAdmin || can(permission.name))
                  return (
                    <label key={permission.name} className={`flex items-center gap-2 text-sm ${grantable ? 'text-slate-700' : 'text-slate-400'}`}>
                      <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" checked={selected.has(permission.name)} disabled={!grantable} onChange={() => toggle(permission.name)} />
                      {permission.name}
                    </label>
                  )
                })}
              </div>
            </fieldset>
          ))}
        </div>
      </form>
    </Modal>
  )
}

export default function RolesPage() {
  useDocumentTitle('Roles')
  const { can, hasRole, isSuperAdmin } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const roles = useAsync(() => adminApi.roles.all(), [])
  const groups = useAsync(() => (can(P.VIEW_PERMISSIONS) || can(P.ASSIGN_PERMISSIONS) ? adminApi.permissions() : Promise.resolve([])), [])
  const [editor, setEditor] = useState({ open: false, role: null })

  const remove = (role) =>
    confirm({
      title: `Delete role “${role.name}”?`,
      message: 'Roles that are still assigned to users cannot be deleted.',
      confirmLabel: 'Delete role',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.roles.remove(role.id))
          roles.reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  // A non-Super-Admin cannot edit Super Admin or a role they hold (API enforces the same).
  const editable = (role) => !role.is_super_admin && (isSuperAdmin || !hasRole(role.name))

  return (
    <div>
      <PageHeader
        title="Roles"
        description="Roles bundle permissions. Users inherit every permission of their roles."
        actions={
          can(P.CREATE_ROLES) && (
            <Button onClick={() => setEditor({ open: true, role: null })}>
              <Icon name="plus" className="h-4 w-4" /> New role
            </Button>
          )
        }
      />

      {roles.error ? (
        <ErrorState error={roles.error} onRetry={roles.reload} />
      ) : roles.loading ? (
        <div className="grid gap-4 md:grid-cols-2">{[1, 2, 3, 4].map((i) => <Skeleton key={i} className="h-40 w-full rounded-xl" />)}</div>
      ) : (
        <ul className="grid gap-4 md:grid-cols-2">
          {roles.data.map((role) => (
            <li key={role.id} className="card flex flex-col p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="flex items-center gap-2 text-base font-semibold text-slate-900">
                    {role.name}
                    {role.is_system && <Badge>Built-in</Badge>}
                  </h2>
                  <p className="text-sm text-slate-500">
                    {role.users_count} user{role.users_count === 1 ? '' : 's'} · {role.is_super_admin ? 'All permissions' : `${role.permissions.length} permissions`}
                  </p>
                </div>
                <div className="flex gap-1">
                  {can(P.EDIT_ROLES) && editable(role) && (
                    <Button size="xs" variant="secondary" onClick={() => setEditor({ open: true, role })}>
                      Edit
                    </Button>
                  )}
                  {can(P.DELETE_ROLES) && !role.is_system && editable(role) && (
                    <Button size="xs" variant="ghost" className="!text-red-600" onClick={() => remove(role)}>
                      Delete
                    </Button>
                  )}
                </div>
              </div>
              <div className="mt-3 flex flex-wrap gap-1">
                {role.is_super_admin ? (
                  <Badge tone="violet">Unrestricted access</Badge>
                ) : role.permissions.length === 0 ? (
                  <span className="text-sm text-slate-400">No permissions (acts on own data only)</span>
                ) : (
                  role.permissions.slice(0, 12).map((p) => <Badge key={p}>{p}</Badge>)
                )}
                {role.permissions.length > 12 && <Badge tone="slate">+{role.permissions.length - 12} more</Badge>}
              </div>
            </li>
          ))}
        </ul>
      )}

      <RoleEditor
        open={editor.open}
        role={editor.role}
        groups={groups.data ?? []}
        onClose={() => setEditor({ open: false, role: null })}
        onSaved={() => {
          setEditor({ open: false, role: null })
          roles.reload()
        }}
      />
      {dialog}
    </div>
  )
}
