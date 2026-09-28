import { useState } from 'react'
import { Link } from 'react-router-dom'
import { authApi } from '../../api/authApi'
import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'
import { Input } from '../../components/forms/Fields'
import { AuthCard, FormAlert } from '../../features/auth/AuthCard'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'

export default function ForgotPasswordPage() {
  useDocumentTitle('Forgot password')
  const [sentMessage, setSentMessage] = useState(null)
  const [formError, setFormError] = useState(null)
  const form = useForm({ email: '' })

  const onSubmit = form.submit(
    async ({ email }) => {
      setFormError(null)
      setSentMessage(await authApi.forgotPassword(email))
    },
    { onError: (error) => !error.isValidation && setFormError(error.message) },
  )

  return (
    <AuthCard
      title="Reset your password"
      subtitle="Enter your email and we will send you a reset link."
      footer={
        <Link to="/login" className="font-medium text-brand-600 hover:underline">
          Back to sign in
        </Link>
      }
    >
      {sentMessage ? (
        <div role="status" className="flex gap-3 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">
          <Icon name="check" className="h-5 w-5 shrink-0" />
          <p>{sentMessage}</p>
        </div>
      ) : (
        <>
          <FormAlert message={formError} />
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <Input label="Email" type="email" autoComplete="email" required autoFocus {...form.bind('email')} />
            <Button type="submit" block size="lg" loading={form.submitting}>
              Send reset link
            </Button>
          </form>
        </>
      )}
    </AuthCard>
  )
}
