/**
 * Uniform error shape for every failed API call, so screens never need to
 * inspect raw Axios errors.
 */
export class ApiError extends Error {
  constructor({ status = 0, message, errors = {}, data = null }) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.data = data
  }

  get isNetworkError() {
    return this.status === 0
  }

  get isValidation() {
    return this.status === 422
  }

  get isUnauthenticated() {
    return this.status === 401
  }

  get isForbidden() {
    return this.status === 403
  }

  get isNotFound() {
    return this.status === 404
  }

  get isConflict() {
    return this.status === 409
  }

  /** First message for a field, e.g. fieldError('email'). */
  fieldError(field) {
    const value = this.errors?.[field]
    return Array.isArray(value) ? value[0] : value
  }
}

const DEFAULT_MESSAGES = {
  0: 'Unable to reach the server. Please check your connection and try again.',
  401: 'Please sign in to continue.',
  403: 'You do not have permission to perform this action.',
  404: 'The requested resource was not found.',
  409: 'This action conflicts with the current state. Please refresh and try again.',
  419: 'Your session has expired. Please refresh the page.',
  422: 'Please correct the highlighted fields.',
  429: 'Too many requests. Please wait a moment and try again.',
  500: 'Something went wrong on our side. Please try again later.',
}

export function normalizeError(error) {
  if (error instanceof ApiError) return error

  const response = error?.response
  if (!response) {
    return new ApiError({ status: 0, message: DEFAULT_MESSAGES[0] })
  }

  const { status, data } = response
  const fallback = DEFAULT_MESSAGES[status] ?? (status >= 500 ? DEFAULT_MESSAGES[500] : 'Request failed.')
  const message = typeof data?.message === 'string' && data.message ? data.message : fallback

  return new ApiError({ status, message: status >= 500 ? DEFAULT_MESSAGES[500] : message, errors: data?.errors ?? {}, data })
}

/** Human-readable message for any thrown value. */
export function errorMessage(error) {
  return normalizeError(error).message
}
