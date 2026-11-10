export interface Store {
  id: string
  name: string
  description: string | null
  location: string | null
  active: boolean
  created_at?: string | null
  updated_at?: string | null
}

export interface Category {
  id: string
  store_id: string
  parent_id: string | null
  name: string
  slug: string
  children?: Category[]
  ancestors?: Array<{ id: string; name: string }>
  created_at?: string | null
  updated_at?: string | null
}

export interface ProductAttribute {
  id?: string
  name: string
  value: string
}

export interface ProductImage {
  id: string
  product_id: string
  url: string
  mime_type: string
  size: number
  is_main: boolean
  created_at?: string | null
}

export interface Stock {
  product_id: string
  quantity: number
  reserved: number
  available: number
}

export interface Product {
  id: string
  store_id: string
  category_id: string
  name: string
  slug: string
  description: string | null
  price_per_hour: number
  deposit_amount: number
  is_active: boolean
  main_image_url: string | null
  category_name?: string | null
  category?: { id: string; name: string } | null
  attributes?: ProductAttribute[]
  images?: ProductImage[]
  stock?: Stock | null
  created_at?: string | null
  updated_at?: string | null
}

export interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

export interface PaginatedResponse<T> {
  data: T[]
  links?: {
    first?: string | null
    last?: string | null
    prev?: string | null
    next?: string | null
  }
  meta?: {
    current_page: number
    from: number | null
    last_page: number
    links?: PaginationLink[]
    path?: string
    per_page: number
    to: number | null
    total: number
  }
}

export interface StoreListParams {
  active?: boolean
  page?: number
  per_page?: number
}

export type ProductSortField = 'price_per_hour' | 'created_at'
export type SortDirection = 'asc' | 'desc'

export interface ProductListParams {
  store_id?: string
  category_id?: string
  min_price?: number
  max_price?: number
  max_deposit?: number
  available_only?: boolean
  is_active?: boolean
  sort?: ProductSortField
  sort_direction?: SortDirection
  page?: number
  per_page?: number
}

export interface CreateStorePayload {
  name: string
  description?: string | null
  location?: string | null
  active?: boolean
}

export interface UpdateStorePayload {
  name?: string
  description?: string | null
  location?: string | null
  active?: boolean
}

export interface CreateCategoryPayload {
  name: string
  slug: string
  parent_id?: string | null
}

export interface UpdateCategoryPayload {
  name?: string
  slug?: string
  parent_id?: string | null
}

export interface CreateProductPayload {
  name: string
  slug: string
  category_id: string
  price_per_hour: number
  deposit_amount?: number
  quantity: number
  description?: string | null
  is_active?: boolean
  attributes?: Array<{ name: string; value: string }>
}

export interface UpdateProductPayload {
  name?: string
  slug?: string
  category_id?: string
  price_per_hour?: number
  deposit_amount?: number
  description?: string | null
  is_active?: boolean
  attributes?: Array<{ name: string; value: string }>
}
