import { Button } from '../../components/common/Button'
import { Icon } from '../../components/common/Icon'

/** "Color: Black, Size: M" <-> { Color: 'Black', Size: 'M' } */
export function optionsToText(options) {
  return Object.entries(options ?? {})
    .map(([key, value]) => `${key}: ${value}`)
    .join(', ')
}

export function textToOptions(text) {
  const entries = (text ?? '')
    .split(',')
    .map((part) => part.split(':').map((s) => s.trim()))
    .filter(([key, value]) => key && value)
  return entries.length ? Object.fromEntries(entries) : null
}

export const emptyVariant = () => ({ key: crypto.randomUUID?.() ?? String(Math.random()), id: null, name: '', sku: '', optionsText: '', price: '', compare_at_price: '', stock_quantity: 0, is_active: true })

const cell = 'block w-full rounded-md border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500'

/**
 * Editable variant rows. Stock can only be set for NEW variants; existing stock is
 * changed through inventory adjustments so every change is recorded.
 */
export function VariantsEditor({ variants, onChange, errorFor }) {
  const update = (index, field, value) => onChange(variants.map((v, i) => (i === index ? { ...v, [field]: value } : v)))
  const remove = (index) => onChange(variants.filter((_, i) => i !== index))

  return (
    <div className="space-y-3">
      {variants.length === 0 && <p className="text-sm text-slate-500">No variants — this product is sold as a single item with its own stock.</p>}

      {variants.map((variant, index) => {
        const err = (field) => errorFor(`variants.${index}.${field}`)
        return (
          <fieldset key={variant.key} className="rounded-xl border border-slate-200 p-4">
            <legend className="px-1 text-xs font-semibold text-slate-500">Variant {index + 1}</legend>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
              <label className="text-xs font-medium text-slate-600 lg:col-span-2">
                Name *
                <input className={`${cell} mt-1`} value={variant.name} onChange={(e) => update(index, 'name', e.target.value)} placeholder="e.g. 256GB / Black" aria-invalid={Boolean(err('name')) || undefined} />
                {err('name') && <span className="mt-1 block text-red-600">{err('name')}</span>}
              </label>
              <label className="text-xs font-medium text-slate-600">
                SKU *
                <input className={`${cell} mt-1 uppercase`} value={variant.sku} onChange={(e) => update(index, 'sku', e.target.value.toUpperCase())} aria-invalid={Boolean(err('sku')) || undefined} />
                {err('sku') && <span className="mt-1 block text-red-600">{err('sku')}</span>}
              </label>
              <label className="text-xs font-medium text-slate-600">
                Price override
                <input type="number" step="0.01" min="0" className={`${cell} mt-1`} value={variant.price} onChange={(e) => update(index, 'price', e.target.value)} placeholder="Inherit" />
                {err('price') && <span className="mt-1 block text-red-600">{err('price')}</span>}
              </label>
              <label className="text-xs font-medium text-slate-600">
                Stock {variant.id ? '(via inventory)' : ''}
                <input type="number" min="0" className={`${cell} mt-1`} value={variant.stock_quantity} disabled={Boolean(variant.id)} onChange={(e) => update(index, 'stock_quantity', e.target.value)} />
                {err('stock_quantity') && <span className="mt-1 block text-red-600">{err('stock_quantity')}</span>}
              </label>
              <div className="flex items-end justify-between gap-2">
                <label className="flex items-center gap-2 pb-2 text-xs font-medium text-slate-600">
                  <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" checked={variant.is_active} onChange={(e) => update(index, 'is_active', e.target.checked)} />
                  Active
                </label>
                <button type="button" onClick={() => remove(index)} className="rounded-md p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label={`Remove variant ${index + 1}`}>
                  <Icon name="trash" className="h-4 w-4" />
                </button>
              </div>
              <label className="text-xs font-medium text-slate-600 sm:col-span-2 lg:col-span-6">
                Options
                <input className={`${cell} mt-1`} value={variant.optionsText} onChange={(e) => update(index, 'optionsText', e.target.value)} placeholder="Color: Black, Storage: 256GB" />
              </label>
            </div>
          </fieldset>
        )
      })}

      <Button variant="secondary" size="sm" onClick={() => onChange([...variants, emptyVariant()])}>
        <Icon name="plus" className="h-4 w-4" /> Add variant
      </Button>
      {variants.length > 0 && (
        <p className="text-xs text-slate-500">When a product has variants, its stock is the sum of its active variants’ stock.</p>
      )}
    </div>
  )
}
