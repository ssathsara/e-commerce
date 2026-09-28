import { NavLink } from 'react-router-dom'
import { ADMIN_NAVIGATION } from '../../constants/adminNavigation'
import { useAuth } from '../../contexts/AuthContext'
import { Icon } from '../common/Icon'
import { Logo } from '../layout/Logo'

export function AdminSidebar({ onNavigate }) {
  const { canAny } = useAuth()

  const sections = ADMIN_NAVIGATION.map((section) => ({
    ...section,
    items: section.items.filter((item) => canAny(Array.isArray(item.permission) ? item.permission : [item.permission])),
  })).filter((section) => section.items.length > 0)

  return (
    <div className="flex h-full flex-col bg-slate-900 text-slate-300">
      <div className="flex h-16 items-center border-b border-white/10 px-5">
        <Logo to="/admin" subtitle="Admin" inverted />
      </div>
      <nav aria-label="Admin" className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        {sections.map((section) => (
          <div key={section.label}>
            <p className="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{section.label}</p>
            <ul className="space-y-0.5">
              {section.items.map((item) => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.end}
                    onClick={onNavigate}
                    className={({ isActive }) =>
                      `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${isActive ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'}`
                    }
                  >
                    <Icon name={item.icon} className="h-5 w-5 shrink-0" />
                    {item.label}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
      <div className="border-t border-white/10 p-3">
        <NavLink to="/" className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-white/5 hover:text-white">
          <Icon name="chevronLeft" className="h-4 w-4" /> Back to store
        </NavLink>
      </div>
    </div>
  )
}
