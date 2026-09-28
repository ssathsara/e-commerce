import { api, ensureCsrfCookie, unwrap } from './axios'

export const authApi = {
  async me() {
    return unwrap(await api.get('/auth/me', { skipAuthEvent: true }))
  },

  async login(credentials) {
    await ensureCsrfCookie(true)
    return unwrap(await api.post('/auth/login', credentials))
  },

  async register(payload) {
    await ensureCsrfCookie(true)
    return unwrap(await api.post('/auth/register', payload))
  },

  async logout() {
    await api.post('/auth/logout', null, { skipAuthEvent: true })
  },

  async forgotPassword(email) {
    return (await api.post('/auth/forgot-password', { email })).data.message
  },

  async resetPassword(payload) {
    return (await api.post('/auth/reset-password', payload)).data.message
  },

  async resendVerification() {
    return (await api.post('/auth/email/verification-notification')).data.message
  },

  /** `path` is the signed relative path from the verification email link. */
  async verifyEmail(path) {
    const relative = path.replace(/^\/?api\/v1/, '')
    return unwrap(await api.post(relative))
  },
}
