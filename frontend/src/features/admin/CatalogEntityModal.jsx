import { useEffect, useState } from 'react'
import { Button } from '../../components/common/Button'
import { Modal } from '../../components/common/Modal'
import { Checkbox, Input, Select, Textarea } from '../../components/forms/Fields'
import { ImageUpload } from '../../components/forms/ImageUpload'
import { useToast } from '../../contexts/ToastContext'
import { useForm } from '../../hooks/useForm'

/**
 * Shared create/edit modal for categories and brands (both have name, slug,
 * description, an image and an active flag). Sent as multipart form data.
 *
 * kind: 'category' | 'brand'
 */
export function CatalogEntityModal({ kind, open, entity, parents = [], onClose, onSave }) {
  const toast = useToast()
  const imageField = kind === 'brand' ? 'logo' : 'image'
  const currentUrl = kind === 'brand' ? entity?.logo_url : entity?.image_url
  const [file, setFile] = useState(null)
  const [removeImage, setRemoveImage] = useState(false)
  const form = useForm({})

  useEffect(() => {
    if (!open) return
    setFile(null)
    setRemoveImage(false)
    form.reset({
      name: entity?.name ?? '',
      slug: entity?.slug ?? '',
      description: entity?.description ?? '',
      is_active: entity?.is_active ?? true,
      is_featured: entity?.is_featured ?? false,
      parent_id: entity?.parent_id ? String(entity.parent_id) : '',
      sort_order: entity?.sort_order ?? 0,
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, entity])

  const onSubmit = form.submit(
    async (values) => {
      const payload = {
        name: values.name,
        slug: values.slug || null,
        description: values.description || null,
        is_active: Boolean(values.is_active),
        ...(kind === 'category' ? { parent_id: values.parent_id || null, is_featured: Boolean(values.is_featured), sort_order: values.sort_order || 0 } : {}),
        ...(file ? { [imageField]: file } : {}),
        ...(removeImage && !file ? { [`remove_${imageField}`]: true } : {}),
      }
      await onSave(payload)
    },
    { onError: (error) => !error.isValidation && toast.error(error.message) },
  )

  const title = `${entity ? 'Edit' : 'New'} ${kind}`

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form={`${kind}-form`} loading={form.submitting}>
            {entity ? 'Save changes' : `Create ${kind}`}
          </Button>
        </>
      }
    >
      <form id={`${kind}-form`} onSubmit={onSubmit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Name" required {...form.bind('name')} />
          <Input label="Slug" hint="Leave blank to generate automatically." {...form.bind('slug')} />
          {kind === 'category' && (
            <>
              <Select
                label="Parent category"
                placeholder="None (top level)"
                options={parents.filter((p) => p.id !== entity?.id).map((p) => ({ value: String(p.id), label: p.name }))}
                {...form.bind('parent_id')}
              />
              <Input label="Sort order" type="number" min="0" {...form.bind('sort_order')} />
            </>
          )}
        </div>
        <Textarea label="Description" rows={3} {...form.bind('description')} />
        <ImageUpload
          label={kind === 'brand' ? 'Logo' : 'Image'}
          currentUrl={removeImage ? null : currentUrl}
          file={file}
          onChange={(f) => {
            setFile(f)
            setRemoveImage(false)
          }}
          onRemove={() => {
            setFile(null)
            setRemoveImage(true)
          }}
          error={form.error(imageField)}
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:gap-8">
          <Checkbox label="Active" description="Visible on the storefront." {...form.bindCheckbox('is_active')} />
          {kind === 'category' && <Checkbox label="Featured" description="Shown on the home page." {...form.bindCheckbox('is_featured')} />}
        </div>
      </form>
    </Modal>
  )
}
