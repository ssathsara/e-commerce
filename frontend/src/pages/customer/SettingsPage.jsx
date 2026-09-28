import { accountApi } from '../../api/accountApi'
import { Button } from '../../components/common/Button'
import { Card } from '../../components/common/Misc'
import { Input } from '../../components/forms/Fields'
import { useToast } from '../../contexts/ToastContext'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'

export default function SettingsPage() {
  useDocumentTitle('Account settings')
  const toast = useToast()
  const form = useForm({ current_password: '', password: '', password_confirmation: '' })

  const onSubmit = form.submit(
    async (values) => {
      toast.success(await accountApi.changePassword(values))
      form.reset()
    },
    { onError: (error) => !error.isValidation && toast.error(error.message) },
  )

  return (
    <Card title="Change password">
      <form onSubmit={onSubmit} className="max-w-xl space-y-4" noValidate>
        <Input label="Current password" type="password" autoComplete="current-password" required {...form.bind('current_password')} />
        <Input label="New password" type="password" autoComplete="new-password" required hint="At least 8 characters with upper and lower case letters and a number." {...form.bind('password')} />
        <Input label="Confirm new password" type="password" autoComplete="new-password" required {...form.bind('password_confirmation')} />
        <p className="text-sm text-slate-500">For your security, changing your password signs you out on all other devices.</p>
        <Button type="submit" loading={form.submitting}>
          Update password
        </Button>
      </form>
    </Card>
  )
}
