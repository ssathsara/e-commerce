import { useState } from 'react'
import { formatPrice } from '../../utils/format'
import { Icon } from './Icon'

export function Price({ amount, compareAt, currency, className = '', size = 'md' }) {
  const sizes = { sm: 'text-sm', md: 'text-base', lg: 'text-2xl' }
  const hasDiscount = compareAt && Number(compareAt) > Number(amount)

  return (
    <span className={`inline-flex items-baseline gap-2 ${className}`}>
      <span className={`font-semibold text-slate-900 ${sizes[size]}`}>{formatPrice(amount, currency)}</span>
      {hasDiscount && (
        <span className="text-sm text-slate-400 line-through">
          <span className="sr-only">Original price: </span>
          {formatPrice(compareAt, currency)}
        </span>
      )}
    </span>
  )
}

/** Read-only star rating with an accessible text equivalent. */
export function Rating({ value = 0, count, size = 'h-4 w-4', showValue = false }) {
  const rounded = Math.round(Number(value) * 2) / 2

  return (
    <span className="inline-flex items-center gap-1">
      <span className="flex" aria-hidden="true">
        {[1, 2, 3, 4, 5].map((star) => (
          <Icon
            key={star}
            name="star"
            className={`${size} ${star <= rounded ? 'fill-amber-400 text-amber-400' : star - 0.5 === rounded ? 'fill-amber-200 text-amber-400' : 'text-slate-300'}`}
          />
        ))}
      </span>
      <span className="sr-only">Rated {Number(value).toFixed(1)} out of 5</span>
      {showValue && <span className="text-sm font-medium text-slate-700">{Number(value).toFixed(1)}</span>}
      {count !== undefined && <span className="text-xs text-slate-500">({count})</span>}
    </span>
  )
}

/** Accessible 1-5 star input (radio group). */
export function RatingInput({ value, onChange, name = 'rating' }) {
  const [hover, setHover] = useState(0)

  return (
    <div role="radiogroup" aria-label="Rating" className="flex gap-1" onMouseLeave={() => setHover(0)}>
      {[1, 2, 3, 4, 5].map((star) => (
        <label key={star} className="cursor-pointer" onMouseEnter={() => setHover(star)}>
          <input type="radio" name={name} value={star} checked={value === star} onChange={() => onChange(star)} className="sr-only" />
          <span className="sr-only">{star} star{star > 1 ? 's' : ''}</span>
          <Icon name="star" className={`h-7 w-7 transition-colors ${star <= (hover || value) ? 'fill-amber-400 text-amber-400' : 'text-slate-300'}`} />
        </label>
      ))}
    </div>
  )
}

export function QuantitySelector({ value, onChange, min = 1, max = 99, disabled = false, size = 'md', label = 'Quantity' }) {
  const clamp = (n) => Math.max(min, Math.min(max, n))
  const height = size === 'sm' ? 'h-8' : 'h-10'

  return (
    <div className={`inline-flex ${height} items-stretch overflow-hidden rounded-lg border border-slate-300 bg-white`}>
      <button type="button" className="px-2.5 text-slate-600 hover:bg-slate-50 disabled:opacity-40" onClick={() => onChange(clamp(value - 1))} disabled={disabled || value <= min} aria-label={`Decrease ${label.toLowerCase()}`}>
        <Icon name="minus" className="h-4 w-4" />
      </button>
      <input
        type="number"
        inputMode="numeric"
        aria-label={label}
        className="w-12 border-x border-y-0 border-slate-300 p-0 text-center text-sm [appearance:textfield] focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none"
        value={value}
        min={min}
        max={max}
        disabled={disabled}
        onChange={(event) => {
          const next = parseInt(event.target.value, 10)
          if (!Number.isNaN(next)) onChange(clamp(next))
        }}
      />
      <button type="button" className="px-2.5 text-slate-600 hover:bg-slate-50 disabled:opacity-40" onClick={() => onChange(clamp(value + 1))} disabled={disabled || value >= max} aria-label={`Increase ${label.toLowerCase()}`}>
        <Icon name="plus" className="h-4 w-4" />
      </button>
    </div>
  )
}

/** Image with graceful fallback when the URL is missing or fails to load. */
export function ProductImage({ src, alt = '', className = '', loading = 'lazy' }) {
  const [failed, setFailed] = useState(false)

  if (!src || failed) {
    return (
      <div className={`flex items-center justify-center bg-slate-100 text-slate-300 ${className}`} role="img" aria-label={alt || 'No image available'}>
        <Icon name="photo" className="h-10 w-10" />
      </div>
    )
  }

  return <img src={src} alt={alt} loading={loading} decoding="async" onError={() => setFailed(true)} className={`object-cover ${className}`} />
}

export function PageHeader({ title, description, actions, children }) {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        {children}
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">{title}</h1>
        {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  )
}

export function Card({ title, actions, children, className = '', bodyClassName = 'p-5' }) {
  return (
    <section className={`card ${className}`}>
      {(title || actions) && (
        <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
          {title && <h2 className="text-sm font-semibold text-slate-900">{title}</h2>}
          {actions}
        </header>
      )}
      <div className={bodyClassName}>{children}</div>
    </section>
  )
}
