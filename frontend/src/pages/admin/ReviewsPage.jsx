import { useState } from 'react'
import { adminApi } from '../../api/adminApi'
import { ListToolbar } from '../../components/admin/ListToolbar'
import { StatusBadge } from '../../components/common/Badge'
import { Button } from '../../components/common/Button'
import { useConfirm } from '../../components/common/ConfirmDialog'
import { DataTable } from '../../components/common/DataTable'
import { Modal } from '../../components/common/Modal'
import { PageHeader, Rating } from '../../components/common/Misc'
import { Pagination } from '../../components/common/Pagination'
import { EmptyState } from '../../components/common/States'
import { P } from '../../constants/permissions'
import { REVIEW_STATUS_TONES } from '../../constants/statuses'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'
import { useResourceList } from '../../hooks/useResourceList'
import { formatDateTime } from '../../utils/format'

export default function ReviewsPage() {
  useDocumentTitle('Reviews')
  const { can } = useAuth()
  const toast = useToast()
  const { confirm, dialog } = useConfirm()
  const list = useResourceList((query) => adminApi.reviews.list({ ...query, per_page: 15 }))
  const [viewing, setViewing] = useState(null)

  const moderate = async (review, status) => {
    try {
      const { data, message } = await adminApi.reviews.moderate(review.id, status)
      toast.success(message)
      if (viewing?.id === review.id) setViewing(data)
      list.reload()
    } catch (e) {
      toast.error(e.message)
    }
  }

  const remove = (review) =>
    confirm({
      title: 'Delete review?',
      message: 'This permanently removes the review and updates the product rating.',
      confirmLabel: 'Delete review',
      onConfirm: async () => {
        try {
          toast.success(await adminApi.reviews.remove(review.id))
          setViewing(null)
          list.reload()
        } catch (e) {
          toast.error(e.message)
          throw e
        }
      },
    })

  const actions = (review) => (
    <div className="flex flex-wrap justify-end gap-1">
      <Button size="xs" variant="ghost" onClick={() => setViewing(review)}>
        View
      </Button>
      {can(P.APPROVE_REVIEWS) && review.status !== 'approved' && (
        <Button size="xs" variant="secondary" onClick={() => moderate(review, 'approved')}>
          Approve
        </Button>
      )}
      {can(P.APPROVE_REVIEWS) && review.status !== 'rejected' && (
        <Button size="xs" variant="secondary" onClick={() => moderate(review, 'rejected')}>
          Reject
        </Button>
      )}
      {can(P.DELETE_REVIEWS) && (
        <Button size="xs" variant="ghost" className="!text-red-600" onClick={() => remove(review)}>
          Delete
        </Button>
      )}
    </div>
  )

  const columns = [
    {
      key: 'review',
      header: 'Review',
      render: (r) => (
        <div className="max-w-md">
          <Rating value={r.rating} />
          <p className="mt-1 truncate font-medium text-slate-900">{r.title || 'Untitled'}</p>
          <p className="line-clamp-1 text-xs text-slate-500">{r.body}</p>
        </div>
      ),
    },
    { key: 'product', header: 'Product', render: (r) => r.product?.name, hideBelow: 'md' },
    { key: 'customer', header: 'Customer', render: (r) => r.customer?.name, hideBelow: 'lg' },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge value={r.status} tones={REVIEW_STATUS_TONES} /> },
    { key: 'actions', header: <span className="sr-only">Actions</span>, render: actions, className: 'text-right' },
  ]

  return (
    <div>
      <PageHeader title="Reviews" description={list.data?.meta ? `${list.data.meta.pending_count} review(s) awaiting moderation.` : 'Moderate customer reviews.'} />
      <div className="card overflow-hidden">
        <ListToolbar
          search={list.search}
          onSearch={list.setSearch}
          searchPlaceholder="Search text, product or customer…"
          params={list.params}
          setParams={list.setParams}
          filters={[
            { key: 'status', label: 'All statuses', options: [{ value: 'pending', label: 'Pending' }, { value: 'approved', label: 'Approved' }, { value: 'rejected', label: 'Rejected' }] },
            { key: 'rating', label: 'Any rating', options: [5, 4, 3, 2, 1].map((r) => ({ value: String(r), label: `${r} star${r > 1 ? 's' : ''}` })) },
          ]}
        />
        <DataTable caption="Reviews" columns={columns} rows={list.data?.items} loading={list.loading} error={list.error} onRetry={list.reload} empty={<EmptyState icon="chat" title="No reviews found" />} />
        <div className="border-t border-slate-100 px-4">
          <Pagination meta={list.data?.meta} onChange={list.setPage} />
        </div>
      </div>

      <Modal open={Boolean(viewing)} onClose={() => setViewing(null)} title="Review" size="lg" footer={viewing && actions(viewing)}>
        {viewing && (
          <div className="space-y-3 text-sm">
            <div className="flex items-center justify-between">
              <Rating value={viewing.rating} showValue />
              <StatusBadge value={viewing.status} tones={REVIEW_STATUS_TONES} />
            </div>
            {viewing.title && <h3 className="text-base font-semibold text-slate-900">{viewing.title}</h3>}
            <p className="whitespace-pre-line text-slate-700">{viewing.body}</p>
            <dl className="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3">
              <div>
                <dt className="text-slate-500">Product</dt>
                <dd className="font-medium">{viewing.product?.name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Customer</dt>
                <dd className="font-medium">{viewing.customer?.name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Submitted</dt>
                <dd>{formatDateTime(viewing.created_at)}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Moderated</dt>
                <dd>{viewing.moderated_at ? `${formatDateTime(viewing.moderated_at)} by ${viewing.moderator ?? '—'}` : '—'}</dd>
              </div>
            </dl>
          </div>
        )}
      </Modal>
      {dialog}
    </div>
  )
}
