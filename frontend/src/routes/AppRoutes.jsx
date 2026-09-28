import { lazy, Suspense } from 'react'
import { Route, Routes } from 'react-router-dom'
import { PageSpinner } from '../components/common/Spinner'
import { P } from '../constants/permissions'
import { AdminLayout } from '../layouts/AdminLayout'
import { CustomerLayout } from '../layouts/CustomerLayout'
import { PublicLayout } from '../layouts/PublicLayout'
import { GuestOnly, RequireAuth, RequirePermission } from './guards'

// Public
const HomePage = lazy(() => import('../pages/public/HomePage'))
const ProductListPage = lazy(() => import('../pages/public/ProductListPage'))
const ProductDetailPage = lazy(() => import('../pages/public/ProductDetailPage'))
const CartPage = lazy(() => import('../pages/public/CartPage'))
const CheckoutPage = lazy(() => import('../pages/public/CheckoutPage'))
const OrderConfirmationPage = lazy(() => import('../pages/public/OrderConfirmationPage'))
const LoginPage = lazy(() => import('../pages/public/LoginPage'))
const RegisterPage = lazy(() => import('../pages/public/RegisterPage'))
const ForgotPasswordPage = lazy(() => import('../pages/public/ForgotPasswordPage'))
const ResetPasswordPage = lazy(() => import('../pages/public/ResetPasswordPage'))
const VerifyEmailPage = lazy(() => import('../pages/public/VerifyEmailPage'))
const NotFoundPage = lazy(() => import('../pages/public/NotFoundPage'))

// Customer
const AccountOverviewPage = lazy(() => import('../pages/customer/AccountOverviewPage'))
const ProfilePage = lazy(() => import('../pages/customer/ProfilePage'))
const OrdersPage = lazy(() => import('../pages/customer/OrdersPage'))
const OrderDetailPage = lazy(() => import('../pages/customer/OrderDetailPage'))
const AddressesPage = lazy(() => import('../pages/customer/AddressesPage'))
const WishlistPage = lazy(() => import('../pages/customer/WishlistPage'))
const SettingsPage = lazy(() => import('../pages/customer/SettingsPage'))

// Admin
const AdminDashboardPage = lazy(() => import('../pages/admin/DashboardPage'))
const AdminProductsPage = lazy(() => import('../pages/admin/ProductsPage'))
const AdminProductFormPage = lazy(() => import('../pages/admin/ProductFormPage'))
const AdminProductViewPage = lazy(() => import('../pages/admin/ProductViewPage'))
const AdminCategoriesPage = lazy(() => import('../pages/admin/CategoriesPage'))
const AdminBrandsPage = lazy(() => import('../pages/admin/BrandsPage'))
const AdminOrdersPage = lazy(() => import('../pages/admin/OrdersPage'))
const AdminOrderDetailPage = lazy(() => import('../pages/admin/OrderDetailPage'))
const AdminCustomersPage = lazy(() => import('../pages/admin/CustomersPage'))
const AdminCustomerDetailPage = lazy(() => import('../pages/admin/CustomerDetailPage'))
const AdminInventoryPage = lazy(() => import('../pages/admin/InventoryPage'))
const AdminReviewsPage = lazy(() => import('../pages/admin/ReviewsPage'))
const AdminCouponsPage = lazy(() => import('../pages/admin/CouponsPage'))
const AdminUsersPage = lazy(() => import('../pages/admin/UsersPage'))
const AdminRolesPage = lazy(() => import('../pages/admin/RolesPage'))
const AdminPermissionsPage = lazy(() => import('../pages/admin/PermissionsPage'))
const AdminReportsPage = lazy(() => import('../pages/admin/ReportsPage'))

const guard = (permission, element) => <RequirePermission permission={permission}>{element}</RequirePermission>

export function AppRoutes() {
  return (
    <Suspense fallback={<PageSpinner />}>
      <Routes>
        <Route element={<PublicLayout />}>
          <Route index element={<HomePage />} />
          <Route path="products" element={<ProductListPage />} />
          <Route path="products/:slug" element={<ProductDetailPage />} />
          <Route path="categories/:categorySlug" element={<ProductListPage />} />
          <Route path="brands/:brandSlug" element={<ProductListPage />} />
          <Route path="search" element={<ProductListPage />} />
          <Route path="cart" element={<CartPage />} />
          <Route path="verify-email" element={<VerifyEmailPage />} />

          <Route element={<GuestOnly />}>
            <Route path="login" element={<LoginPage />} />
            <Route path="register" element={<RegisterPage />} />
            <Route path="forgot-password" element={<ForgotPasswordPage />} />
            <Route path="reset-password" element={<ResetPasswordPage />} />
          </Route>

          <Route element={<RequireAuth />}>
            <Route path="checkout" element={<CheckoutPage />} />
            <Route path="checkout/complete/:orderId" element={<OrderConfirmationPage />} />

            <Route path="account" element={<CustomerLayout />}>
              <Route index element={<AccountOverviewPage />} />
              <Route path="profile" element={<ProfilePage />} />
              <Route path="orders" element={<OrdersPage />} />
              <Route path="orders/:orderId" element={<OrderDetailPage />} />
              <Route path="addresses" element={<AddressesPage />} />
              <Route path="wishlist" element={<WishlistPage />} />
              <Route path="settings" element={<SettingsPage />} />
            </Route>
          </Route>

          <Route path="*" element={<NotFoundPage />} />
        </Route>

        <Route
          path="admin"
          element={
            <RequireAuth>
              <RequirePermission permission={P.ACCESS_ADMIN}>
                <AdminLayout />
              </RequirePermission>
            </RequireAuth>
          }
        >
          <Route index element={<AdminDashboardPage />} />
          <Route path="products" element={guard(P.VIEW_PRODUCTS, <AdminProductsPage />)} />
          <Route path="products/create" element={guard(P.CREATE_PRODUCTS, <AdminProductFormPage />)} />
          <Route path="products/:productId" element={guard(P.VIEW_PRODUCTS, <AdminProductViewPage />)} />
          <Route path="products/:productId/edit" element={guard(P.EDIT_PRODUCTS, <AdminProductFormPage />)} />
          <Route path="categories" element={guard(P.VIEW_CATEGORIES, <AdminCategoriesPage />)} />
          <Route path="brands" element={guard(P.VIEW_BRANDS, <AdminBrandsPage />)} />
          <Route path="orders" element={guard(P.VIEW_ORDERS, <AdminOrdersPage />)} />
          <Route path="orders/:orderId" element={guard(P.VIEW_ORDERS, <AdminOrderDetailPage />)} />
          <Route path="customers" element={guard(P.VIEW_CUSTOMERS, <AdminCustomersPage />)} />
          <Route path="customers/:customerId" element={guard(P.VIEW_CUSTOMERS, <AdminCustomerDetailPage />)} />
          <Route path="inventory" element={guard(P.VIEW_INVENTORY, <AdminInventoryPage />)} />
          <Route path="reviews" element={guard(P.VIEW_REVIEWS, <AdminReviewsPage />)} />
          <Route path="coupons" element={guard(P.VIEW_COUPONS, <AdminCouponsPage />)} />
          <Route path="users" element={guard(P.VIEW_USERS, <AdminUsersPage />)} />
          <Route path="roles" element={guard(P.VIEW_ROLES, <AdminRolesPage />)} />
          <Route path="permissions" element={guard(P.VIEW_PERMISSIONS, <AdminPermissionsPage />)} />
          <Route path="reports" element={guard([P.VIEW_REPORTS, P.VIEW_SALES_REPORTS, P.VIEW_INVENTORY_REPORTS], <AdminReportsPage />)} />
          <Route path="*" element={<NotFoundPage embedded />} />
        </Route>
      </Routes>
    </Suspense>
  )
}
