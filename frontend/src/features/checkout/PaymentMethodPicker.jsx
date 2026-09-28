import { Icon } from '../../components/common/Icon'
import { TEST_CARDS } from '../../constants/statuses'

/**
 * Payment selection. The project ships with a MOCK gateway: instead of collecting card
 * numbers (which must never touch our server), the customer picks a test token, exactly
 * like a real provider's client SDK would return one.
 */
export function PaymentMethodPicker({ methods, value, onChange, cardToken, onCardTokenChange }) {
  return (
    <div className="space-y-3">
      <fieldset>
        <legend className="sr-only">Payment method</legend>
        <div className="grid gap-3 sm:grid-cols-2">
          {methods.map((method) => (
            <label
              key={method.code}
              className={`flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm focus-within:ring-2 focus-within:ring-brand-500 ${value === method.code ? 'border-brand-600 bg-brand-50/50' : 'border-slate-200 hover:border-slate-300'}`}
            >
              <input type="radio" name="payment_method" value={method.code} checked={value === method.code} onChange={() => onChange(method.code)} className="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" />
              <Icon name={method.is_online ? 'card' : 'cash'} className="h-5 w-5 text-slate-500" />
              <span className="font-medium text-slate-900">{method.name}</span>
            </label>
          ))}
        </div>
      </fieldset>

      {value === 'card' && (
        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
          <p className="flex items-center gap-2 text-sm font-medium text-slate-800">
            <Icon name="shield" className="h-4 w-4 text-brand-600" /> Test payment mode
          </p>
          <p className="mt-1 text-xs text-slate-500">No real card is charged. Choose a test card to simulate the payment outcome.</p>
          <label htmlFor="test-card" className="mt-3 mb-1 block text-sm font-medium text-slate-700">
            Test card
          </label>
          <select id="test-card" value={cardToken} onChange={(e) => onCardTokenChange(e.target.value)} className="block w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
            {TEST_CARDS.map((card) => (
              <option key={card.token} value={card.token}>
                {card.label} — {card.outcome}
              </option>
            ))}
          </select>
        </div>
      )}

      {value === 'cash_on_delivery' && <p className="text-sm text-slate-600">Pay in cash when your order is delivered. Your order will be confirmed by our team.</p>}
    </div>
  )
}
