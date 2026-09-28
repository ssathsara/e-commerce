export function Spinner({ className = 'h-5 w-5', label }) {
  return (
    <span role={label ? 'status' : undefined} className="inline-flex items-center">
      <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
      </svg>
      {label && <span className="sr-only">{label}</span>}
    </span>
  )
}

export function PageSpinner({ label = 'Loading…' }) {
  return (
    <div className="flex min-h-[40vh] items-center justify-center text-brand-600">
      <Spinner className="h-8 w-8" label={label} />
    </div>
  )
}
