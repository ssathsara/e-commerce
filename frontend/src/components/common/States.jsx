import { Button } from './Button'
import { Icon } from './Icon'

export function EmptyState({ icon = 'box', title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
      <div className="mb-4 rounded-full bg-slate-100 p-3 text-slate-400">
        <Icon name={icon} className="h-7 w-7" />
      </div>
      <h3 className="text-base font-semibold text-slate-900">{title}</h3>
      {description && <p className="mt-1 max-w-sm text-sm text-slate-500">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}

/** Error panel with retry, used whenever a screen's main request fails. */
export function ErrorState({ error, onRetry, title = 'Something went wrong' }) {
  const message = error?.message || 'We could not load this content.'
  const forbidden = error?.status === 403
  const notFound = error?.status === 404

  return (
    <div role="alert" className="flex flex-col items-center justify-center px-6 py-14 text-center">
      <div className="mb-4 rounded-full bg-red-50 p-3 text-red-500">
        <Icon name={forbidden ? 'lock' : 'alert'} className="h-7 w-7" />
      </div>
      <h3 className="text-base font-semibold text-slate-900">
        {forbidden ? 'Access denied' : notFound ? 'Not found' : title}
      </h3>
      <p className="mt-1 max-w-md text-sm text-slate-500">{message}</p>
      {onRetry && !forbidden && !notFound && (
        <Button variant="secondary" size="sm" className="mt-5" onClick={onRetry}>
          <Icon name="refresh" className="h-4 w-4" /> Try again
        </Button>
      )}
    </div>
  )
}
