import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { ADMIN_SEGMENT_LABELS } from '../../constants/adminNavigation'
import { useAuth } from '../../contexts/AuthContext'
import { Breadcrumbs } from '../common/Breadcrumbs'
import { Dropdown, DropdownItem } from '../common/Dropdown'
import { Icon } from '../common/Icon'

function useBreadcrumbs() {
  const { pathname } = useLocation()
  const segments = pathname.split('/').filter(Boolean)

  return segments.map((segment, index) => ({
    label: ADMIN_SEGMENT_LABELS[segment] ?? (/^\d+$/.test(segment) ? `#${segment}` : segment),
    to: '/' + segments.slice(0, index + 1).join('/'),
  }))
}

function Notifications() {
  const [items, setItems] = useState([])
  const { pathname } = useLocation()

  // Refresh on navigation and every minute, so counts reflect actions just taken.
  useEffect(() => {
    let active = true
    const load = () => adminApi.notifications().then((data) => active && setItems(data)).catch(() => {})
    load()
    const timer = setInterval(load, 60_000)
    return () => {
      active = false
      clearInterval(timer)
    }
  }, [pathname])

  const total = items.reduce((sum, item) => sum + item.count, 0)

  return (
    <Dropdown
      width="w-72"
      trigger={({ open, toggle, id }) => (
        <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} className="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label={`Notifications${total ? ` (${total})` : ''}`}>
          <Icon name="bell" className="h-6 w-6" />
          {total > 0 && <span className="absolute top-1.5 right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white" aria-hidden="true" />}
        </button>
      )}
    >
      <p className="border-b border-slate-100 px-4 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Needs attention</p>
      {items.length === 0 && <p className="px-4 py-3 text-sm text-slate-500">You're all caught up.</p>}
      {items.map((item) => (
        <DropdownItem key={item.key} as={Link} to={item.link} className="justify-between">
          <span>{item.label}</span>
          <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${item.count ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-500'}`}>{item.count}</span>
        </DropdownItem>
      ))}
    </Dropdown>
  )
}

export function AdminHeader({ onMenu }) {
  const { user, roles, logout } = useAuth()
  const navigate = useNavigate()
  const breadcrumbs = useBreadcrumbs()

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
      <button type="button" className="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" onClick={onMenu} aria-label="Open navigation">
        <Icon name="menu" className="h-6 w-6" />
      </button>
      <Breadcrumbs items={breadcrumbs} className="hidden min-w-0 flex-1 sm:block" />
      <div className="ml-auto flex items-center gap-1">
        <Notifications />
        <Dropdown
          trigger={({ open, toggle, id }) => (
            <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} className="flex items-center gap-2 rounded-full py-1 pr-2 pl-1 hover:bg-slate-100">
              <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700" aria-hidden="true">
                {user?.name?.charAt(0)}
              </span>
              <span className="hidden text-left sm:block">
                <span className="block text-sm font-medium text-slate-800">{user?.name}</span>
                <span className="block text-xs text-slate-500">{roles.join(', ')}</span>
              </span>
              <Icon name="chevronDown" className="h-4 w-4 text-slate-400" />
            </button>
          )}
        >
          <DropdownItem as={Link} to="/account/profile">
            <Icon name="user" className="h-4 w-4" /> My profile
          </DropdownItem>
          <DropdownItem as={Link} to="/">
            <Icon name="home" className="h-4 w-4" /> View store
          </DropdownItem>
          <div className="my-1 border-t border-slate-100" />
          <DropdownItem
            onClick={async () => {
              await logout()
              navigate('/login')
            }}
          >
            <Icon name="logout" className="h-4 w-4" /> Sign out
          </DropdownItem>
        </Dropdown>
      </div>
    </header>
  )
}
