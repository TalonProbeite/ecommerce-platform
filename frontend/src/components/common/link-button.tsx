import type { VariantProps } from 'class-variance-authority'
import { Link, type LinkProps } from 'react-router-dom'
import { buttonVariants } from '@/components/ui/button'
import { cn } from '@/lib/utils'

type LinkButtonProps = LinkProps & VariantProps<typeof buttonVariants>

export function LinkButton({ variant, size, className, ...props }: LinkButtonProps) {
  return <Link className={cn(buttonVariants({ variant, size }), className)} {...props} />
}
