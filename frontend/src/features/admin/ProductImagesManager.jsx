import { useRef, useState } from 'react'
import { adminApi } from '../../api/adminApi'
import { Badge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { Icon } from '../../components/common/Icon'
import { ProductImage } from '../../components/common/Misc'
import { useToast } from '../../contexts/ToastContext'

const ACCEPT = 'image/jpeg,image/png,image/webp'

/**
 * Upload, reorder, set primary, replace and delete product images. File type/size/
 * dimensions are validated by the API (content-based MIME detection).
 */
export function ProductImagesManager({ productId, images, onChange }) {
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const [busy, setBusy] = useState(false)
  const uploadRef = useRef(null)
  const replaceRef = useRef(null)
  const replaceTarget = useRef(null)

  const run = async (operation) => {
    setBusy(true)
    try {
      const { data, message } = await operation()
      onChange(data)
      toast.success(message)
    } catch (error) {
      const fieldMessage = error.errors && Object.values(error.errors)[0]?.[0]
      toast.error(fieldMessage ?? error.message)
    } finally {
      setBusy(false)
    }
  }

  const upload = (event) => {
    const files = [...(event.target.files ?? [])]
    event.target.value = ''
    if (files.length) run(() => adminApi.products.uploadImages(productId, files))
  }

  const replace = (event) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (file && replaceTarget.current) run(() => adminApi.products.replaceImage(productId, replaceTarget.current, file))
  }

  const move = (index, direction) => {
    const ids = images.map((i) => i.id)
    const target = index + direction
    if (target < 0 || target >= ids.length) return
    ;[ids[index], ids[target]] = [ids[target], ids[index]]
    run(() => adminApi.products.reorderImages(productId, ids))
  }

  const remove = (image) =>
    confirm({
      title: 'Delete image?',
      message: 'The image file will be permanently removed.',
      confirmLabel: 'Delete image',
      onConfirm: () => run(() => adminApi.products.deleteImage(productId, image.id)),
    })

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-500">JPEG, PNG or WebP · up to 5 MB · at least 200×200px. The first image marked primary is shown in listings.</p>
        <Button variant="secondary" size="sm" onClick={() => uploadRef.current?.click()} loading={busy}>
          <Icon name="photo" className="h-4 w-4" /> Upload images
        </Button>
        <input ref={uploadRef} type="file" accept={ACCEPT} multiple className="sr-only" onChange={upload} aria-label="Upload product images" />
        <input ref={replaceRef} type="file" accept={ACCEPT} className="sr-only" onChange={replace} aria-label="Replacement image" tabIndex={-1} />
      </div>

      {images.length === 0 ? (
        <div className="flex flex-col items-center rounded-xl border-2 border-dashed border-slate-200 py-10 text-center text-sm text-slate-500">
          <Icon name="photo" className="mb-2 h-8 w-8 text-slate-300" />
          No images yet.
        </div>
      ) : (
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {images.map((image, index) => (
            <li key={image.id} className="overflow-hidden rounded-xl border border-slate-200 bg-white">
              <div className="relative">
                <ProductImage src={image.url} alt={image.alt_text} className="aspect-square w-full" />
                {image.is_primary && <Badge tone="indigo" className="absolute top-2 left-2">Primary</Badge>}
              </div>
              <div className="flex flex-wrap items-center justify-between gap-1 p-2">
                <div className="flex">
                  <button type="button" className="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" disabled={busy || index === 0} onClick={() => move(index, -1)} aria-label="Move image earlier">
                    <Icon name="chevronLeft" className="h-4 w-4" />
                  </button>
                  <button type="button" className="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" disabled={busy || index === images.length - 1} onClick={() => move(index, 1)} aria-label="Move image later">
                    <Icon name="chevronRight" className="h-4 w-4" />
                  </button>
                </div>
                <div className="flex">
                  {!image.is_primary && (
                    <button type="button" className="rounded px-1.5 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50" disabled={busy} onClick={() => run(() => adminApi.products.updateImage(productId, image.id, { is_primary: true }))}>
                      Make primary
                    </button>
                  )}
                  <button
                    type="button"
                    className="rounded p-1 text-slate-500 hover:bg-slate-100"
                    disabled={busy}
                    onClick={() => {
                      replaceTarget.current = image.id
                      replaceRef.current?.click()
                    }}
                    aria-label="Replace image"
                  >
                    <Icon name="refresh" className="h-4 w-4" />
                  </button>
                  <button type="button" className="rounded p-1 text-slate-500 hover:bg-red-50 hover:text-red-600" disabled={busy} onClick={() => remove(image)} aria-label="Delete image">
                    <Icon name="trash" className="h-4 w-4" />
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
      {dialog}
    </div>
  )
}
