import { useEffect, useId, useRef, useState } from 'react'

/**
 * Disclosure-style dropdown menu. Closes on outside click, Escape and selection.
 * `trigger` receives ({ open, toggle, id }) and must render the toggle button.
 */
export function Dropdown({ trigger, children, align = 'right', width = 'w-56' }) {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)
  const id = useId()

  useEffect(() => {
    if (!open) return undefined

    const onClick = (event) => ref.current && !ref.current.contains(event.target) && setOpen(false)
    const onKey = (event) => event.key === 'Escape' && setOpen(false)

    document.addEventListener('mousedown', onClick)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onClick)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  return (
    <div ref={ref} className="relative">
      {trigger({ open, toggle: () => setOpen((v) => !v), id })}
      {open && (
        <div
          id={id}
          className={`absolute z-40 mt-2 ${width} origin-top animate-fade-in rounded-xl border border-slate-200 bg-white py-1 shadow-lg ${align === 'right' ? 'right-0' : 'left-0'}`}
          onClick={() => setOpen(false)}
        >
          {children}
        </div>
      )}
    </div>
  )
}

export function DropdownItem({ as: Component = 'button', className = '', children, ...props }) {
  return (
    <Component
      type={Component === 'button' ? 'button' : undefined}
      className={`flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 hover:text-slate-900 ${className}`}
      {...props}
    >
      {children}
    </Component>
  )
}
