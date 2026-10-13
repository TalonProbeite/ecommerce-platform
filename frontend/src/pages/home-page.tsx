import { ArrowRight, CalendarCheck, ShieldCheck, Truck } from 'lucide-react'
import { Link } from 'react-router-dom'
import { ROUTES } from '@/app/routes'
import { CategoryIcon } from '@/components/common/category-icon'
import { EquipmentCard } from '@/components/common/equipment-card'
import { LinkButton } from '@/components/common/link-button'
import { CATEGORIES, EQUIPMENT } from '@/data/equipment'

const STEPS = [
  { icon: ShieldCheck, title: 'Find verified gear', text: 'Every provider is vetted and every item is inspected before it ships.' },
  { icon: CalendarCheck, title: 'Pick your dates', text: 'Book for a day or a month with transparent pricing and no surprises.' },
  { icon: Truck, title: 'Pickup or delivery', text: 'Collect it nearby or have it delivered to your door, ready to use.' },
]

export function HomePage() {
  const featured = EQUIPMENT.filter((item) => item.featured && item.status === 'active').slice(0, 4)

  return (
    <>
      <section className="mx-auto max-w-7xl px-4 pt-8 sm:px-6 sm:pt-12">
        <div className="relative isolate overflow-hidden rounded-[2rem] bg-foreground text-background">
          <img src="/images/hero.png" alt="" className="absolute inset-0 -z-10 size-full object-cover opacity-60" />
          <div className="absolute inset-0 -z-10 bg-gradient-to-r from-foreground via-foreground/80 to-transparent" />
          <div className="max-w-2xl px-6 py-16 sm:px-12 sm:py-24">
            <p className="mb-4 inline-block rounded-full bg-background/15 px-4 py-1.5 text-sm font-semibold backdrop-blur">
              Rent it. Use it. Return it.
            </p>
            <h1 className="text-balance text-4xl font-extrabold tracking-tight sm:text-6xl">
              Premium gear, without the price tag.
            </h1>
            <p className="mt-5 max-w-lg text-pretty text-lg text-background/80">
              Cameras, tools, bikes and more from trusted providers near you. Book in minutes, pay only for the days you need.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <LinkButton to={ROUTES.catalog} size="lg" className="h-12 rounded-full px-7 text-base">
                Browse equipment <ArrowRight className="size-4" />
              </LinkButton>
              <LinkButton
                to={ROUTES.register}
                size="lg"
                variant="outline"
                className="h-12 rounded-full border-background/30 bg-transparent px-7 text-base text-background hover:bg-background/10 hover:text-background"
              >
                Create account
              </LinkButton>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto mt-16 max-w-7xl px-4 sm:px-6" aria-labelledby="categories-heading">
        <h2 id="categories-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">
          Shop by category
        </h2>
        <div className="mt-6 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-5">
          {CATEGORIES.map((category) => (
            <Link
              key={category.id}
              to={`${ROUTES.catalog}?category=${category.id}`}
              className="group flex flex-col items-start gap-3 rounded-3xl border bg-card p-5 transition-all hover:-translate-y-1 hover:border-primary hover:shadow-lg hover:shadow-primary/10"
            >
              <span className="grid size-12 place-items-center rounded-2xl bg-secondary text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                <CategoryIcon category={category.id} className="size-6" />
              </span>
              <span>
                <span className="block font-bold">{category.label}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{category.description}</span>
              </span>
            </Link>
          ))}
        </div>
      </section>

      <section className="mx-auto mt-20 max-w-7xl px-4 sm:px-6" aria-labelledby="featured-heading">
        <div className="flex items-end justify-between gap-4">
          <h2 id="featured-heading" className="text-2xl font-extrabold tracking-tight sm:text-3xl">
            Popular this week
          </h2>
          <Link to={ROUTES.catalog} className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
            View all <ArrowRight className="size-4" />
          </Link>
        </div>
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {featured.map((item) => (
            <EquipmentCard key={item.id} item={item} />
          ))}
        </div>
      </section>

      <section className="mx-auto mt-20 max-w-7xl px-4 sm:px-6" aria-labelledby="how-heading">
        <h2 id="how-heading" className="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">
          How Rentify works
        </h2>
        <ol className="mt-8 grid gap-4 md:grid-cols-3">
          {STEPS.map((step, index) => (
            <li key={step.title} className="rounded-3xl bg-secondary p-7">
              <span className="grid size-12 place-items-center rounded-2xl bg-background text-primary">
                <step.icon className="size-6" aria-hidden="true" />
              </span>
              <p className="mt-5 text-sm font-bold text-primary">Step {index + 1}</p>
              <h3 className="mt-1 text-lg font-bold">{step.title}</h3>
              <p className="mt-2 text-sm text-muted-foreground">{step.text}</p>
            </li>
          ))}
        </ol>
      </section>
    </>
  )
}
