const DEFAULT_CURRENCY = 'USD'
const formatters = new Map()

/** Formats a major-unit amount (e.g. 19.99) as currency. */
export function formatPrice(amount, currency = DEFAULT_CURRENCY) {
  if (amount === null || amount === undefined || Number.isNaN(Number(amount))) return '—'

  if (!formatters.has(currency)) {
    formatters.set(currency, new Intl.NumberFormat('en-US', { style: 'currency', currency }))
  }

  return formatters.get(currency).format(Number(amount))
}

export function formatNumber(value) {
  return new Intl.NumberFormat('en-US').format(Number(value ?? 0))
}

export function formatDate(value, options = { dateStyle: 'medium' }) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-US', options).format(new Date(value))
}

export function formatDateTime(value) {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' })
}

export function titleCase(value = '') {
  return String(value)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

/** Collision-resistant id for idempotency keys. */
export function uuid() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID()
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
  })
}

export function formatAddress(address) {
  if (!address) return ''
  return [
    address.line1,
    address.line2,
    [address.city, address.state, address.postal_code].filter(Boolean).join(', '),
    address.country,
  ]
    .filter(Boolean)
    .join('\n')
}
