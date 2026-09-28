import { Button } from '../../components/common/Button'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'

export default function NotFoundPage({ embedded = false }) {
  useDocumentTitle('Page not found')

  return (
    <div className={`flex flex-col items-center justify-center px-6 text-center ${embedded ? 'py-16' : 'min-h-[60vh] py-20'}`}>
      <p className="text-sm font-semibold text-brand-600">404</p>
      <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-900">Page not found</h1>
      <p className="mt-2 max-w-md text-slate-500">Sorry, we could not find the page you are looking for.</p>
      <Button to={embedded ? '/admin' : '/'} className="mt-6">
        {embedded ? 'Back to dashboard' : 'Back to home'}
      </Button>
    </div>
  )
}
