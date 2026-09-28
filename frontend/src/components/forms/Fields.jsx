import { forwardRef, useId } from 'react'

const control =
  'block w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500'
const invalid = 'border-red-400 focus:border-red-500 focus:ring-red-500'

/**
 * Label + control + hint/error, wired with aria-describedby / aria-invalid so screen
 * readers announce errors with the field.
 */
export function FormField({ label, error, hint, required, children, className = '', id: providedId }) {
  const generatedId = useId()
  const id = providedId ?? generatedId
  const describedBy = [error ? `${id}-error` : null, hint ? `${id}-hint` : null].filter(Boolean).join(' ') || undefined

  return (
    <div className={className}>
      {label && (
        <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700">
          {label}
          {required && (
            <span className="ml-0.5 text-red-500" aria-hidden="true">
              *
            </span>
          )}
        </label>
      )}
      {children({ id, describedBy, invalid: Boolean(error) })}
      {hint && !error && (
        <p id={`${id}-hint`} className="mt-1 text-xs text-slate-500">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="mt-1 text-xs font-medium text-red-600">
          {error}
        </p>
      )}
    </div>
  )
}

export const Input = forwardRef(function Input({ label, error, hint, required, className, id, ...props }, ref) {
  return (
    <FormField label={label} error={error} hint={hint} required={required} className={className} id={id}>
      {({ id: fieldId, describedBy, invalid: isInvalid }) => (
        <input
          ref={ref}
          id={fieldId}
          required={required}
          aria-invalid={isInvalid || undefined}
          aria-describedby={describedBy}
          className={`${control} ${isInvalid ? invalid : ''}`}
          {...props}
        />
      )}
    </FormField>
  )
})

export function Textarea({ label, error, hint, required, className, rows = 4, ...props }) {
  return (
    <FormField label={label} error={error} hint={hint} required={required} className={className}>
      {({ id, describedBy, invalid: isInvalid }) => (
        <textarea id={id} rows={rows} required={required} aria-invalid={isInvalid || undefined} aria-describedby={describedBy} className={`${control} ${isInvalid ? invalid : ''}`} {...props} />
      )}
    </FormField>
  )
}

/** options: [{ value, label }] */
export function Select({ label, error, hint, required, className, options = [], placeholder, ...props }) {
  return (
    <FormField label={label} error={error} hint={hint} required={required} className={className}>
      {({ id, describedBy, invalid: isInvalid }) => (
        <select id={id} required={required} aria-invalid={isInvalid || undefined} aria-describedby={describedBy} className={`${control} ${isInvalid ? invalid : ''}`} {...props}>
          {placeholder !== undefined && <option value="">{placeholder}</option>}
          {options.map((option) => (
            <option key={option.value} value={option.value} disabled={option.disabled}>
              {option.label}
            </option>
          ))}
        </select>
      )}
    </FormField>
  )
}

export function Checkbox({ label, description, error, className = '', ...props }) {
  const id = useId()

  return (
    <div className={className}>
      <div className="flex items-start gap-3">
        <input id={id} type="checkbox" className="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" aria-describedby={description ? `${id}-desc` : undefined} {...props} />
        <div className="text-sm">
          <label htmlFor={id} className="font-medium text-slate-700">
            {label}
          </label>
          {description && (
            <p id={`${id}-desc`} className="text-slate-500">
              {description}
            </p>
          )}
        </div>
      </div>
      {error && <p className="mt-1 text-xs font-medium text-red-600">{error}</p>}
    </div>
  )
}

/** Switch rendered as a checkbox with role="switch" for correct semantics. */
export function Toggle({ label, checked, onChange, disabled, description }) {
  const id = useId()

  return (
    <div className="flex items-center justify-between gap-4">
      <div className="text-sm">
        <label htmlFor={id} className="font-medium text-slate-700">
          {label}
        </label>
        {description && <p className="text-slate-500">{description}</p>}
      </div>
      <button
        id={id}
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50 ${checked ? 'bg-brand-600' : 'bg-slate-300'}`}
      >
        <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform ${checked ? 'translate-x-5' : 'translate-x-0.5'}`} />
      </button>
    </div>
  )
}

/** Search input with a leading icon; used in filter bars. */
export function SearchInput({ value, onChange, placeholder = 'Search…', label = 'Search', className = '' }) {
  const id = useId()

  return (
    <div className={`relative ${className}`}>
      <label className="sr-only" htmlFor={id}>
        {label}
      </label>
      <svg aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
        <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
      </svg>
      <input id={id} type="search" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className={`${control} pl-9`} />
    </div>
  )
}
