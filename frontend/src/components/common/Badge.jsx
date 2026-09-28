import { titleCase } from '../../utils/format'

const TONES = {
  slate: 'bg-slate-100 text-slate-700 ring-slate-500/20',
  green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  red: 'bg-red-50 text-red-700 ring-red-600/20',
  amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
  blue: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  indigo: 'bg-brand-50 text-brand-700 ring-brand-600/20',
  violet: 'bg-violet-50 text-violet-700 ring-violet-600/20',
  rose: 'bg-rose-50 text-rose-700 ring-rose-600/20',
}

export function Badge({ tone = 'slate', children, className = '' }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap ${TONES[tone] ?? TONES.slate} ${className}`}>
      {children}
    </span>
  )
}

/** Badge for an API status value using a tone map from constants/statuses. */
export function StatusBadge({ value, tones, label }) {
  return <Badge tone={tones[value] ?? 'slate'}>{label ?? titleCase(value)}</Badge>
}
