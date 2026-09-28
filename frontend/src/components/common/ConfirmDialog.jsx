import { useState } from 'react'
import { Button } from './Button'
import { Modal } from './Modal'

/**
 * Confirmation for destructive actions. `onConfirm` may be async; the dialog stays
 * open with a loading button until it settles, and closes only on success.
 */
export function ConfirmDialog({ open, onClose, onConfirm, title = 'Are you sure?', message, confirmLabel = 'Confirm', tone = 'danger' }) {
  const [busy, setBusy] = useState(false)

  const handleConfirm = async () => {
    setBusy(true)
    try {
      await onConfirm()
      onClose()
    } catch {
      // The caller surfaces the error (toast); keep the dialog open.
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={busy ? undefined : onClose}
      title={title}
      size="sm"
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={handleConfirm} loading={busy}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <p className="text-sm text-slate-600">{message}</p>
    </Modal>
  )
}

/** Small helper for the common "confirm then run" pattern. */
export function useConfirm() {
  const [state, setState] = useState(null)

  return {
    confirm: (options) => setState(options),
    dialog: <ConfirmDialog open={state !== null} onClose={() => setState(null)} onConfirm={state?.onConfirm ?? (() => {})} {...(state ?? {})} />,
  }
}
