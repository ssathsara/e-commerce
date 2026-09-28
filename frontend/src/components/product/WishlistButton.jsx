import { useNavigate } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
import { useToast } from '../../contexts/ToastContext'
import { useWishlist } from '../../contexts/WishlistContext'
import { Icon } from '../common/Icon'

export function WishlistButton({ productId, productName, className = '', withLabel = false }) {
  const { isAuthenticated } = useAuth()
  const { has, toggle } = useWishlist()
  const toast = useToast()
  const navigate = useNavigate()
  const active = has(productId)

  const onClick = async (event) => {
    event.preventDefault()
    event.stopPropagation()

    if (!isAuthenticated) {
      toast.info('Sign in to save items to your wishlist.')
      navigate(`/login?redirect=${encodeURIComponent(window.location.pathname)}`)
      return
    }

    try {
      const added = await toggle(productId)
      toast.success(added ? 'Saved to your wishlist.' : 'Removed from your wishlist.')
    } catch (error) {
      toast.error(error.message)
    }
  }

  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      aria-label={active ? `Remove ${productName} from wishlist` : `Add ${productName} to wishlist`}
      className={`inline-flex items-center gap-2 transition-colors ${active ? 'text-rose-500' : 'text-slate-500 hover:text-rose-500'} ${className}`}
    >
      <Icon name="heart" className={`h-5 w-5 ${active ? 'fill-rose-500' : ''}`} />
      {withLabel && <span className="text-sm font-medium">{active ? 'Saved' : 'Save to wishlist'}</span>}
    </button>
  )
}
