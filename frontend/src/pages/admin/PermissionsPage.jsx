import { adminApi } from '../../api/adminApi'
import { Badge } from '../../components/common/Badge'
import { Card, PageHeader } from '../../components/common/Misc'
import { Skeleton } from '../../components/common/Skeleton'
import { ErrorState } from '../../components/common/States'
import { useAsync } from '../../hooks/useAsync'
import { useDocumentTitle } from '../../hooks/useDocumentTitle'

export default function PermissionsPage() {
  useDocumentTitle('Permissions')
  const { data, loading, error, reload } = useAsync(() => adminApi.permissions(), [])

  return (
    <div>
      <PageHeader title="Permissions" description="Every permission the application checks, and which roles grant it. Assign permissions by editing roles." />
      {error ? (
        <ErrorState error={error} onRetry={reload} />
      ) : loading ? (
        <div className="grid gap-4 lg:grid-cols-2">{[1, 2, 3, 4].map((i) => <Skeleton key={i} className="h-48 w-full rounded-xl" />)}</div>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {data.map((group) => (
            <Card key={group.group} title={group.group} bodyClassName="p-0">
              <ul className="divide-y divide-slate-100">
                {group.permissions.map((permission) => (
                  <li key={permission.id} className="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-sm">
                    <code className="text-slate-800">{permission.name}</code>
                    <span className="flex flex-wrap gap-1">
                      <Badge tone="violet">Super Admin</Badge>
                      {permission.roles.map((role) => (
                        <Badge key={role} tone="indigo">
                          {role}
                        </Badge>
                      ))}
                    </span>
                  </li>
                ))}
              </ul>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
