import { api, cleanParams, unwrap } from './axios'

/**
 * Every cart call returns the full, server-computed cart. Prices are never sent.
 * `shippingMethod` only selects which shipping rate the returned totals include,
 * so the displayed total always matches what checkout will charge.
 */
const withShipping = (shippingMethod) => ({ params: cleanParams({ shipping_method: shippingMethod }) })

export const cartApi = {
  async get(shippingMethod) {
    return unwrap(await api.get('/cart', withShipping(shippingMethod)))
  },

  async addItem({ productId, variantId = null, quantity = 1 }, shippingMethod) {
    return unwrap(await api.post('/cart/items', { product_id: productId, variant_id: variantId, quantity }, withShipping(shippingMethod)))
  },

  async updateItem(itemId, quantity, shippingMethod) {
    return unwrap(await api.put(`/cart/items/${itemId}`, { quantity }, withShipping(shippingMethod)))
  },

  async removeItem(itemId, shippingMethod) {
    return unwrap(await api.delete(`/cart/items/${itemId}`, withShipping(shippingMethod)))
  },

  async clear(shippingMethod) {
    return unwrap(await api.delete('/cart', withShipping(shippingMethod)))
  },

  async applyCoupon(code, shippingMethod) {
    return unwrap(await api.post('/cart/coupon', { coupon_code: code }, withShipping(shippingMethod)))
  },

  async removeCoupon(shippingMethod) {
    return unwrap(await api.delete('/cart/coupon', withShipping(shippingMethod)))
  },

  async checkoutOptions() {
    return unwrap(await api.get('/checkout/options'))
  },
}
