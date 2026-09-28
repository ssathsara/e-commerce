import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Button } from '../../components/common/Button'
import { Input } from '../../components/forms/Fields'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { AuthCard, FormAlert } from '../../features/auth/AuthCard'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'
import { safeRedirect } from '../../routes/guards'

export default function RegisterPage() {
  useDocumentTitle('Create account')
  const { register } = useAuth()
  const toast = useToast()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [formError, setFormError] = useState(null)
  const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

  const onSubmit = form.submit(
    async (values) => {
      setFormError(null)
      await register(values)
      toast.success('Welcome! Your account has been created. Please check your email to verify your address.')
      navigate(safeRedirect(params.get('redirect'), '/account'), { replace: true })
    },
    { onError: (error) => !error.isValidation && setFormError(error.message) },
  )

  return (
    <AuthCard
      title="Create your account"
      subtitle="Track orders, save addresses and build your wishlist."
      footer={
        <>
          Already have an account?{' '}
          <Link to="/login" className="font-medium text-brand-600 hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <FormAlert message={formError} />
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <Input label="Full name" autoComplete="name" required autoFocus {...form.bind('name')} />
        <Input label="Email" type="email" autoComplete="email" required {...form.bind('email')} />
        <Input label="Password" type="password" autoComplete="new-password" required hint="At least 8 characters with upper and lower case letters and a number." {...form.bind('password')} />
        <Input label="Confirm password" type="password" autoComplete="new-password" required {...form.bind('password_confirmation')} />
        <Button type="submit" block size="lg" loading={form.submitting}>
          Create account
        </Button>
      </form>
    </AuthCard>
  )
}
