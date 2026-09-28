import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '../../src/api/authApi'
import { cartApi } from '../../src/api/cartApi'
import LoginPage from '../../src/pages/public/LoginPage'
import { ApiError } from '../../src/utils/errors'
import { customerAuth, emptyCart, renderWithProviders } from '../utils'

vi.mock('../../src/api/authApi')
vi.mock('../../src/api/cartApi')
vi.mock('../../src/api/accountApi', () => ({ accountApi: { wishlistIds: vi.fn().mockResolvedValue([]) } }))

const routes = (
  <>
    <Route path="/account" element={<p>Account home</p>} />
    <Route path="/admin" element={<p>Admin home</p>} />
    <Route path="/checkout" element={<p>Checkout page</p>} />
  </>
)

async function fillAndSubmit(email = 'casey@example.com', password = 'Password123!') {
  const user = userEvent.setup()
  await user.type(await screen.findByLabelText(/email/i), email)
  await user.type(screen.getByLabelText(/^password/i), password)
  await user.click(screen.getByRole('button', { name: 'Sign in' }))
}

describe('LoginPage', () => {
  beforeEach(() => {
    authApi.me.mockRejectedValue(new ApiError({ status: 401, message: 'Unauthenticated.' }))
    cartApi.get.mockResolvedValue(emptyCart)
  })

  it('signs a customer in and sends them to their account', async () => {
    authApi.login.mockResolvedValue(customerAuth)
    renderWithProviders(<LoginPage />, { route: '/login', path: '/login', extraRoutes: routes })

    await fillAndSubmit()

    expect(await screen.findByText('Account home')).toBeInTheDocument()
    expect(authApi.login).toHaveBeenCalledWith({ email: 'casey@example.com', password: 'Password123!', remember: false })
  })

  it('sends staff users to the admin portal', async () => {
    authApi.login.mockResolvedValue({ ...customerAuth, roles: ['Staff'], permissions: ['access admin panel', 'view products'] })
    renderWithProviders(<LoginPage />, { route: '/login', path: '/login', extraRoutes: routes })

    await fillAndSubmit()

    expect(await screen.findByText('Admin home')).toBeInTheDocument()
  })

  it('honours a safe redirect parameter', async () => {
    authApi.login.mockResolvedValue(customerAuth)
    renderWithProviders(<LoginPage />, { route: '/login?redirect=%2Fcheckout', path: '/login', extraRoutes: routes })

    await fillAndSubmit()

    expect(await screen.findByText('Checkout page')).toBeInTheDocument()
  })

  it('shows server validation errors next to the field', async () => {
    authApi.login.mockRejectedValue(new ApiError({ status: 422, message: 'Validation failed.', errors: { email: ['These credentials do not match our records.'] } }))
    renderWithProviders(<LoginPage />, { route: '/login', path: '/login', extraRoutes: routes })

    await fillAndSubmit('casey@example.com', 'wrong')

    const emailInput = screen.getByLabelText(/email/i)
    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument()
    expect(emailInput).toHaveAttribute('aria-invalid', 'true')
  })

  it('shows non-field errors such as a deactivated account', async () => {
    authApi.login.mockRejectedValue(new ApiError({ status: 403, message: 'Your account has been deactivated. Please contact support.' }))
    renderWithProviders(<LoginPage />, { route: '/login', path: '/login', extraRoutes: routes })

    await fillAndSubmit()

    expect(await screen.findByRole('alert')).toHaveTextContent('deactivated')
  })

  it('prevents duplicate submissions while signing in', async () => {
    let resolve
    authApi.login.mockReturnValue(new Promise((r) => (resolve = r)))
    renderWithProviders(<LoginPage />, { route: '/login', path: '/login', extraRoutes: routes })

    await fillAndSubmit()
    const button = screen.getByRole('button', { name: 'Sign in' })
    expect(button).toBeDisabled()
    await userEvent.click(button)

    expect(authApi.login).toHaveBeenCalledTimes(1)
    resolve(customerAuth)
    await waitFor(() => expect(screen.getByText('Account home')).toBeInTheDocument())
  })
})
