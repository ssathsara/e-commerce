import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { adminApi } from '../../api/adminApi'
import { Button } from '../../components/common/Button'
import { Card, PageHeader } from '../../components/common/Misc'
import { PageSpinner } from '../../components/common/Spinner'
import { ErrorState } from '../../components/common/States'
import { Checkbox, Input, Select, Textarea } from '../../components/forms/Fields'
import { useToast } from '../../contexts/ToastContext'
import { ProductImagesManager } from '../../features/admin/ProductImagesManager'
import { optionsToText, textToOptions, VariantsEditor } from '../../features/admin/VariantsEditor'
import { useAdminOptions } from '../../hooks/useAdminOptions'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useForm } from '../../hooks/useForm'

const BLANK = {
  name: '',
  slug: '',
  sku: '',
  category_id: '',
  brand_id: '',
  short_description: '',
  description: '',
  price: '',
  compare_at_price: '',
  stock_quantity: 0,
  low_stock_threshold: 5,
  status: 'draft',
  is_featured: false,
  weight: '',
  length: '',
  width: '',
  height: '',
  seo_title: '',
  seo_description: '',
}

function toFormValues(product) {
  const values = { ...BLANK }
  Object.keys(BLANK).forEach((key) => {
    values[key] = product[key] ?? BLANK[key]
  })
  values.status = product.status === 'out_of_stock' ? 'active' : product.status
  return values
}

const toNumberOrNull = (value) => (value === '' || value === null || value === undefined ? null : Number(value))

export default function ProductFormPage() {
  const { productId } = useParams()
  const isEdit = Boolean(productId)
  useDocumentTitle(isEdit ? 'Edit product' : 'New product')
  const navigate = useNavigate()
  const toast = useToast()
  const options = useAdminOptions()
  const product = useAsync(() => (isEdit ? adminApi.products.get(productId) : Promise.resolve(null)), [productId])
  const form = useForm(BLANK)
  const [variants, setVariants] = useState([])
  const [images, setImages] = useState([])

  useEffect(() => {
    if (!product.data) return
    form.reset(toFormValues(product.data))
    setImages(product.data.images ?? [])
    setVariants(
      (product.data.variants ?? []).map((v) => ({
        key: `v-${v.id}`,
        id: v.id,
        name: v.name,
        sku: v.sku,
        optionsText: optionsToText(v.options),
        price: v.price_override ?? '',
        compare_at_price: v.compare_at_price_override ?? '',
        stock_quantity: v.stock_quantity,
        is_active: v.is_active,
      })),
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [product.data])

  const onSubmit = form.submit(
    async (values) => {
      const payload = {
        ...values,
        category_id: toNumberOrNull(values.category_id),
        brand_id: toNumberOrNull(values.brand_id),
        compare_at_price: toNumberOrNull(values.compare_at_price),
        weight: toNumberOrNull(values.weight),
        length: toNumberOrNull(values.length),
        width: toNumberOrNull(values.width),
        height: toNumberOrNull(values.height),
        slug: values.slug || null,
        variants: variants.map((v, index) => ({
          id: v.id,
          name: v.name,
          sku: v.sku,
          options: textToOptions(v.optionsText),
          price: toNumberOrNull(v.price),
          compare_at_price: toNumberOrNull(v.compare_at_price),
          stock_quantity: v.id ? undefined : Number(v.stock_quantity || 0),
          is_active: v.is_active,
          sort_order: index,
        })),
      }
      if (isEdit) delete payload.stock_quantity

      if (isEdit) {
        const { data, message } = await adminApi.products.update(productId, payload)
        toast.success(message)
        product.setData(data)
      } else {
        const { data, message } = await adminApi.products.create(payload)
        toast.success(`${message} You can now add images.`)
        navigate(`/admin/products/${data.id}/edit`, { replace: true })
      }
    },
    { onError: (error) => toast.error(error.isValidation ? 'Please correct the highlighted fields.' : error.message) },
  )

  if (product.loading || !options) return <PageSpinner />
  if (product.error) return <ErrorState error={product.error} onRetry={product.reload} />

  const categoryOptions = options.categories.map((c) => ({ value: String(c.id), label: `${c.parent_id ? '— ' : ''}${c.name}${c.is_active ? '' : ' (inactive)'}` }))
  const brandOptions = options.brands.map((b) => ({ value: String(b.id), label: b.name }))
  const statusOptions = options.product_statuses.filter((s) => s.value !== 'out_of_stock')

  return (
    <form onSubmit={onSubmit} noValidate>
      <PageHeader
        title={isEdit ? `Edit: ${product.data?.name}` : 'New product'}
        description={isEdit ? `SKU ${product.data?.sku}` : 'Add a product to your catalogue.'}
        actions={
          <>
            <Button variant="secondary" to={isEdit ? `/admin/products/${productId}` : '/admin/products'}>
              Cancel
            </Button>
            <Button type="submit" loading={form.submitting}>
              {isEdit ? 'Save changes' : 'Create product'}
            </Button>
          </>
        }
      />

      <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div className="space-y-6">
          <Card title="Basic information">
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Product name" required className="sm:col-span-2" {...form.bind('name')} />
              <Input label="SKU" required {...form.bind('sku')} onChange={(e) => form.set('sku', e.target.value.toUpperCase())} hint="Letters, numbers, dashes. Must be unique." />
              <Input label="URL slug" {...form.bind('slug')} hint="Leave blank to generate from the name." />
              <Textarea label="Short description" rows={2} maxLength={500} className="sm:col-span-2" {...form.bind('short_description')} />
              <Textarea label="Full description" rows={7} className="sm:col-span-2" {...form.bind('description')} />
            </div>
          </Card>

          <Card title="Pricing & stock">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <Input label="Price" type="number" step="0.01" min="0" required {...form.bind('price')} />
              <Input label="Compare-at price" type="number" step="0.01" min="0" hint="Original price, shown struck through." {...form.bind('compare_at_price')} />
              {!isEdit && variants.length === 0 && <Input label="Opening stock" type="number" min="0" {...form.bind('stock_quantity')} />}
              {isEdit && (
                <div>
                  <span className="mb-1.5 block text-sm font-medium text-slate-700">Current stock</span>
                  <p className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{product.data?.stock_quantity} units</p>
                  <p className="mt-1 text-xs text-slate-500">Adjust stock from Inventory.</p>
                </div>
              )}
              <Input label="Low-stock threshold" type="number" min="0" required {...form.bind('low_stock_threshold')} />
            </div>
          </Card>

          <Card title="Variants">
            <VariantsEditor variants={variants} onChange={setVariants} errorFor={form.error} />
          </Card>

          {isEdit && (
            <Card title="Images">
              <ProductImagesManager productId={productId} images={images} onChange={setImages} />
            </Card>
          )}

          <Card title="Search engine listing">
            <div className="grid gap-4">
              <Input label="SEO title" maxLength={70} hint={`${form.values.seo_title?.length ?? 0}/70`} {...form.bind('seo_title')} />
              <Textarea label="SEO description" rows={2} maxLength={160} hint={`${form.values.seo_description?.length ?? 0}/160`} {...form.bind('seo_description')} />
            </div>
          </Card>
        </div>

        <div className="space-y-6">
          <Card title="Status">
            <div className="space-y-4">
              <Select label="Status" required options={statusOptions} {...form.bind('status')} hint="Active products without stock are shown as out of stock automatically." />
              <Checkbox label="Featured product" description="Highlight on the home page." {...form.bindCheckbox('is_featured')} />
            </div>
          </Card>
          <Card title="Organisation">
            <div className="space-y-4">
              <Select label="Category" required placeholder="Select a category" options={categoryOptions} {...form.bind('category_id')} value={String(form.values.category_id ?? '')} />
              <Select label="Brand" placeholder="No brand" options={brandOptions} {...form.bind('brand_id')} value={String(form.values.brand_id ?? '')} />
            </div>
          </Card>
          <Card title="Shipping">
            <div className="grid grid-cols-2 gap-3">
              <Input label="Weight (kg)" type="number" step="0.001" min="0" className="col-span-2" {...form.bind('weight')} />
              <Input label="Length (cm)" type="number" step="0.01" min="0" {...form.bind('length')} />
              <Input label="Width (cm)" type="number" step="0.01" min="0" {...form.bind('width')} />
              <Input label="Height (cm)" type="number" step="0.01" min="0" {...form.bind('height')} />
            </div>
          </Card>
        </div>
      </div>
    </form>
  )
}
