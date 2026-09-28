import { NavLink, Outlet } from 'react-router-dom'
import { Icon } from '../components/common/Icon'
import { useAuth } from '../contexts/AuthContext'

const LINKS = [
  { to: '/account', label: 'Overview', icon: 'home', end: true },
  { to: '/account/orders', label: 'Orders', icon: 'receipt' },
  { to: '/account/wishlist', label: 'Wishlist', icon: 'heart' },
  { to: '/account/addresses', label: 'Addresses', icon: 'mapPin' },
  { to: '/account/profile', label: 'Profile', icon: 'user' },
  { to: '/account/settings', label: 'Settings', icon: 'cog' },
]

export function CustomerLayout() {
  const { user } = useAuth()

  return (
    <div className="container-page py-8">
      <div className="mb-6">
        <p className="text-sm text-slate-500">Signed in as</p>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">{user?.name}</h1>
      </div>
      <div className="grid gap-8 lg:grid-cols-[220px_1fr]">
        <nav aria-label="Account">
          <ul className="-mx-1 flex gap-1 overflow-x-auto pb-2 lg:mx-0 lg:flex-col lg:overflow-visible lg:pb-0">
            {LINKS.map((link) => (
              <li key={link.to} className="shrink-0">
                <NavLink
                  to={link.to}
                  end={link.end}
                  className={({ isActive }) =>
                    `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap ${isActive ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-white hover:text-slate-900'}`
                  }
                >
                  <Icon name={link.icon} className="h-4 w-4" />
                  {link.label}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>
        <div className="min-w-0">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
