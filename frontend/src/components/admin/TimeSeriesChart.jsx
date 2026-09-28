import { useId, useState } from 'react'
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatDate } from '../../utils/format'

// Single-series charts use one validated hue (dataviz reference slot 1, passes all
// checks on a light surface). Text stays in ink colours, never the series colour.
const SERIES = '#2a78d6'
const GRID = '#e2e8f0'
const AXIS_TEXT = '#64748b'

function ChartTooltip({ active, payload, label, valueLabel, formatValue }) {
  if (!active || !payload?.length) return null

  return (
    <div className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-md">
      <p className="font-medium text-slate-500">{formatDate(label)}</p>
      <p className="mt-0.5 flex items-center gap-2 text-slate-900">
        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: SERIES }} aria-hidden="true" />
        {valueLabel}: <span className="font-semibold">{formatValue(payload[0].value)}</span>
      </p>
    </div>
  )
}

/**
 * One measure over time. Two measures of different scale go in two charts
 * (never a dual axis). Includes a data-table view so the numbers are available
 * without relying on the graphic.
 */
export function TimeSeriesChart({ data, dataKey, type = 'area', valueLabel, formatValue = (v) => v, height = 260 }) {
  const [showTable, setShowTable] = useState(false)
  const gradientId = useId().replace(/:/g, '')
  const tickDate = (value) => formatDate(value, { month: 'short', day: 'numeric' })

  const axes = (
    <>
      <CartesianGrid stroke={GRID} strokeDasharray="0" vertical={false} />
      <XAxis dataKey="date" tickFormatter={tickDate} tick={{ fill: AXIS_TEXT, fontSize: 11 }} tickLine={false} axisLine={{ stroke: GRID }} minTickGap={24} />
      <YAxis tickFormatter={formatValue} tick={{ fill: AXIS_TEXT, fontSize: 11 }} tickLine={false} axisLine={false} width={64} allowDecimals={false} />
      <Tooltip content={<ChartTooltip valueLabel={valueLabel} formatValue={formatValue} />} cursor={type === 'area' ? { stroke: '#94a3b8', strokeWidth: 1 } : { fill: '#f1f5f9' }} />
    </>
  )

  return (
    <div>
      {showTable ? (
        <div className="max-h-[260px] overflow-auto rounded-lg border border-slate-200">
          <table className="min-w-full text-sm">
            <thead className="sticky top-0 bg-slate-50 text-left text-xs text-slate-500 uppercase">
              <tr>
                <th scope="col" className="px-3 py-2">Date</th>
                <th scope="col" className="px-3 py-2 text-right">{valueLabel}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {data.map((row) => (
                <tr key={row.date}>
                  <td className="px-3 py-1.5 text-slate-600">{formatDate(row.date)}</td>
                  <td className="px-3 py-1.5 text-right font-medium text-slate-900">{formatValue(row[dataKey])}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div style={{ height }} role="img" aria-label={`${valueLabel} over time chart. Use "Show table" for the underlying values.`}>
          <ResponsiveContainer width="100%" height="100%">
            {type === 'area' ? (
              <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={SERIES} stopOpacity={0.18} />
                    <stop offset="100%" stopColor={SERIES} stopOpacity={0} />
                  </linearGradient>
                </defs>
                {axes}
                <Area isAnimationActive={false} type="monotone" dataKey={dataKey} stroke={SERIES} strokeWidth={2} fill={`url(#${gradientId})`} activeDot={{ r: 5, stroke: '#fff', strokeWidth: 2, fill: SERIES }} dot={false} />
              </AreaChart>
            ) : (
              <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} barCategoryGap={2}>
                {axes}
                <Bar isAnimationActive={false} dataKey={dataKey} fill={SERIES} radius={[4, 4, 0, 0]} maxBarSize={18} />
              </BarChart>
            )}
          </ResponsiveContainer>
        </div>
      )}
      <button type="button" onClick={() => setShowTable((v) => !v)} className="mt-2 text-xs font-medium text-brand-600 hover:underline">
        {showTable ? 'Show chart' : 'Show table'}
      </button>
    </div>
  )
}
