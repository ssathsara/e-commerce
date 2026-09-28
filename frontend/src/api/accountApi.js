import { api, cleanParams, unwrap, unwrapPage } from './axios'

export const accountApi = {
  async profile() {
    return unwrap(await api.get('/account/profile'))
  },

  async updateProfile(payload) {
    return unwrap(await api.put('/account/profile', payload))
  },

  async changePassword(payload) {
    return (await api.put('/account/password', payload)).data.message
  },

  // Addresses
  async addresses() {
    return unwrap(await api.get('/account/addresses'))
  },

  async createAddress(payload) {
    return unwrap(await api.post('/account/addresses', payload))
  },

  async updateAddress(id, payload) {
    return unwrap(await api.put(`/account/addresses/${id}`, payload))
  },

  async deleteAddress(id) {
    await api.delete(`/account/addresses/${id}`)
  },

  // Wishlist
  async wishlist() {
    return unwrap(await api.get('/account/wishlist'))
  },

  async wishlistIds() {
    return unwrap(await api.get('/account/wishlist/ids'))
  },

  async addToWishlist(productId) {
    await api.post('/account/wishlist', { product_id: productId })
  },

  async removeFromWishlist(productId) {
    await api.delete(`/account/wishlist/${productId}`)
  },

  async moveWishlistItemToCart(productId, variantId = null) {
    await api.post(`/account/wishlist/${productId}/move-to-cart`, { variant_id: variantId })
  },

  // Reviews
  async myReviews(params) {
    return unwrapPage(await api.get('/account/reviews', { params: cleanParams(params) }))
  },

  async reviewEligibility(slug) {
    return unwrap(await api.get(`/products/${encodeURIComponent(slug)}/review-eligibility`))
  },

  async createReview(slug, payload) {
    const response = await api.post(`/products/${encodeURIComponent(slug)}/reviews`, payload)
    return { review: response.data.data, message: response.data.message }
  },

  async updateReview(id, payload) {
    return unwrap(await api.put(`/reviews/${id}`, payload))
  },

  async deleteReview(id) {
    await api.delete(`/reviews/${id}`)
  },
}
