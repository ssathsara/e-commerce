import { useEffect, useId, useState } from 'react'
import { Icon } from '../common/Icon'

const ACCEPT = 'image/jpeg,image/png,image/webp'
const MAX_BYTES = 5 * 1024 * 1024

/**
 * Single image picker with preview. Client-side checks are for fast feedback only;
 * the API validates MIME type (by content), size and dimensions.
 */
export function ImageUpload({ label, currentUrl, file, onChange, onRemove, error }) {
  const id = useId()
  const [preview, setPreview] = useState(null)
  const [localError, setLocalError] = useState(null)

  useEffect(() => {
    if (!file) {
      setPreview(null)
      return undefined
    }
    const url = URL.createObjectURL(file)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file])

  const handleFile = (event) => {
    const selected = event.target.files?.[0]
    event.target.value = ''
    if (!selected) return

    if (!ACCEPT.split(',').includes(selected.type)) {
      setLocalError('Please choose a JPEG, PNG or WebP image.')
      return
    }
    if (selected.size > MAX_BYTES) {
      setLocalError('Images must be 5 MB or smaller.')
      return
    }

    setLocalError(null)
    onChange(selected)
  }

  const shown = preview ?? currentUrl
  const message = localError ?? error

  return (
    <div>
      <span className="mb-1.5 block text-sm font-medium text-slate-700">{label}</span>
      <div className="flex items-center gap-4">
        <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
          {shown ? <img src={shown} alt="" className="h-full w-full object-cover" /> : <Icon name="photo" className="h-8 w-8 text-slate-300" />}
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={id} className="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus-within:outline-2 focus-within:outline-brand-500">
            {shown ? 'Replace image' : 'Upload image'}
            <input id={id} type="file" accept={ACCEPT} className="sr-only" onChange={handleFile} />
          </label>
          {shown && onRemove && (
            <button type="button" onClick={onRemove} className="text-left text-xs font-medium text-red-600 hover:underline">
              Remove image
            </button>
          )}
          <span className="text-xs text-slate-500">JPEG, PNG or WebP, up to 5 MB.</span>
        </div>
      </div>
      {message && <p className="mt-1 text-xs font-medium text-red-600">{message}</p>}
    </div>
  )
}
