import { Button } from '@/components/ui/button'
import { ROUTES } from '@/app/routes'

export function RouteError() {
  return (
    <section className="mx-auto max-w-2xl px-4 py-16 sm:px-6" role="alert">
      <h1 className="text-3xl font-bold">This page could not load</h1>
      <p className="mt-3 text-muted-foreground">
        The app may have been updated or your connection was interrupted.
        Reload the page to try again.
      </p>
      <div className="mt-6 flex flex-wrap items-center gap-4">
        <Button onClick={() => window.location.reload()}>Reload page</Button>
        <a href={ROUTES.home} className="font-semibold text-primary underline underline-offset-4">Back to home</a>
      </div>
    </section>
  )
}
