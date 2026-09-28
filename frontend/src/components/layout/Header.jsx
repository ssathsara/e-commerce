import { useEffect, useState } from 'react'
import { Link, NavLink, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { useCart } from '../../contexts/CartContext'
import { useToast } from '../../contexts/ToastContext'
import { useWishlist } from '../../contexts/WishlistContext'
import { useCategories } from '../../hooks/useCategories'
import { Dropdown, DropdownItem } from '../common/Dropdown'
import { Icon } from '../common/Icon'
import { Logo } from './Logo'

function SearchForm({ className = '', onSubmitted }) {
  const [params] = useSearchParams()
  const [term, setTerm] = useState(params.get('search') ?? '')
  const navigate = useNavigate()

  const submit = (event) => {
    event.preventDefault()
    const value = term.trim()
    navigate(value ? `/search?search=${encodeURIComponent(value)}` : '/products')
    onSubmitted?.()
  }

  return (
    <form role="search" onSubmit={submit} className={`relative ${className}`}>
      <label htmlFor="site-search" className="sr-only">
        Search products
      </label>
      <input
        id="site-search"
        type="search"
        value={term}
        onChange={(e) => setTerm(e.target.value)}
        placeholder="Search products…"
        className="w-full rounded-full border-slate-300 bg-slate-50 py-2 pr-10 pl-4 text-sm focus:border-brand-500 focus:bg-white focus:ring-brand-500"
      />
      <button type="submit" className="absolute top-1/2 right-1.5 -translate-y-1/2 rounded-full p-1.5 text-slate-500 hover:bg-slate-200 hover:text-slate-700" aria-label="Submit search">
        <Icon name="search" className="h-4 w-4" />
      </button>
    </form>
  )
}

function IconLink({ to, icon, label, count }) {
  return (
    <Link to={to} className="relative rounded-full p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900" aria-label={count ? `${label} (${count} items)` : label}>
      <Icon name={icon} className="h-6 w-6" />
      {count > 0 && (
        <span className="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-[11px] font-semibold text-white" aria-hidden="true">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  )
}

function AccountMenu() {
  const { user, canAccessAdmin, logout } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()

  const signOut = async () => {
    await logout()
    toast.success('You have been signed out.')
    navigate('/')
  }

  return (
    <Dropdown
      trigger={({ open, toggle, id }) => (
        <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} aria-haspopup="true" className="flex items-center gap-1.5 rounded-full p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900">
          <Icon name="user" className="h-6 w-6" />
          <span className="sr-only">Account menu</span>
        </button>
      )}
    >
      <div className="border-b border-slate-100 px-4 py-2.5">
        <p className="truncate text-sm font-medium text-slate-900">{user.name}</p>
        <p className="truncate text-xs text-slate-500">{user.email}</p>
      </div>
      <DropdownItem as={Link} to="/account">
        <Icon name="user" className="h-4 w-4" /> My account
      </DropdownItem>
      <DropdownItem as={Link} to="/account/orders">
        <Icon name="receipt" className="h-4 w-4" /> Orders
      </DropdownItem>
      <DropdownItem as={Link} to="/account/wishlist">
        <Icon name="heart" className="h-4 w-4" /> Wishlist
      </DropdownItem>
      {canAccessAdmin && (
        <DropdownItem as={Link} to="/admin" className="text-brand-700">
          <Icon name="chart" className="h-4 w-4" /> Admin portal
        </DropdownItem>
      )}
      <div className="my-1 border-t border-slate-100" />
      <DropdownItem onClick={signOut}>
        <Icon name="logout" className="h-4 w-4" /> Sign out
      </DropdownItem>
    </Dropdown>
  )
}

export function Header() {
  const { isAuthenticated } = useAuth()
  const { itemCount } = useCart()
  const { count: wishlistCount } = useWishlist()
  const { categories } = useCategories()
  const [mobileOpen, setMobileOpen] = useState(false)
  const location = useLocation()

  useEffect(() => setMobileOpen(false), [location.pathname])

  return (
    <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
      <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:shadow">
        Skip to content
      </a>
      <div className="container-page flex h-16 items-center gap-4">
        <button type="button" className="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" onClick={() => setMobileOpen(true)} aria-label="Open menu" aria-expanded={mobileOpen}>
          <Icon name="menu" className="h-6 w-6" />
        </button>
        <Logo />
        <SearchForm className="mx-auto hidden max-w-xl flex-1 md:block" />
        <div className="ml-auto flex items-center gap-1 md:ml-0">
          {isAuthenticated && <IconLink to="/account/wishlist" icon="heart" label="Wishlist" count={wishlistCount} />}
          <IconLink to="/cart" icon="cart" label="Cart" count={itemCount} />
          {isAuthenticated ? (
            <AccountMenu />
          ) : (
            <Link to="/login" className="ml-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
              Sign in
            </Link>
          )}
        </div>
      </div>

      <nav aria-label="Categories" className="hidden border-t border-slate-100 lg:block">
        <ul className="container-page flex h-11 items-center gap-6 text-sm">
          <li>
            <NavLink to="/products" end className={({ isActive }) => `font-medium ${isActive ? 'text-brand-600' : 'text-slate-600 hover:text-slate-900'}`}>
              All products
            </NavLink>
          </li>
          {categories.slice(0, 7).map((category) => (
            <li key={category.id} className="group relative">
              <NavLink to={`/categories/${category.slug}`} className={({ isActive }) => `font-medium ${isActive ? 'text-brand-600' : 'text-slate-600 hover:text-slate-900'}`}>
                {category.name}
              </NavLink>
              {category.children?.length > 0 && (
                <div className="invisible absolute top-full left-0 z-40 pt-3 opacity-0 transition group-focus-within:visible group-focus-within:opacity-100 group-hover:visible group-hover:opacity-100">
                  <ul className="w-52 rounded-xl border border-slate-200 bg-white py-2 shadow-lg">
                    {category.children.map((child) => (
                      <li key={child.id}>
                        <Link to={`/categories/${child.slug}`} className="block px-4 py-1.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                          {child.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </li>
          ))}
        </ul>
      </nav>

      {mobileOpen && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
          <div className="absolute inset-0 bg-slate-900/40" onClick={() => setMobileOpen(false)} aria-hidden="true" />
          <div className="absolute inset-y-0 left-0 flex w-80 max-w-[85%] animate-slide-up flex-col overflow-y-auto bg-white shadow-xl">
            <div className="flex items-center justify-between border-b border-slate-100 p-4">
              <Logo />
              <button type="button" onClick={() => setMobileOpen(false)} className="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Close menu">
                <Icon name="close" className="h-5 w-5" />
              </button>
            </div>
            <div className="p-4">
              <SearchForm onSubmitted={() => setMobileOpen(false)} />
            </div>
            <nav aria-label="Mobile categories" className="px-2 pb-6">
              <Link to="/products" className="block rounded-lg px-3 py-2 font-medium text-slate-800 hover:bg-slate-50">
                All products
              </Link>
              {categories.map((category) => (
                <div key={category.id}>
                  <Link to={`/categories/${category.slug}`} className="block rounded-lg px-3 py-2 font-medium text-slate-800 hover:bg-slate-50">
                    {category.name}
                  </Link>
                  {category.children?.map((child) => (
                    <Link key={child.id} to={`/categories/${child.slug}`} className="block rounded-lg py-1.5 pr-3 pl-7 text-sm text-slate-600 hover:bg-slate-50">
                      {child.name}
                    </Link>
                  ))}
                </div>
              ))}
            </nav>
          </div>
        </div>
      )}
    </header>
  )
}
