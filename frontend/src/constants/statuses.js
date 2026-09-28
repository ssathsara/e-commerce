/** Badge tones for status values returned by the API. */
export const ORDER_STATUS_TONES = {
  pending: 'amber',
  confirmed: 'blue',
  processing: 'indigo',
  shipped: 'violet',
  delivered: 'green',
  cancelled: 'slate',
  refunded: 'rose',
}

export const PAYMENT_STATUS_TONES = {
  pending: 'amber',
  paid: 'green',
  failed: 'red',
  refunded: 'rose',
}

export const PRODUCT_STATUS_TONES = {
  draft: 'slate',
  active: 'green',
  inactive: 'amber',
  out_of_stock: 'red',
}

export const REVIEW_STATUS_TONES = {
  pending: 'amber',
  approved: 'green',
  rejected: 'red',
}

export const PRODUCT_SORTS = [
  { value: 'newest', label: 'Newest' },
  { value: 'popular', label: 'Most popular' },
  { value: 'rating', label: 'Top rated' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
]

/** Mock gateway test tokens (see backend MockPaymentGateway). */
export const TEST_CARDS = [
  { token: 'tok_visa', label: 'Visa •••• 4242', outcome: 'Payment succeeds' },
  { token: 'tok_mastercard', label: 'Mastercard •••• 4444', outcome: 'Payment succeeds' },
  { token: 'tok_chargeDeclined', label: 'Visa •••• 0002', outcome: 'Card declined' },
  { token: 'tok_insufficientFunds', label: 'Visa •••• 9995', outcome: 'Insufficient funds' },
]
