import { Icon } from '../../components/common/Icon'
import { formatDateTime } from '../../utils/format'

const FLOW = [
  { status: 'pending', label: 'Order placed', dateKey: 'created_at' },
  { status: 'confirmed', label: 'Confirmed', dateKey: 'confirmed_at' },
  { status: 'processing', label: 'Processing' },
  { status: 'shipped', label: 'Shipped', dateKey: 'shipped_at' },
  { status: 'delivered', label: 'Delivered', dateKey: 'delivered_at' },
]

/** Visual progress of the fulfilment workflow. */
export function OrderStatusTimeline({ order }) {
  if (order.status === 'cancelled' || order.status === 'refunded') {
    return (
      <div className="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700" role="status">
        This order was <strong>{order.status}</strong>
        {order.cancelled_at && ` on ${formatDateTime(order.cancelled_at)}`}.
      </div>
    )
  }

  const currentIndex = FLOW.findIndex((step) => step.status === order.status)

  return (
    <ol className="grid grid-cols-5 gap-2" aria-label="Order progress">
      {FLOW.map((step, index) => {
        const done = index <= currentIndex
        return (
          <li key={step.status} className="flex flex-col items-center text-center" aria-current={index === currentIndex ? 'step' : undefined}>
            <span className={`flex h-8 w-8 items-center justify-center rounded-full ${done ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-400'}`}>
              {done ? <Icon name="check" className="h-4 w-4" strokeWidth={2.5} /> : <span className="text-xs font-semibold">{index + 1}</span>}
            </span>
            <span className={`mt-2 text-xs font-medium ${done ? 'text-slate-900' : 'text-slate-400'}`}>{step.label}</span>
            {step.dateKey && order[step.dateKey] && <span className="mt-0.5 hidden text-[11px] text-slate-500 sm:block">{formatDateTime(order[step.dateKey])}</span>}
          </li>
        )
      })}
    </ol>
  )
}
