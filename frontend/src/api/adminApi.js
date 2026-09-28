import { api, cleanParams, toFormData, unwrap, unwrapPage } from './axios'

/** Standard CRUD calls for an admin resource under /admin/{path}. */
function resource(path, { multipart = false } = {}) {
  const body = (values, method) => (multipart ? toFormData(values, method) : values)

  return {
    async list(params, config = {}) {
      return unwrapPage(await api.get(`/admin/${path}`, { params: cleanParams(params), ...config }))
    },
    async get(id) {
      return unwrap(await api.get(`/admin/${path}/${id}`))
    },
    async create(values) {
      const response = await api.post(`/admin/${path}`, body(values))
      return { data: response.data.data, message: response.data.message }
    },
    async update(id, values) {
      // Multipart updates use POST + _method=PUT (PHP cannot parse multipart PUT bodies).
      const response = multipart
        ? await api.post(`/admin/${path}/${id}`, body(values, 'PUT'))
        : await api.put(`/admin/${path}/${id}`, values)
      return { data: response.data.data, message: response.data.message }
    },
    async remove(id) {
      return (await api.delete(`/admin/${path}/${id}`)).data.message
    },
  }
}

const withMessage = (response) => ({ data: response.data.data, message: response.data.message })

export const adminApi = {
  async dashboard() {
    return unwrap(await api.get('/admin/dashboard'))
  },

  async notifications() {
    return unwrap(await api.get('/admin/notifications'))
  },

  async options() {
    return unwrap(await api.get('/admin/options'))
  },

  products: {
    ...resource('products'),
    async setStatus(id, status) {
      return withMessage(await api.patch(`/admin/products/${id}/status`, { status }))
    },
    async uploadImages(id, files) {
      const form = new FormData()
      files.forEach((file) => form.append('images[]', file))
      return withMessage(await api.post(`/admin/products/${id}/images`, form))
    },
    async updateImage(id, imageId, values) {
      return withMessage(await api.patch(`/admin/products/${id}/images/${imageId}`, values))
    },
    async replaceImage(id, imageId, file) {
      const form = new FormData()
      form.append('image', file)
      return withMessage(await api.post(`/admin/products/${id}/images/${imageId}/replace`, form))
    },
    async reorderImages(id, imageIds) {
      return withMessage(await api.put(`/admin/products/${id}/images/reorder`, { image_ids: imageIds }))
    },
    async deleteImage(id, imageId) {
      return withMessage(await api.delete(`/admin/products/${id}/images/${imageId}`))
    },
  },

  categories: resource('categories', { multipart: true }),
  brands: resource('brands', { multipart: true }),

  orders: {
    ...resource('orders'),
    async updateStatus(id, status, trackingNumber) {
      return withMessage(await api.patch(`/admin/orders/${id}/status`, { status, tracking_number: trackingNumber || null }))
    },
    async updatePaymentStatus(id, paymentStatus) {
      return withMessage(await api.patch(`/admin/orders/${id}/payment-status`, { payment_status: paymentStatus }))
    },
    async updateDetails(id, values) {
      return withMessage(await api.patch(`/admin/orders/${id}`, values))
    },
  },

  customers: {
    ...resource('customers'),
    async setActive(id, isActive) {
      return withMessage(await api.patch(`/admin/customers/${id}/status`, { is_active: isActive }))
    },
  },

  inventory: {
    async list(params) {
      return unwrapPage(await api.get('/admin/inventory', { params: cleanParams(params) }))
    },
    async movements(params) {
      return unwrapPage(await api.get('/admin/inventory/movements', { params: cleanParams(params) }))
    },
    async adjust(values) {
      return withMessage(await api.post('/admin/inventory/adjustments', values))
    },
  },

  reviews: {
    ...resource('reviews'),
    async moderate(id, status) {
      return withMessage(await api.patch(`/admin/reviews/${id}/status`, { status }))
    },
  },

  coupons: {
    ...resource('coupons'),
    async toggle(id) {
      return withMessage(await api.patch(`/admin/coupons/${id}/toggle`))
    },
    async usages(id, params) {
      return unwrapPage(await api.get(`/admin/coupons/${id}/usages`, { params: cleanParams(params) }))
    },
  },

  users: {
    ...resource('users'),
    async setActive(id, isActive) {
      return withMessage(await api.patch(`/admin/users/${id}/status`, { is_active: isActive }))
    },
    async assignableRoles() {
      return unwrap(await api.get('/admin/users/assignable-roles'))
    },
  },

  roles: {
    ...resource('roles'),
    async all() {
      return unwrap(await api.get('/admin/roles'))
    },
  },

  async permissions() {
    return unwrap(await api.get('/admin/permissions'))
  },

  reports: {
    async sales(params) {
      return unwrap(await api.get('/admin/reports/sales', { params: cleanParams(params) }))
    },
    async orders(params) {
      return unwrapPage(await api.get('/admin/reports/orders', { params: cleanParams(params) }))
    },
    async products(params) {
      return unwrapPage(await api.get('/admin/reports/products', { params: cleanParams(params) }))
    },
    async customers(params) {
      return unwrapPage(await api.get('/admin/reports/customers', { params: cleanParams(params) }))
    },
    async inventory(params) {
      return unwrapPage(await api.get('/admin/reports/inventory', { params: cleanParams(params) }))
    },
  },
}
