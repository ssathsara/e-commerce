import { Button } from '../../components/common/Button'
import { Checkbox, Input } from '../../components/forms/Fields'
import { useToast } from '../../contexts/ToastContext'
import { useForm } from '../../hooks/useForm'

const EMPTY = {
  label: '',
  first_name: '',
  last_name: '',
  company: '',
  phone: '',
  line1: '',
  line2: '',
  city: '',
  state: '',
  postal_code: '',
  country: 'US',
  is_default_shipping: false,
  is_default_billing: false,
}

/** Create/edit address form; server validation errors are shown per field. */
export function AddressForm({ address, onSave, onCancel, submitLabel = 'Save address' }) {
  const toast = useToast()
  const form = useForm({ ...EMPTY, ...(address ?? {}) })

  const onSubmit = form.submit(async (values) => onSave(values), {
    onError: (error) => !error.isValidation && toast.error(error.message),
  })

  return (
    <form onSubmit={onSubmit} className="space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-2">
        <Input label="First name" required autoComplete="given-name" {...form.bind('first_name')} />
        <Input label="Last name" required autoComplete="family-name" {...form.bind('last_name')} />
        <Input label="Company" autoComplete="organization" {...form.bind('company')} />
        <Input label="Phone" type="tel" autoComplete="tel" {...form.bind('phone')} />
        <Input label="Address line 1" required autoComplete="address-line1" className="sm:col-span-2" {...form.bind('line1')} />
        <Input label="Address line 2" autoComplete="address-line2" className="sm:col-span-2" {...form.bind('line2')} />
        <Input label="City" required autoComplete="address-level2" {...form.bind('city')} />
        <Input label="State / Province" autoComplete="address-level1" {...form.bind('state')} />
        <Input label="Postal code" required autoComplete="postal-code" {...form.bind('postal_code')} />
        <Input label="Country code" required maxLength={2} autoComplete="country" hint="Two letters, e.g. US" {...form.bind('country')} />
        <Input label="Label" placeholder="Home, Work…" className="sm:col-span-2" {...form.bind('label')} />
      </div>
      <div className="flex flex-col gap-3 sm:flex-row sm:gap-6">
        <Checkbox label="Default shipping address" {...form.bindCheckbox('is_default_shipping')} />
        <Checkbox label="Default billing address" {...form.bindCheckbox('is_default_billing')} />
      </div>
      <div className="flex justify-end gap-2 pt-2">
        {onCancel && (
          <Button variant="secondary" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" loading={form.submitting}>
          {submitLabel}
        </Button>
      </div>
    </form>
  )
}
