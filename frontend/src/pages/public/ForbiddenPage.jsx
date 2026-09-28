import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'

export function ForbiddenPage({ embedded = false }) {
  return (
    <div className={`flex flex-col items-center justify-center px-6 text-center ${embedded ? 'py-16' : 'min-h-screen'}`}>
      <div className="rounded-full bg-red-50 p-3 text-red-500">
        <Icon name="lock" className="h-7 w-7" />
      </div>
      <h1 className="mt-4 text-2xl font-bold tracking-tight text-slate-900">Access denied</h1>
      <p className="mt-2 max-w-md text-slate-500">Your account does not have permission to view this page. Contact an administrator if you believe this is a mistake.</p>
      <Button to="/" variant="secondary" className="mt-6">
        Back to the store
      </Button>
    </div>
  )
}
