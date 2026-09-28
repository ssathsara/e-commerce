import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '../../src/api/authApi'
import { cartApi } from '../../src/api/cartApi'
import { AddToCartPanel } from '../../src/features/products/AddToCartPanel'
import { ApiError } from '../../src/utils/errors'
import { emptyCart, product, renderWithProviders } from '../utils'

vi.mock('../../src/api/authApi')
vi.mock('../../src/api/cartApi')
vi.mock('../../src/api/accountApi', () => ({ accountApi: { wishlistIds: vi.fn().mockResolvedValue([]) } }))

describe('AddToCartPanel', () => {
  beforeEach(() => {
    authApi.me.mockRejectedValue(new ApiError({ status: 401, message: 'Unauthenticated.' }))
    cartApi.get.mockResolvedValue(emptyCart)
    cartApi.addItem.mockResolvedValue({ ...emptyCart, item_count: 1, token: 'guest-token' })
  })

  it('caps the quantity at the available stock', async () => {
    const user = userEvent.setup()
    renderWithProviders(<AddToCartPanel product={product({ stock_quantity: 2 })} />)

    const increase = screen.getByRole('button', { name: 'Increase quantity' })
    await user.click(increase)
    expect(screen.getByLabelText('Quantity')).toHaveValue(2)
    expect(increase).toBeDisabled()
    expect(screen.getByText('Only 2 left in stock')).toBeInTheDocument()
  })

  it('adds the selected quantity and stores the guest cart token', async () => {
    const user = userEvent.setup()
    renderWithProviders(<AddToCartPanel product={product({ stock_quantity: 10 })} />)

    await user.click(screen.getByRole('button', { name: 'Increase quantity' }))
    await user.click(screen.getByRole('button', { name: 'Add to cart' }))

    // Only ids and quantity are sent — never a price.
    expect(cartApi.addItem).toHaveBeenCalledWith({ productId: 1, variantId: null, quantity: 2 }, null)
    expect(await screen.findByText(/added to your cart/i)).toBeInTheDocument()
    expect(window.localStorage.getItem('shop.cartToken')).toBe('guest-token')
  })

  it('requires an in-stock variant and uses its price and stock', async () => {
    const user = userEvent.setup()
    const variants = [
      { id: 11, name: 'Small', price: 100, compare_at_price: null, stock_quantity: 0, in_stock: false },
      { id: 12, name: 'Large', price: 130, compare_at_price: null, stock_quantity: 4, in_stock: true },
    ]
    renderWithProviders(<AddToCartPanel product={product({ has_variants: true, variants, compare_at_price: null })} />)

    expect(screen.getByRole('radio', { name: /Small/ })).toBeDisabled()
    expect(screen.getByRole('radio', { name: 'Large' })).toBeChecked()
    expect(screen.getByText('$130.00')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Add to cart' }))
    expect(cartApi.addItem).toHaveBeenCalledWith({ productId: 1, variantId: 12, quantity: 1 }, null)
  })

  it('disables purchasing when the product is out of stock', () => {
    renderWithProviders(<AddToCartPanel product={product({ status: 'out_of_stock', stock_quantity: 0, in_stock: false })} />)

    expect(screen.getByRole('button', { name: /unavailable/i })).toBeDisabled()
    expect(screen.getByText('Out of stock')).toBeInTheDocument()
  })

  it('shows the server error when the stock changed meanwhile', async () => {
    cartApi.addItem.mockRejectedValue(new ApiError({ status: 409, message: 'Only 1 unit(s) of "Cloud Runner" are available.' }))
    const user = userEvent.setup()
    renderWithProviders(<AddToCartPanel product={product({ stock_quantity: 5 })} />)

    await user.click(screen.getByRole('button', { name: 'Add to cart' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Only 1 unit(s)')
  })
})
