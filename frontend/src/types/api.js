/**
 * JSDoc shapes of the main API payloads, for editor autocompletion and as living
 * documentation of the contract with the Laravel API (see docs/api.md).
 * All money values are MAJOR units (e.g. 19.99); the API stores cents.
 *
 * @typedef {{ success: boolean, message: string|null, data: any, meta?: PaginationMeta, errors?: Record<string, string[]> }} ApiEnvelope
 * @typedef {{ current_page: number, last_page: number, per_page: number, total: number, from: number|null, to: number|null }} PaginationMeta
 *
 * @typedef {{ id: number, name: string, email: string, phone: string|null, email_verified: boolean, is_active: boolean }} User
 * @typedef {{ user: User, roles: string[], permissions: string[] }} AuthUser
 *
 * @typedef {{ id: number, url: string, alt_text: string|null, sort_order: number, is_primary: boolean }} ProductImage
 * @typedef {{ id: number, name: string, sku: string, options: Record<string,string>, price: number, compare_at_price: number|null, stock_quantity: number, in_stock: boolean, is_active: boolean }} ProductVariant
 * @typedef {{ id: number, name: string, slug: string, sku: string, price: number, compare_at_price: number|null, discount_percentage: number,
 *   status: 'draft'|'active'|'inactive'|'out_of_stock', in_stock: boolean, is_low_stock: boolean, has_variants: boolean,
 *   rating_average: number, rating_count: number, image: ProductImage|null }} ProductCard
 *
 * @typedef {{ id: number, quantity: number, unit_price: number, line_total: number, available_stock: number, issue: string|null,
 *   product: { id: number, name: string, slug: string, sku: string, image: ProductImage|null }|null,
 *   variant: { id: number, name: string, sku: string }|null }} CartLine
 * @typedef {{ subtotal: number, discount: number, shipping: number, tax: number, total: number }} Totals
 * @typedef {{ id: number|null, token: string|null, items: CartLine[], item_count: number, totals: Totals, has_issues: boolean,
 *   coupon: { code: string, is_applied: boolean, error: string|null }|null, currency: string, tax_rate: number }} Cart
 *
 * @typedef {'pending'|'confirmed'|'processing'|'shipped'|'delivered'|'cancelled'|'refunded'} OrderStatus
 * @typedef {'pending'|'paid'|'failed'|'refunded'} PaymentStatus
 * @typedef {{ id: number, order_number: string, status: OrderStatus, payment_status: PaymentStatus, payment_method: 'card'|'cash_on_delivery',
 *   subtotal: number, discount_total: number, shipping_total: number, tax_total: number, grand_total: number, currency: string,
 *   can_cancel: boolean, can_pay: boolean, created_at: string }} Order
 */

export {}
