import { useState } from 'react'
import { ProductImage } from '../../components/common/Misc'

export function ProductGallery({ images, name }) {
  const [activeIndex, setActiveIndex] = useState(0)
  const active = images[activeIndex] ?? images[0]

  return (
    <div className="flex flex-col-reverse gap-4 sm:flex-row">
      {images.length > 1 && (
        <ul className="flex gap-3 sm:flex-col" aria-label="Product images">
          {images.map((image, index) => (
            <li key={image.id}>
              <button
                type="button"
                onClick={() => setActiveIndex(index)}
                aria-label={`Show image ${index + 1} of ${images.length}`}
                aria-current={index === activeIndex ? 'true' : undefined}
                className={`h-16 w-16 overflow-hidden rounded-lg border-2 sm:h-20 sm:w-20 ${index === activeIndex ? 'border-brand-600' : 'border-transparent opacity-70 hover:opacity-100'}`}
              >
                <ProductImage src={image.url} alt="" className="h-full w-full" />
              </button>
            </li>
          ))}
        </ul>
      )}
      <div className="card flex-1 overflow-hidden">
        <ProductImage src={active?.url} alt={active?.alt_text || name} className="aspect-square w-full" loading="eager" />
      </div>
    </div>
  )
}
