/**
 * Permission names, mirroring backend App\Support\Permissions.
 * Used ONLY for UI visibility; the API enforces every permission itself.
 */
export const P = Object.freeze({
  ACCESS_ADMIN: 'access admin panel',

  VIEW_PRODUCTS: 'view products',
  CREATE_PRODUCTS: 'create products',
  EDIT_PRODUCTS: 'edit products',
  DELETE_PRODUCTS: 'delete products',

  VIEW_CATEGORIES: 'view categories',
  CREATE_CATEGORIES: 'create categories',
  EDIT_CATEGORIES: 'edit categories',
  DELETE_CATEGORIES: 'delete categories',

  VIEW_BRANDS: 'view brands',
  CREATE_BRANDS: 'create brands',
  EDIT_BRANDS: 'edit brands',
  DELETE_BRANDS: 'delete brands',

  VIEW_ORDERS: 'view orders',
  EDIT_ORDERS: 'edit orders',
  UPDATE_ORDERS: 'update orders',
  CANCEL_ORDERS: 'cancel orders',

  VIEW_CUSTOMERS: 'view customers',
  CREATE_CUSTOMERS: 'create customers',
  EDIT_CUSTOMERS: 'edit customers',
  DELETE_CUSTOMERS: 'delete customers',

  VIEW_INVENTORY: 'view inventory',
  MANAGE_INVENTORY: 'manage inventory',

  VIEW_REVIEWS: 'view reviews',
  APPROVE_REVIEWS: 'approve reviews',
  DELETE_REVIEWS: 'delete reviews',

  VIEW_COUPONS: 'view coupons',
  CREATE_COUPONS: 'create coupons',
  EDIT_COUPONS: 'edit coupons',
  DELETE_COUPONS: 'delete coupons',

  VIEW_REPORTS: 'view reports',
  VIEW_SALES_REPORTS: 'view sales reports',
  VIEW_INVENTORY_REPORTS: 'view inventory reports',

  VIEW_USERS: 'view users',
  CREATE_USERS: 'create users',
  EDIT_USERS: 'edit users',
  DELETE_USERS: 'delete users',

  VIEW_ROLES: 'view roles',
  CREATE_ROLES: 'create roles',
  EDIT_ROLES: 'edit roles',
  DELETE_ROLES: 'delete roles',
  ASSIGN_ROLES: 'assign roles',

  VIEW_PERMISSIONS: 'view permissions',
  ASSIGN_PERMISSIONS: 'assign permissions',
})

export const SUPER_ADMIN = 'Super Admin'
