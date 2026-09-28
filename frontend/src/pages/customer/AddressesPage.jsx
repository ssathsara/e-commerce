import { useState } from 'react'
import { accountApi } from '../../api/accountApi'
import { Badge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { Icon } from '../../components/common/Icon'
import { Modal } from '../../components/common/Modal'
import { Skeleton } from '../../components/common/Skeleton'
import { EmptyState, ErrorState } from '../../components/common/States'
import { useToast } from '../../contexts/ToastContext'
import { AddressForm } from '../../features/account/AddressForm'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { formatAddress } from '../../utils/format'

export default function AddressesPage() {
  useDocumentTitle('Addresses')
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const { data: addresses, loading, error, reload } = useAsync(() => accountApi.addresses(), [])
  const [editing, setEditing] = useState(null) // null = closed, {} = new, address = edit

  const save = async (values) => {
    if (editing?.id) {
      await accountApi.updateAddress(editing.id, values)
      toast.success('Address updated.')
    } else {
      await accountApi.createAddress(values)
      toast.success('Address added.')
    }
    setEditing(null)
    reload()
  }

  const remove = (address) =>
    confirm({
      title: 'Delete address?',
      message: 'This address will be removed from your account. Past orders are not affected.',
      confirmLabel: 'Delete',
      onConfirm: async () => {
        try {
          await accountApi.deleteAddress(address.id)
          toast.success('Address deleted.')
          reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-slate-900">Saved addresses</h2>
        <Button size="sm" onClick={() => setEditing({})}>
          <Icon name="plus" className="h-4 w-4" /> Add address
        </Button>
      </div>

      {error ? (
        <ErrorState error={error} onRetry={reload} />
      ) : loading ? (
        <div className="grid gap-4 sm:grid-cols-2">
          {[1, 2].map((i) => (
            <Skeleton key={i} className="h-40 w-full rounded-xl" />
          ))}
        </div>
      ) : addresses.length === 0 ? (
        <div className="card">
          <EmptyState icon="mapPin" title="No addresses yet" description="Add an address to speed up checkout." action={<Button onClick={() => setEditing({})}>Add address</Button>} />
        </div>
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2">
          {addresses.map((address) => (
            <li key={address.id} className="card flex flex-col p-5">
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-semibold text-slate-900">
                  {address.first_name} {address.last_name}
                </p>
                {address.label && <Badge>{address.label}</Badge>}
                {address.is_default_shipping && <Badge tone="indigo">Default shipping</Badge>}
                {address.is_default_billing && <Badge tone="blue">Default billing</Badge>}
              </div>
              <p className="mt-2 flex-1 text-sm whitespace-pre-line text-slate-600">{formatAddress(address)}</p>
              {address.phone && <p className="mt-1 text-sm text-slate-500">{address.phone}</p>}
              <div className="mt-4 flex gap-4 border-t border-slate-100 pt-3">
                <Button variant="link" className="text-sm" onClick={() => setEditing(address)}>
                  Edit
                </Button>
                <Button variant="link" className="text-sm !text-red-600" onClick={() => remove(address)}>
                  Delete
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing?.id ? 'Edit address' : 'Add address'} size="lg">
        {editing !== null && <AddressForm address={editing?.id ? editing : null} onSave={save} onCancel={() => setEditing(null)} />}
      </Modal>
      {dialog}
    </div>
  )
}
