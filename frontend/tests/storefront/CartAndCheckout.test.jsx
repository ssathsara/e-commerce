import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { accountApi } from '../../src/api/accountApi'
import { authApi } from '../../src/api/authApi'
import { cartApi } from '../../src/api/cartApi'
import { orderApi } from '../../src/api/orderApi'
import CartPage from '../../src/pages/public/CartPage'
import CheckoutPage from '../../src/pages/public/CheckoutPage'
import { ApiError } from '../../src/utils/errors'
import { customerAuth, emptyCart, renderWithProviders } from '../utils'

vi.mock('../../src/api/authApi')
vi.mock('../../src/api/cartApi')
vi.mock('../../src/api/orderApi')
vi.mock('../../src/api/accountApi')

const cart = {
  ...emptyCart,
  items: [
    {
      id: 5,
      quantity: 2,
      unit_price: 25,
      line_total: 50,
      available_stock: 10,
      issue: null,
      product: { id: 1, name: 'Linen Throw', slug: 'linen-throw', sku: 'DEC-1', image: null },
      variant: null,
    },
  ],
  item_count: 2,
  totals: { subtotal: 50, discount: 0, shipping: 5, tax: 5, total: 60 },
}

const address = { id: 3, first_name: 'Casey', last_name: 'Customer', line1: '1 Main St', city: 'Springfield', postal_code: '12345', country: 'US', is_default_shipping: true, is_default_billing: true }

const options = {
  shipping_methods: [
    { code: 'standard', name: 'Standard Shipping', description: '3-5 days', cost: 5, is_free: false },
    { code: 'express', name: 'Express Shipping', description: '1-2 days', cost: 15, is_free: false },
  ],
  payment_methods: [
    { code: 'card', name: 'Credit / Debit Card', is_online: true },
    { code: 'cash_on_delivery', name: 'Cash on Delivery', is_online: false },
  ],
  free_shipping_threshold: 100,
  tax_rate: 10,
  currency: 'USD',
}

describe('CartPage', () => {
  beforeEach(() => {
    accountApi.wishlistIds.mockResolvedValue([])
  })

  it('shows server-computed totals and lets a signed-in customer proceed', async () => {
    authApi.me.mockResolvedValue(customerAuth)
    cartApi.get.mockResolvedValue(cart)
    renderWithProviders(<CartPage />)

    expect(await screen.findByText('Linen Throw')).toBeInTheDocument()
    expect(screen.getByText('$60.00')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Proceed to checkout' })).toHaveAttribute('href', '/checkout')
  })

  it('asks guests to sign in before checking out', async () => {
    authApi.me.mockRejectedValue(new ApiError({ status: 401, message: 'Unauthenticated.' }))
    cartApi.get.mockResolvedValue(cart)
    renderWithProviders(<CartPage />)

    expect(await screen.findByRole('link', { name: 'Sign in to check out' })).toHaveAttribute('href', '/login?redirect=/checkout')
  })

  it('blocks checkout while items need attention', async () => {
    authApi.me.mockResolvedValue(customerAuth)
    cartApi.get.mockResolvedValue({ ...cart, has_issues: true, items: [{ ...cart.items[0], issue: 'Only 1 left in stock.' }] })
    renderWithProviders(<CartPage />)

    expect(await screen.findByText('Only 1 left in stock.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Proceed to checkout' })).toBeDisabled()
  })

  it('shows the empty state', async () => {
    authApi.me.mockResolvedValue(customerAuth)
    cartApi.get.mockResolvedValue(emptyCart)
    renderWithProviders(<CartPage />)

    expect(await screen.findByText('Your cart is empty')).toBeInTheDocument()
  })
})

describe('CheckoutPage', () => {
  beforeEach(() => {
    authApi.me.mockResolvedValue(customerAuth)
    accountApi.wishlistIds.mockResolvedValue([])
    accountApi.addresses.mockResolvedValue([address])
    cartApi.get.mockResolvedValue(cart)
    cartApi.checkoutOptions.mockResolvedValue(options)
  })

  async function goToReview(user) {
    await user.click(await screen.findByRole('button', { name: 'Continue to delivery' }))
    await user.click(screen.getByRole('button', { name: 'Continue to payment' }))
    await user.click(screen.getByRole('button', { name: 'Review order' }))
  }

  it('re-prices the cart when a shipping method is chosen', async () => {
    const user = userEvent.setup()
    renderWithProviders(<CheckoutPage />)

    await user.click(await screen.findByRole('button', { name: 'Continue to delivery' }))
    await user.click(screen.getByRole('radio', { name: /Express Shipping/ }))

    await waitFor(() => expect(cartApi.get).toHaveBeenCalledWith('express'))
  })

  it('places the order and reuses the same idempotency key when retrying after a failure', async () => {
    const user = userEvent.setup()
    orderApi.place
      .mockRejectedValueOnce(new ApiError({ status: 0, message: 'Unable to reach the server.' }))
      .mockResolvedValueOnce({ order: { id: 42 }, created: true, paymentError: null })

    renderWithProviders(<CheckoutPage />, { extraRoutes: <Route path="/checkout/complete/:orderId" element={<p>Order complete</p>} /> })
    await goToReview(user)

    const pay = screen.getByRole('button', { name: 'Pay $60.00' })
    await user.click(pay)
    await screen.findByText('Unable to reach the server.')
    await user.click(screen.getByRole('button', { name: 'Pay $60.00' }))

    expect(await screen.findByText('Order complete')).toBeInTheDocument()
    expect(orderApi.place).toHaveBeenCalledTimes(2)
    const [first, second] = orderApi.place.mock.calls.map(([payload]) => payload)
    expect(second.idempotency_key).toBe(first.idempotency_key)
    expect(first).toMatchObject({ shipping_address_id: 3, billing_same_as_shipping: true, payment_method: 'card', payment_token: 'tok_visa', shipping_method: 'standard' })
    // No monetary values are sent to the server.
    expect(first).not.toHaveProperty('total')
    expect(first).not.toHaveProperty('price')
  })

  it('takes the customer to the order page when the card is declined', async () => {
    const user = userEvent.setup()
    orderApi.place.mockResolvedValue({ order: { id: 9 }, created: true, paymentError: 'Your card was declined.' })

    renderWithProviders(<CheckoutPage />, { extraRoutes: <Route path="/account/orders/:orderId" element={<p>Order page</p>} /> })
    await goToReview(user)
    await user.click(screen.getByRole('button', { name: 'Pay $60.00' }))

    expect(await screen.findByText('Order page')).toBeInTheDocument()
  })
})
