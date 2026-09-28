import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { DataTable } from '../../src/components/common/DataTable'
import { Modal } from '../../src/components/common/Modal'

const columns = [
  { key: 'name', header: 'Name' },
  { key: 'total', header: 'Total', render: (row) => `$${row.total}` },
]

describe('DataTable', () => {
  it('renders rows', () => {
    render(<DataTable columns={columns} rows={[{ id: 1, name: 'Order A', total: 10 }]} />)

    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: '$10' })).toBeInTheDocument()
  })

  it('shows loading, empty and error states', async () => {
    const { rerender } = render(<DataTable columns={columns} rows={[]} loading />)
    expect(screen.getByRole('status', { name: 'Loading' })).toBeInTheDocument()

    rerender(<DataTable columns={columns} rows={[]} />)
    expect(screen.getByText('Nothing here yet')).toBeInTheDocument()

    const retry = vi.fn()
    rerender(<DataTable columns={columns} rows={[]} error={{ status: 500, message: 'Server exploded' }} onRetry={retry} />)
    expect(screen.getByRole('alert')).toHaveTextContent('Server exploded')
    await userEvent.click(screen.getByRole('button', { name: /try again/i }))
    expect(retry).toHaveBeenCalled()
  })

  it('shows an access-denied message for 403 errors without a retry button', () => {
    render(<DataTable columns={columns} rows={[]} error={{ status: 403, message: 'You do not have permission.' }} onRetry={() => {}} />)

    expect(screen.getByText('Access denied')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /try again/i })).not.toBeInTheDocument()
  })
})

describe('Modal', () => {
  it('is an accessible dialog that closes on Escape', async () => {
    const onClose = vi.fn()
    render(
      <Modal open onClose={onClose} title="Edit coupon">
        <input aria-label="Code" />
      </Modal>,
    )

    const dialog = screen.getByRole('dialog', { name: 'Edit coupon' })
    expect(dialog).toHaveAttribute('aria-modal', 'true')
    expect(screen.getByRole('button', { name: 'Close dialog' })).toHaveFocus()

    await userEvent.keyboard('{Escape}')
    expect(onClose).toHaveBeenCalled()
  })
})
