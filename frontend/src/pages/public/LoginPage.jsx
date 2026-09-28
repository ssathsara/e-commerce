import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Button } from '../../components/common/Button'
import { Checkbox, Input } from '../../components/forms/Fields'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { AuthCard, FormAlert } from '../../features/auth/AuthCard'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'
import { homePathFor, safeRedirect } from '../../routes/guards'

export default function LoginPage() {
  useDocumentTitle('Sign in')
  const { login } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [formError, setFormError] = useState(null)
  const form = useForm({ email: '', password: '', remember: false })

  const onSubmit = form.submit(
    async (values) => {
      setFormError(null)
      const auth = await login(values)
      toast.success(`Welcome back, ${auth.user.name.split(' ')[0]}!`)

      const canAccessAdmin = auth.roles.includes('Super Admin') || auth.permissions.includes('access admin panel')
      navigate(safeRedirect(params.get('redirect'), homePathFor({ canAccessAdmin })), { replace: true })
    },
    { onError: (error) => !error.isValidation && setFormError(error.message) },
  )

  return (
    <AuthCard
      title="Sign in"
      subtitle="Welcome back! Please enter your details."
      footer={
        <>
          New here?{' '}
          <Link to={`/register${params.get('redirect') ? `?redirect=${encodeURIComponent(params.get('redirect'))}` : ''}`} className="font-medium text-brand-600 hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      <FormAlert message={formError} />
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <Input label="Email" type="email" autoComplete="email" required autoFocus {...form.bind('email')} />
        <Input label="Password" type="password" autoComplete="current-password" required {...form.bind('password')} />
        <div className="flex items-center justify-between">
          <Checkbox label="Remember me" {...form.bindCheckbox('remember')} />
          <Link to="/forgot-password" className="text-sm font-medium text-brand-600 hover:underline">
            Forgot password?
          </Link>
        </div>
        <Button type="submit" block size="lg" loading={form.submitting}>
          Sign in
        </Button>
      </form>
      {import.meta.env.DEV && (
        <p className="mt-6 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
          Local development accounts: superadmin@, admin@, staff@, customer@example.com — password <code>Password123!</code>
        </p>
      )}
    </AuthCard>
  )
}
