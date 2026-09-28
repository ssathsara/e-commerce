import { P } from './permissions'

/**
 * Admin navigation. Each item lists the permission(s) needed to SEE it; the API
 * independently enforces the same permissions on every endpoint.
 */
export const ADMIN_NAVIGATION = [
  {
    label: 'Overview',
    items: [{ to: '/admin', label: 'Dashboard', icon: 'home', end: true, permission: P.ACCESS_ADMIN }],
  },
  {
    label: 'Catalogue',
    items: [
      { to: '/admin/products', label: 'Products', icon: 'box', permission: P.VIEW_PRODUCTS },
      { to: '/admin/categories', label: 'Categories', icon: 'folder', permission: P.VIEW_CATEGORIES },
      { to: '/admin/brands', label: 'Brands', icon: 'tag', permission: P.VIEW_BRANDS },
      { to: '/admin/inventory', label: 'Inventory', icon: 'archive', permission: P.VIEW_INVENTORY },
    ],
  },
  {
    label: 'Sales',
    items: [
      { to: '/admin/orders', label: 'Orders', icon: 'receipt', permission: P.VIEW_ORDERS },
      { to: '/admin/customers', label: 'Customers', icon: 'users', permission: P.VIEW_CUSTOMERS },
      { to: '/admin/coupons', label: 'Coupons', icon: 'ticket', permission: P.VIEW_COUPONS },
      { to: '/admin/reviews', label: 'Reviews', icon: 'chat', permission: P.VIEW_REVIEWS },
      { to: '/admin/reports', label: 'Reports', icon: 'chart', permission: [P.VIEW_REPORTS, P.VIEW_SALES_REPORTS, P.VIEW_INVENTORY_REPORTS] },
    ],
  },
  {
    label: 'Access control',
    items: [
      { to: '/admin/users', label: 'Users', icon: 'user', permission: P.VIEW_USERS },
      { to: '/admin/roles', label: 'Roles', icon: 'shield', permission: P.VIEW_ROLES },
      { to: '/admin/permissions', label: 'Permissions', icon: 'key', permission: P.VIEW_PERMISSIONS },
    ],
  },
]

/** Human labels for breadcrumb path segments. */
export const ADMIN_SEGMENT_LABELS = {
  admin: 'Dashboard',
  products: 'Products',
  categories: 'Categories',
  brands: 'Brands',
  inventory: 'Inventory',
  orders: 'Orders',
  customers: 'Customers',
  coupons: 'Coupons',
  reviews: 'Reviews',
  reports: 'Reports',
  users: 'Users',
  roles: 'Roles',
  permissions: 'Permissions',
  create: 'Create',
  edit: 'Edit',
}
