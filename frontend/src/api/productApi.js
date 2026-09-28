import { api, cleanParams, unwrap, unwrapPage } from './axios'

export const productApi = {
  async home() {
    return unwrap(await api.get('/home'))
  },

  async list(params, config = {}) {
    return unwrapPage(await api.get('/products', { params: cleanParams(params), ...config }))
  },

  async get(slug) {
    return unwrap(await api.get(`/products/${encodeURIComponent(slug)}`))
  },

  async related(slug) {
    return unwrap(await api.get(`/products/${encodeURIComponent(slug)}/related`))
  },

  async reviews(slug, params) {
    return unwrapPage(await api.get(`/products/${encodeURIComponent(slug)}/reviews`, { params: cleanParams(params) }))
  },

  async categories() {
    return unwrap(await api.get('/categories'))
  },

  async category(slug) {
    return unwrap(await api.get(`/categories/${encodeURIComponent(slug)}`))
  },

  async brands() {
    return unwrap(await api.get('/brands'))
  },

  async brand(slug) {
    return unwrap(await api.get(`/brands/${encodeURIComponent(slug)}`))
  },
}
