import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '../../src/api/authApi'
import { cartApi } from '../../src/api/cartApi'
import { productApi } from '../../src/api/productApi'
import ProductListPage from '../../src/pages/public/ProductListPage'
import { ApiError } from '../../src/utils/errors'
import { emptyCart, product, renderWithProviders } from '../utils'

vi.mock('../../src/api/authApi')
vi.mock('../../src/api/cartApi')
vi.mock('../../src/api/productApi')
vi.mock('../../src/api/accountApi', () => ({ accountApi: { wishlistIds: vi.fn().mockResolvedValue([]) } }))

const meta = (total) => ({ current_page: 1, last_page: Math.max(1, Math.ceil(total / 12)), per_page: 12, total, from: total ? 1 : null, to: Math.min(total, 12) })

describe('ProductListPage', () => {
  beforeEach(() => {
    authApi.me.mockRejectedValue(new ApiError({ status: 401, message: 'Unauthenticated.' }))
    cartApi.get.mockResolvedValue(emptyCart)
    productApi.categories.mockResolvedValue([])
    productApi.brands.mockResolvedValue([{ id: 1, name: 'Stride', slug: 'stride', products_count: 3 }])
  })

  it('renders products returned by the API with pricing and stock badges', async () => {
    productApi.list.mockResolvedValue({ items: [product(), product({ id: 2, name: 'Trail Boot', slug: 'trail-boot', in_stock: false, is_low_stock: false, compare_at_price: null, discount_percentage: 0 })], meta: meta(2) })

    renderWithProviders(<ProductListPage />, { route: '/products', path: '/products' })

    expect(await screen.findByRole('heading', { name: 'Cloud Runner' })).toBeInTheDocument()
    expect(screen.getByText('2 products')).toBeInTheDocument()
    expect(screen.getByText('-20%')).toBeInTheDocument()
    expect(screen.getByText('Out of stock')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add Cloud Runner to cart' })).toBeInTheDocument()
  })

  it('sends filters and sorting to the server instead of filtering locally', async () => {
    productApi.list.mockResolvedValue({ items: [product()], meta: meta(1) })
    const user = userEvent.setup()

    renderWithProviders(<ProductListPage />, { route: '/search?search=runner', path: '/search' })
    await screen.findByRole('heading', { name: 'Cloud Runner' })

    await user.selectOptions(screen.getByLabelText('Sort products'), 'price_asc')
    await user.click(screen.getAllByRole('checkbox', { name: /Stride/ })[0])

    expect(productApi.list).toHaveBeenLastCalledWith(expect.objectContaining({ search: 'runner', sort: 'price_asc', brand: 'stride', page: 1 }))
  })

  it('shows an empty state when nothing matches', async () => {
    productApi.list.mockResolvedValue({ items: [], meta: meta(0) })

    renderWithProviders(<ProductListPage />, { route: '/products', path: '/products' })

    expect(await screen.findByText('No products found')).toBeInTheDocument()
  })

  it('shows an error state with retry when the request fails', async () => {
    productApi.list.mockRejectedValueOnce(new ApiError({ status: 0, message: 'Unable to reach the server.' })).mockResolvedValue({ items: [product()], meta: meta(1) })
    const user = userEvent.setup()

    renderWithProviders(<ProductListPage />, { route: '/products', path: '/products' })

    expect(await screen.findByText('Unable to reach the server.')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /try again/i }))
    expect(await screen.findByRole('heading', { name: 'Cloud Runner' })).toBeInTheDocument()
  })
})
