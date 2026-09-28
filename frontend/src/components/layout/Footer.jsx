import { Link } from 'react-router-dom'
import { useCategories } from '../../hooks/useCategories'
import { Icon } from '../common/Icon'
import { Logo } from './Logo'

const PERKS = [
  { icon: 'truck', title: 'Free shipping', text: 'On standard orders over $100' },
  { icon: 'refresh', title: '30-day returns', text: 'Easy, no-hassle returns' },
  { icon: 'shield', title: 'Secure checkout', text: 'Your data stays protected' },
]

export function Footer() {
  const { categories } = useCategories()

  return (
    <footer className="mt-16 border-t border-slate-200 bg-white">
      <div className="container-page grid gap-6 border-b border-slate-100 py-8 sm:grid-cols-3">
        {PERKS.map((perk) => (
          <div key={perk.title} className="flex items-center gap-3">
            <span className="rounded-full bg-brand-50 p-2.5 text-brand-600">
              <Icon name={perk.icon} className="h-5 w-5" />
            </span>
            <div>
              <p className="text-sm font-semibold text-slate-900">{perk.title}</p>
              <p className="text-sm text-slate-500">{perk.text}</p>
            </div>
          </div>
        ))}
      </div>

      <div className="container-page grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-3">
          <Logo />
          <p className="max-w-xs text-sm text-slate-500">Thoughtfully chosen products for everyday life, delivered to your door.</p>
        </div>
        <div>
          <h2 className="text-sm font-semibold text-slate-900">Shop</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {categories.slice(0, 5).map((category) => (
              <li key={category.id}>
                <Link to={`/categories/${category.slug}`} className="text-slate-500 hover:text-slate-900">
                  {category.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h2 className="text-sm font-semibold text-slate-900">Account</h2>
          <ul className="mt-3 space-y-2 text-sm">
            <li>
              <Link to="/account" className="text-slate-500 hover:text-slate-900">My account</Link>
            </li>
            <li>
              <Link to="/account/orders" className="text-slate-500 hover:text-slate-900">Order history</Link>
            </li>
            <li>
              <Link to="/account/wishlist" className="text-slate-500 hover:text-slate-900">Wishlist</Link>
            </li>
            <li>
              <Link to="/cart" className="text-slate-500 hover:text-slate-900">Cart</Link>
            </li>
          </ul>
        </div>
        <div>
          <h2 className="text-sm font-semibold text-slate-900">Customer care</h2>
          <ul className="mt-3 space-y-2 text-sm text-slate-500">
            <li>Standard delivery: 3–5 business days</li>
            <li>Express delivery: 1–2 business days</li>
            <li>support@shopsphere.test</li>
          </ul>
        </div>
      </div>
      <div className="border-t border-slate-100 py-5">
        <p className="container-page text-xs text-slate-400">© {new Date().getFullYear()} ShopSphere. Demo store — payments use a test gateway.</p>
      </div>
    </footer>
  )
}
