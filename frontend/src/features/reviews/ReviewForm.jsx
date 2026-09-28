import { accountApi } from '../../api/accountApi'
import { Button } from '../../components/common/Button'
import { RatingInput } from '../../components/common/Misc'
import { Input, Textarea } from '../../components/forms/Fields'
import { useToast } from '../../contexts/ToastContext'
import { useForm } from '../../hooks/useForm'

export function ReviewForm({ productSlug, onSubmitted }) {
  const toast = useToast()
  const form = useForm({ rating: 0, title: '', body: '' })

  const onSubmit = form.submit(
    async (values) => {
      const { message } = await accountApi.createReview(productSlug, values)
      toast.success(message)
      form.reset()
      onSubmitted?.()
    },
    { onError: (error) => toast.error(error.message) },
  )

  return (
    <form onSubmit={onSubmit} className="card space-y-4 p-5" noValidate>
      <h3 className="text-base font-semibold text-slate-900">Write a review</h3>
      <div>
        <span className="mb-1.5 block text-sm font-medium text-slate-700">Your rating</span>
        <RatingInput value={form.values.rating} onChange={(rating) => form.set('rating', rating)} />
        {form.error('rating') && <p className="mt-1 text-xs font-medium text-red-600">{form.error('rating')}</p>}
      </div>
      <Input label="Title" maxLength={120} {...form.bind('title')} placeholder="Summarise your experience" />
      <Textarea label="Review" required rows={4} maxLength={5000} {...form.bind('body')} placeholder="What did you like or dislike?" hint="At least 10 characters." />
      <Button type="submit" loading={form.submitting} disabled={!form.values.rating}>
        Submit review
      </Button>
    </form>
  )
}
