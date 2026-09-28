import { describe, expect, it } from 'vitest'
import { ApiError, normalizeError } from '../../src/utils/errors'
import { formatPrice, titleCase } from '../../src/utils/format'
import { safeRedirect } from '../../src/routes/guards'

describe('normalizeError', () => {
  it('maps a Laravel validation response to field errors', () => {
    const error = normalizeError({
      response: { status: 422, data: { success: false, message: 'Validation failed.', errors: { email: ['The email field is required.'] } } },
    })

    expect(error).toBeInstanceOf(ApiError)
    expect(error.isValidation).toBe(true)
    expect(error.fieldError('email')).toBe('The email field is required.')
  })

  it('reports network failures with a friendly message', () => {
    const error = normalizeError(new Error('Network Error'))

    expect(error.isNetworkError).toBe(true)
    expect(error.message).toMatch(/unable to reach the server/i)
  })

  it('never exposes server error details to the user', () => {
    const error = normalizeError({ response: { status: 500, data: { message: 'SQLSTATE[42S02]: table missing' } } })

    expect(error.message).not.toMatch(/SQLSTATE/)
    expect(error.message).toMatch(/something went wrong/i)
  })

  it('keeps business-rule messages from the API (e.g. 409 conflicts)', () => {
    const error = normalizeError({ response: { status: 409, data: { message: 'Only 2 unit(s) are available.' } } })

    expect(error.isConflict).toBe(true)
    expect(error.message).toBe('Only 2 unit(s) are available.')
  })
})

describe('formatting helpers', () => {
  it('formats currency', () => {
    expect(formatPrice(1234.5)).toBe('$1,234.50')
    expect(formatPrice(null)).toBe('—')
  })

  it('title-cases enum values', () => {
    expect(titleCase('cash_on_delivery')).toBe('Cash On Delivery')
  })
})

describe('safeRedirect', () => {
  it('only allows same-site relative redirects', () => {
    expect(safeRedirect('/checkout')).toBe('/checkout')
    expect(safeRedirect(encodeURIComponent('/account/orders?page=2'))).toBe('/account/orders?page=2')
    expect(safeRedirect('https://evil.example')).toBe('/account')
    expect(safeRedirect('//evil.example')).toBe('/account')
    expect(safeRedirect(null, '/admin')).toBe('/admin')
  })
})
