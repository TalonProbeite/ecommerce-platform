import type { Equipment } from '@/types'
import type { Category as ApiCategory, Product, Store } from '@/types/catalog'

export const FALLBACK_IMAGE = '/placeholder.svg'

export function categoryNameFor(product: Product, categories: ApiCategory[]): string | undefined {
  if (product.category?.name) return product.category.name
  if (product.category_name) return product.category_name
  return categories.find((category) => category.id === product.category_id)?.name
}

export function storeFor(product: Product, stores: Store[]): Store | undefined {
  return stores.find((store) => store.id === product.store_id)
}

/**
 * Maps a catalog API product onto the existing marketplace view model.
 * Fields the backend does not expose (rating, reviews, featured, rentals) get
 * neutral values so the UI can degrade gracefully.
 */
export function toEquipment(product: Product, store?: Store, categoryName?: string): Equipment {
  const attributes = product.attributes ?? []
  return {
    id: product.id,
    name: product.name,
    category: product.category_id,
    categoryLabel: categoryName ?? product.category?.name ?? product.category_name ?? undefined,
    image: product.main_image_url || FALLBACK_IMAGE,
    pricePerDay: product.price_per_hour,
    priceUnit: 'hour',
    deposit: product.deposit_amount,
    rating: 0,
    reviewCount: 0,
    location: store?.location ?? '',
    provider: store?.name ?? '',
    providerVerified: false,
    description: product.description ?? '',
    features: attributes.map((attribute) => `${attribute.name}: ${attribute.value}`),
    includes: [],
    featured: false,
    status: product.is_active ? 'active' : 'paused',
    totalRentals: 0,
  }
}

export function flattenCategories(categories: ApiCategory[]): ApiCategory[] {
  const result: ApiCategory[] = []
  const seen = new Set<string>()
  const visit = (items: ApiCategory[]) => {
    for (const item of items) {
      if (seen.has(item.id)) continue
      seen.add(item.id)
      result.push(item)
      if (item.children?.length) visit(item.children)
    }
  }
  visit(categories)
  return result
}

export interface FlatCategory {
  category: ApiCategory
  depth: number
}

export function flattenCategoryTree(categories: ApiCategory[], depth = 0, result: FlatCategory[] = []): FlatCategory[] {
  for (const category of categories) {
    result.push({ category, depth })
    if (category.children?.length) flattenCategoryTree(category.children, depth + 1, result)
  }
  return result
}

export function descendantCategoryIds(categories: ApiCategory[], categoryId: string): Set<string> {
  const result = new Set<string>()
  const visit = (items: ApiCategory[], collecting: boolean) => {
    for (const item of items) {
      const isDescendant = collecting || item.id === categoryId
      if (collecting) result.add(item.id)
      if (item.children?.length) visit(item.children, isDescendant)
    }
  }
  visit(categories, false)
  return result
}
