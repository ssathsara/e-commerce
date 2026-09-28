import { accountApi } from '../../api/accountApi'
import { Button } from '../../components/common/Button'
import { Card } from '../../components/common/Misc'
import { Input } from '../../components/forms/Fields'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'

export default function ProfilePage() {
  useDocumentTitle('Profile')
  const { user, setAuthenticated } = useAuth()
  const toast = useToast()
  const form = useForm({ name: user.name, email: user.email, phone: user.phone ?? '' })

  const onSubmit = form.submit(
    async (values) => {
      const emailChanged = values.email.toLowerCase() !== user.email.toLowerCase()
      setAuthenticated(await accountApi.updateProfile(values))
      toast.success(emailChanged ? 'Profile updated. Please verify your new email address.' : 'Profile updated.')
    },
    { onError: (error) => !error.isValidation && toast.error(error.message) },
  )

  return (
    <Card title="Personal information">
      <form onSubmit={onSubmit} className="max-w-xl space-y-4" noValidate>
        <Input label="Full name" required autoComplete="name" {...form.bind('name')} />
        <Input label="Email" type="email" required autoComplete="email" hint="Changing your email requires verifying the new address." {...form.bind('email')} />
        <Input label="Phone" type="tel" autoComplete="tel" {...form.bind('phone')} />
        <Button type="submit" loading={form.submitting}>
          Save changes
        </Button>
      </form>
    </Card>
  )
}
