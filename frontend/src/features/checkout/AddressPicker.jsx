import { formatAddress } from '../../utils/format'

/** Radio list of saved addresses. */
export function AddressPicker({ name, addresses, value, onChange, legend }) {
  return (
    <fieldset>
      <legend className="sr-only">{legend}</legend>
      <div className="grid gap-3 sm:grid-cols-2">
        {addresses.map((address) => (
          <label
            key={address.id}
            className={`relative cursor-pointer rounded-xl border p-4 text-sm transition-colors focus-within:ring-2 focus-within:ring-brand-500 ${value === address.id ? 'border-brand-600 bg-brand-50/50' : 'border-slate-200 hover:border-slate-300'}`}
          >
            <input type="radio" name={name} value={address.id} checked={value === address.id} onChange={() => onChange(address.id)} className="sr-only" />
            <span className="flex items-center justify-between gap-2">
              <span className="font-semibold text-slate-900">
                {address.first_name} {address.last_name}
              </span>
              {address.label && <span className="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">{address.label}</span>}
            </span>
            <span className="mt-1 block whitespace-pre-line text-slate-600">{formatAddress(address)}</span>
            {address.phone && <span className="mt-1 block text-slate-500">{address.phone}</span>}
          </label>
        ))}
      </div>
    </fieldset>
  )
}
