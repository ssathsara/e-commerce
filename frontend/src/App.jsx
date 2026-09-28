import { AuthProvider } from './contexts/AuthContext'
import { CartProvider } from './contexts/CartContext'
import { ToastProvider } from './contexts/ToastContext'
import { WishlistProvider } from './contexts/WishlistContext'
import { AppRoutes } from './routes/AppRoutes'
import { ScrollToTop } from './routes/ScrollToTop'
import { ErrorBoundary } from './components/common/ErrorBoundary'

export default function App() {
  return (
    <ErrorBoundary>
      <ToastProvider>
        <AuthProvider>
          <CartProvider>
            <WishlistProvider>
              <ScrollToTop />
              <AppRoutes />
            </WishlistProvider>
          </CartProvider>
        </AuthProvider>
      </ToastProvider>
    </ErrorBoundary>
  )
}
