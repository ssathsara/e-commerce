import { useCallback, useRef, useState } from 'react'
import { normalizeError } from '../utils/errors'

/**
 * Form state + server-side validation errors.
 *
 *   const form = useForm({ email: '' })
 *   <Input {...form.bind('email')} />
 *   <form onSubmit={form.submit(values => api.save(values), { onError })}>
 *
 * 422 responses populate field errors (keys match Laravel, e.g. "variants.0.sku").
 * `submit` never rethrows; failures go to `onError`. It also ignores re-entrant
 * submissions, preventing duplicate requests from double-clicks.
 */
export function useForm(initialValues) {
  const [values, setValues] = useState(initialValues)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const inFlight = useRef(false)

  const set = useCallback((field, value) => {
    setValues((current) => ({ ...current, [field]: value }))
    setErrors((current) => {
      if (!current[field]) return current
      const next = { ...current }
      delete next[field]
      return next
    })
  }, [])

  const bind = useCallback(
    (field) => ({
      name: field,
      value: values[field] ?? '',
      onChange: (event) => set(field, event.target.value),
      error: fieldError(errors, field),
    }),
    [values, errors, set],
  )

  const bindCheckbox = useCallback(
    (field) => ({
      name: field,
      checked: Boolean(values[field]),
      onChange: (event) => set(field, event.target.checked),
      error: fieldError(errors, field),
    }),
    [values, errors, set],
  )

  const submit = useCallback(
    (handler, { onError } = {}) =>
      async (event) => {
        event?.preventDefault?.()
        if (inFlight.current) return undefined

        inFlight.current = true
        setSubmitting(true)
        setErrors({})

        try {
          return await handler(values)
        } catch (raw) {
          const error = normalizeError(raw)
          if (error.isValidation) setErrors(error.errors)
          onError?.(error)
          return undefined
        } finally {
          inFlight.current = false
          setSubmitting(false)
        }
      },
    [values],
  )

  const reset = useCallback((next = initialValues) => {
    setValues(next)
    setErrors({})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return { values, setValues, set, errors, setErrors, error: (f) => fieldError(errors, f), bind, bindCheckbox, submit, submitting, reset }
}

function fieldError(errors, field) {
  const value = errors?.[field]
  return Array.isArray(value) ? value[0] : value
}
