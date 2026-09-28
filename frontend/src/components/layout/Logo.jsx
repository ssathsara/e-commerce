import { Link } from 'react-router-dom'

export function Logo({ to = '/', subtitle, inverted = false }) {
  return (
    <Link to={to} className="flex items-center gap-2 rounded-md" aria-label="ShopSphere home">
      <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white" aria-hidden="true">
        S
      </span>
      <span className={`text-lg font-bold tracking-tight ${inverted ? 'text-white' : 'text-slate-900'}`}>
        Shop<span className={inverted ? 'text-brand-300' : 'text-brand-600'}>Sphere</span>
      </span>
      {subtitle && (
        <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${inverted ? 'bg-white/10 text-slate-300' : 'bg-slate-100 text-slate-500'}`}>
          {subtitle}
        </span>
      )}
    </Link>
  )
}
