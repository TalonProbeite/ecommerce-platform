import { LogOut, Menu, Package, Search, ShoppingBag, UserRound, ChevronDown } from 'lucide-react'
import { useRef, useState, type FormEvent } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { ROUTES } from '@/app/routes'
import { Logo } from '@/components/common/logo'
import { LinkButton } from '@/components/common/link-button'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { cn } from '@/lib/utils'
import { NAV_BY_ROLE, fullName, initials } from '@/lib/roles'
import { useAuthStore } from '@/store/auth-store'
import { useCartStore } from '@/store/cart-store'

export function SiteHeader() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.status === 'authenticated' ? state.user : null)
  const logout = useAuthStore((state) => state.logout)
  const cartCount = useCartStore((state) => state.items.length)
  const [query, setQuery] = useState('')
  const [menuOpen, setMenuOpen] = useState(false)
  const [signingOut, setSigningOut] = useState(false)
  const logoutPending = useRef(false)

  const isCustomer = user?.role === 'customer'
  const navLinks = user ? (user.role ? NAV_BY_ROLE[user.role] : []) : []

  function handleSearch(event: FormEvent) {
    event.preventDefault()
    const trimmed = query.trim()
    navigate(trimmed ? `${ROUTES.catalog}?q=${encodeURIComponent(trimmed)}` : ROUTES.catalog)
    setMenuOpen(false)
  }

  async function handleLogout() {
    if (logoutPending.current) return
    logoutPending.current = true
    setSigningOut(true)
    try {
      await logout()
      toast.success('You have been signed out.')
    } catch {
      toast.error('Local session cleared. The server could not confirm sign out. Please try again when the connection is restored.')
    } finally {
      logoutPending.current = false
      setSigningOut(false)
      setMenuOpen(false)
      navigate(ROUTES.login)
    }
  }

  const navClass = ({ isActive }: { isActive: boolean }) =>
    cn(
      'rounded-full px-4 py-2 text-sm font-semibold transition-colors hover:bg-accent',
      isActive ? 'bg-accent text-accent-foreground' : 'text-muted-foreground',
    )

  const searchForm = (
    <form onSubmit={handleSearch} role="search" className="relative w-full">
      <Search className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
      <Input
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        placeholder="Search cameras, tools, bikes..."
        aria-label="Search equipment"
        className="h-10 rounded-full bg-muted pl-10"
      />
    </form>
  )

  return (
    <header className="sticky top-0 z-40 border-b bg-background/85 backdrop-blur-lg">
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        {navLinks.length > 0 ? (
          <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setMenuOpen(true)} aria-label="Open menu">
            <Menu className="size-5" />
          </Button>
        ) : null}
        <Logo />
        {navLinks.length > 0 ? (
          <nav aria-label="Main" className="ml-4 hidden items-center gap-1 md:flex">
            {navLinks.map((link) => (
              <NavLink key={link.to} to={link.to} className={navClass}>
                {link.label}
              </NavLink>
            ))}
          </nav>
        ) : null}
        {isCustomer ? <div className="mx-auto hidden max-w-md flex-1 lg:block">{searchForm}</div> : null}

        <div className="ml-auto flex items-center gap-2">
          {isCustomer ? (
            <LinkButton to={ROUTES.checkout} variant="ghost" size="icon" className="relative" aria-label={`Cart, ${cartCount} items`}>
              <ShoppingBag className="size-5" />
              {cartCount > 0 ? (
                <span className="absolute -right-0.5 -top-0.5 grid size-5 place-items-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">
                  {cartCount}
                </span>
              ) : null}
            </LinkButton>
          ) : null}

          {user ? (
            <DropdownMenu>
              <DropdownMenuTrigger
                className="flex items-center gap-1 rounded-full border p-1 pr-2 transition-colors hover:bg-accent outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                aria-label="Account menu"
              >
                <Avatar>
                  <AvatarFallback className="bg-secondary font-bold text-secondary-foreground">{initials(user) || user.email[0]?.toUpperCase()}</AvatarFallback>
                </Avatar>
                <ChevronDown className="size-3.5 text-muted-foreground" aria-hidden="true" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuGroup>
                  <DropdownMenuLabel className="flex min-w-0 flex-col gap-1 px-3 py-2">
                    <span className="font-semibold text-foreground">{fullName(user)}</span>
                    <span className="truncate text-xs font-normal text-muted-foreground">{user.email}</span>
                    {user.role === 'admin' || user.role === 'analyst' ? <span className="text-xs capitalize">{user.role}</span> : null}
                  </DropdownMenuLabel>
                  <DropdownMenuItem className="rounded-lg px-3 py-2" onClick={() => navigate(ROUTES.profile)}>
                    <UserRound /> My profile
                  </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                {isCustomer ? (
                  <DropdownMenuItem onClick={() => navigate(ROUTES.orders)}>
                    <Package /> My rentals
                  </DropdownMenuItem>
                ) : null}
                <DropdownMenuItem onClick={handleLogout} disabled={signingOut}>
                  <LogOut /> {signingOut ? 'Signing out...' : 'Sign out'}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          ) : (
            <>
              <LinkButton to={ROUTES.login} variant="ghost" className="hidden sm:inline-flex">
                Login
              </LinkButton>
              <LinkButton to={ROUTES.register} className="rounded-full">
                Get started
              </LinkButton>
            </>
          )}
        </div>
      </div>

      {navLinks.length > 0 ? (
        <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
          <SheetContent side="left" className="w-80">
            <SheetHeader>
              <SheetTitle>Menu</SheetTitle>
            </SheetHeader>
            <div className="flex flex-col gap-4 px-4">
              {isCustomer ? searchForm : null}
              <nav aria-label="Mobile" className="flex flex-col gap-1">
                {navLinks.map((link) => (
                  <NavLink key={link.to} to={link.to} className={navClass} onClick={() => setMenuOpen(false)}>
                    {link.label}
                  </NavLink>
                ))}
              </nav>
            </div>
          </SheetContent>
        </Sheet>
      ) : null}
    </header>
  )
}
