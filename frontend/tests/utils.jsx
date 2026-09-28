import { render } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { AuthProvider } from '../src/contexts/AuthContext'
import { CartProvider } from '../src/contexts/CartContext'
import { ToastProvider } from '../src/contexts/ToastContext'
import { WishlistProvider } from '../src/contexts/WishlistContext'

/**
 * Renders UI inside the real providers and a router. API modules are mocked per
 * test file with vi.mock, so these are integration tests of UI + state logic.
 *
 * `path` is the route pattern (for useParams), `route` the URL being visited.
 */
export function renderWithProviders(ui, { route = '/', path = '*', extraRoutes = null } = {}) {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <ToastProvider>
        <AuthProvider>
          <CartProvider>
            <WishlistProvider>
              <Routes>
                <Route path={path} element={ui} />
                {extraRoutes}
              </Routes>
            </WishlistProvider>
          </CartProvider>
        </AuthProvider>
      </ToastProvider>
    </MemoryRouter>,
  )
}

export const customerAuth = {
  user: { id: 7, name: 'Casey Customer', email: 'casey@example.com', email_verified: true },
  roles: ['Customer'],
  permissions: [],
}

export const emptyCart = {
  id: 1,
  token: null,
  items: [],
  item_count: 0,
  coupon: null,
  shipping_method: 'standard',
  totals: { subtotal: 0, discount: 0, shipping: 0, tax: 0, total: 0 },
  tax_rate: 10,
  currency: 'USD',
  has_issues: false,
}

export function product(overrides = {}) {
  return {
    id: 1,
    name: 'Cloud Runner',
    slug: 'cloud-runner',
    sku: 'SHO-1',
    short_description: 'Comfortable shoes',
    description: 'Long description',
    price: 120,
    compare_at_price: 150,
    discount_percentage: 20,
    status: 'active',
    in_stock: true,
    stock_quantity: 3,
    is_low_stock: true,
    has_variants: false,
    rating_average: 4.5,
    rating_count: 2,
    image: null,
    images: [],
    variants: [],
    brand: { id: 1, name: 'Stride', slug: 'stride' },
    category: { id: 1, name: 'Shoes', slug: 'shoes' },
    ...overrides,
  }
}
