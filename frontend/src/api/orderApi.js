import { api, cleanParams, unwrap, unwrapPage } from './axios'

export const orderApi = {
  async list(params) {
    return unwrapPage(await api.get('/orders', { params: cleanParams(params) }))
  },

  async get(id) {
    return unwrap(await api.get(`/orders/${id}`))
  },

  /**
   * Places the order. The same idempotencyKey must be reused on retries so a
   * double-click or network retry can never create two orders.
   * Resolves with { order, created, paymentError }.
   */
  async place(payload) {
    const response = await api.post('/orders', payload)
    return {
      order: response.data.data,
      created: response.status === 201,
      paymentError: response.data.payment_error ?? null,
      message: response.data.message,
    }
  },

  async cancel(id) {
    return unwrap(await api.post(`/orders/${id}/cancel`))
  },

  async pay(id, { paymentToken, idempotencyKey }) {
    return unwrap(await api.post(`/orders/${id}/payments`, { payment_token: paymentToken, idempotency_key: idempotencyKey }))
  },
}
