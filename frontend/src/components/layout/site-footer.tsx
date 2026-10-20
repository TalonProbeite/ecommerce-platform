import { Link } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { Logo } from '@/components/common/logo'
import { CATEGORIES } from '@/data/equipment'

export function SiteFooter() {
  return (
    <footer className="mt-24 border-t bg-card">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-3">
        <div>
          <Logo />
          <p className="mt-3 max-w-xs text-sm text-muted-foreground">
            Get the gear you need, when you need it. Rent from trusted providers near you.
          </p>
        </div>
        <nav aria-label="Categories">
          <h2 className="text-sm font-bold">Categories</h2>
          <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
            {CATEGORIES.map((category) => (
              <li key={category.id}>
                <Link to={`${ROUTES.catalog}?category=${category.id}`} className="hover:text-foreground">
                  {category.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <nav aria-label="Account">
          <h2 className="text-sm font-bold">Account</h2>
          <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
            <li>
              <Link to={ROUTES.orders} className="hover:text-foreground">My rentals</Link>
            </li>
            <li>
              <Link to={ROUTES.profile} className="hover:text-foreground">Profile</Link>
            </li>
            <li>
              <Link to={ROUTES.login} className="hover:text-foreground">Sign in</Link>
            </li>
          </ul>
        </nav>
      </div>
      <div className="border-t py-5 text-center text-xs text-muted-foreground">
        &copy; {new Date().getFullYear()} Rentify. All rights reserved.
      </div>
    </footer>
  )
}
