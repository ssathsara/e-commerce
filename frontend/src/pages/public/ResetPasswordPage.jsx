import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { authApi } from '../../api/authApi'
import { Button } from '../../components/common/Button'
import { Input } from '../../components/forms/Fields'
import { useToast } from '../../contexts/ToastContext'
import { AuthCard, FormAlert } from '../../features/auth/AuthCard'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'

export default function ResetPasswordPage() {
  useDocumentTitle('Choose a new password')
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const toast = useToast()
  const [formError, setFormError] = useState(null)
  const token = params.get('token') ?? ''
  const form = useForm({ email: params.get('email') ?? '', password: '', password_confirmation: '' })

  const onSubmit = form.submit(
    async (values) => {
      setFormError(null)
      const message = await authApi.resetPassword({ ...values, token })
      toast.success(message)
      navigate('/login', { replace: true })
    },
    { onError: (error) => !error.isValidation && setFormError(error.message) },
  )

  if (!token) {
    return (
      <AuthCard title="Invalid reset link" subtitle="This password reset link is incomplete or has expired.">
        <Button to="/forgot-password" block>
          Request a new link
        </Button>
      </AuthCard>
    )
  }

  return (
    <AuthCard
      title="Choose a new password"
      footer={
        <Link to="/login" className="font-medium text-brand-600 hover:underline">
          Back to sign in
        </Link>
      }
    >
      <FormAlert message={formError} />
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <Input label="Email" type="email" autoComplete="email" required {...form.bind('email')} />
        <Input label="New password" type="password" autoComplete="new-password" required autoFocus hint="At least 8 characters with upper and lower case letters and a number." {...form.bind('password')} />
        <Input label="Confirm new password" type="password" autoComplete="new-password" required {...form.bind('password_confirmation')} />
        <Button type="submit" block size="lg" loading={form.submitting}>
          Reset password
        </Button>
      </form>
    </AuthCard>
  )
}
