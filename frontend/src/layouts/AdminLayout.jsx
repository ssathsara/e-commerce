import { useEffect, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { AdminHeader } from '../components/admin/AdminHeader'
import { AdminSidebar } from '../components/admin/AdminSidebar'

export function AdminLayout() {
  const [drawerOpen, setDrawerOpen] = useState(false)
  const { pathname } = useLocation()

  useEffect(() => setDrawerOpen(false), [pathname])

  useEffect(() => {
    if (!drawerOpen) return undefined
    const onKey = (event) => event.key === 'Escape' && setDrawerOpen(false)
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [drawerOpen])

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Desktop sidebar */}
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 lg:block">
        <AdminSidebar />
      </aside>

      {/* Mobile / tablet drawer */}
      {drawerOpen && (
        <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Admin navigation">
          <div className="absolute inset-0 bg-slate-900/50" onClick={() => setDrawerOpen(false)} aria-hidden="true" />
          <aside className="absolute inset-y-0 left-0 w-64 max-w-[85%] animate-slide-up shadow-xl">
            <AdminSidebar onNavigate={() => setDrawerOpen(false)} />
          </aside>
        </div>
      )}

      <div className="lg:pl-64">
        <AdminHeader onMenu={() => setDrawerOpen(true)} />
        <main id="main" className="px-4 py-6 sm:px-6 lg:px-8" tabIndex={-1}>
          <Outlet />
        </main>
      </div>
    </div>
  )
}
