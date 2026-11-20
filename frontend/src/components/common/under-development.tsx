import { Construction } from 'lucide-react'
import { PageHeader } from '@/components/common/page-header'

export function UnderDevelopment({ title, description }: { title: string; description?: string }) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-16 sm:px-6">
      <div className="rounded-3xl border bg-card p-8">
        <Construction className="size-10 text-muted-foreground" aria-hidden="true" />
        <div className="mt-4">
          <PageHeader
            title={title}
            description={description ?? 'This section is being prepared for your role and will be available in a later release.'}
          />
        </div>
      </div>
    </div>
  )
}
