import { screen, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '../../src/api/authApi'
import { cartApi } from '../../src/api/cartApi'
import { AdminSidebar } from '../../src/components/admin/AdminSidebar'
import { P } from '../../src/constants/permissions'
import { Can, RequirePermission } from '../../src/routes/guards'
import { emptyCart, renderWithProviders } from '../utils'

vi.mock('../../src/api/authApi')
vi.mock('../../src/api/cartApi')
vi.mock('../../src/api/accountApi', () => ({ accountApi: { wishlistIds: vi.fn().mockResolvedValue([]) } }))

function signInAs(roles, permissions) {
  authApi.me.mockResolvedValue({ user: { id: 1, name: 'Test', email: 't@example.com' }, roles, permissions })
}

describe('permission-aware UI', () => {
  beforeEach(() => cartApi.get.mockResolvedValue(emptyCart))

  it('shows only the admin modules staff are allowed to see', async () => {
    signInAs(['Staff'], [P.ACCESS_ADMIN, P.VIEW_PRODUCTS, P.VIEW_ORDERS, P.VIEW_INVENTORY])
    renderWithProviders(<AdminSidebar />)

    const nav = await screen.findByRole('navigation', { name: 'Admin' })
    expect(await within(nav).findByRole('link', { name: 'Products' })).toBeInTheDocument()
    expect(within(nav).getByRole('link', { name: 'Orders' })).toBeInTheDocument()
    expect(within(nav).queryByRole('link', { name: 'Users' })).not.toBeInTheDocument()
    expect(within(nav).queryByRole('link', { name: 'Roles' })).not.toBeInTheDocument()
    expect(within(nav).queryByRole('link', { name: 'Reports' })).not.toBeInTheDocument()
  })

  it('shows every module to a Super Admin', async () => {
    signInAs(['Super Admin'], [])
    renderWithProviders(<AdminSidebar />)

    const nav = await screen.findByRole('navigation', { name: 'Admin' })
    for (const label of ['Products', 'Orders', 'Users', 'Roles', 'Permissions', 'Reports']) {
      expect(await within(nav).findByRole('link', { name: label })).toBeInTheDocument()
    }
  })

  it('hides action buttons without the permission', async () => {
    signInAs(['Staff'], [P.ACCESS_ADMIN, P.VIEW_PRODUCTS])
    renderWithProviders(
      <>
        <p>Loaded</p>
        <Can permission={P.CREATE_PRODUCTS}>
          <button type="button">Add product</button>
        </Can>
        <Can permission={P.VIEW_PRODUCTS}>
          <button type="button">View product</button>
        </Can>
      </>,
    )

    expect(await screen.findByRole('button', { name: 'View product' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add product' })).not.toBeInTheDocument()
  })

  it('renders an access-denied page for routes the user cannot access', async () => {
    signInAs(['Staff'], [P.ACCESS_ADMIN])
    renderWithProviders(
      <RequirePermission permission={P.VIEW_USERS}>
        <p>Secret users page</p>
      </RequirePermission>,
    )

    expect(await screen.findByRole('heading', { name: 'Access denied' })).toBeInTheDocument()
    expect(screen.queryByText('Secret users page')).not.toBeInTheDocument()
  })
})
