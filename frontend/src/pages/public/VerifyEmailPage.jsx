import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { authApi } from '../../api/authApi'
import { Button } from '../../components/common/Button'
import { Spinner } from '../../components/common/Spinner'
import { useAuth } from '../../contexts/AuthContext'
import { AuthCard } from '../../features/auth/AuthCard'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'

/**
 * Landing page for the verification email. The link carries a signed API path;
 * the signed-in user's session completes verification.
 */
export default function VerifyEmailPage() {
  useDocumentTitle('Verify email')
  const [params] = useSearchParams()
  const { isLoading, isAuthenticated, setAuthenticated } = useAuth()
  const [state, setState] = useState({ status: 'pending', message: null })
  const started = useRef(false)
  const path = params.get('path')

  useEffect(() => {
    if (isLoading || !isAuthenticated || !path || started.current) return
    started.current = true

    authApi
      .verifyEmail(path)
      .then((payload) => {
        setAuthenticated(payload)
        setState({ status: 'success', message: 'Your email address has been verified. Thank you!' })
      })
      .catch((error) => setState({ status: 'error', message: error.status === 403 ? 'This verification link is invalid or has expired.' : error.message }))
  }, [isLoading, isAuthenticated, path, setAuthenticated])

  if (!path) {
    return <AuthCard title="Invalid link" subtitle="This verification link is incomplete." />
  }

  if (!isLoading && !isAuthenticated) {
    return (
      <AuthCard title="Sign in to verify" subtitle="Please sign in to the account you are verifying, then open the link again.">
        <Button to={`/login?redirect=${encodeURIComponent(`/verify-email?path=${encodeURIComponent(path)}`)}`} block>
          Sign in
        </Button>
      </AuthCard>
    )
  }

  return (
    <AuthCard title="Email verification">
      {state.status === 'pending' && (
        <p className="flex items-center gap-3 text-sm text-slate-600">
          <Spinner /> Verifying your email address…
        </p>
      )}
      {state.status !== 'pending' && (
        <div className="space-y-4">
          <p role="status" className={`rounded-lg p-3 text-sm ${state.status === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
            {state.message}
          </p>
          <Button to="/account" block>
            Go to my account
          </Button>
        </div>
      )}
    </AuthCard>
  )
}
