import { useEffect } from 'react'

const APP_NAME = import.meta.env.VITE_APP_NAME || 'ShopSphere'

export function useDocumentTitle(title) {
  useEffect(() => {
    document.title = title ? `${title} · ${APP_NAME}` : APP_NAME
  }, [title])
}
