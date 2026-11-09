import { catalogAdmin, catalogGet } from '@/lib/catalog/api'
import type {
  Category,
  CreateCategoryPayload,
  CreateProductPayload,
  CreateStorePayload,
  PaginatedResponse,
  Product,
  ProductImage,
  ProductListParams,
  Stock,
  Store,
  StoreListParams,
  UpdateCategoryPayload,
  UpdateProductPayload,
  UpdateStorePayload,
} from '@/types/catalog'

const enc = encodeURIComponent

// Empty strings are omitted by default. listAllStores deliberately sends
// active= (empty), which Laravel normalizes to null and treats as "no filter".
function query(params: object): Record<string, string | number | boolean> {
  const result: Record<string, string | number | boolean> = {}
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    result[key] = value as string | number | boolean
  }
  return result
}

function json(method: string, data?: unknown): RequestInit {
  return data === undefined
    ? { method }
    : { method, body: JSON.stringify(data) }
}

// ---------------------------------------------------------------------------
// Public routes
// ---------------------------------------------------------------------------

export async function listStores(params: StoreListParams = {}, signal?: AbortSignal): Promise<PaginatedResponse<Store>> {
  return catalogGet<PaginatedResponse<Store>>('/stores', query(params), signal)
}

export async function getStore(storeId: string, signal?: AbortSignal): Promise<Store> {
  const response = await catalogGet<{ data: Store }>(`/stores/${enc(storeId)}`, undefined, signal)
  return response.data
}

export async function listStoreCategories(storeId: string, signal?: AbortSignal): Promise<Category[]> {
  const response = await catalogGet<{ data: Category[] }>(`/stores/${enc(storeId)}/categories`, undefined, signal)
  return response.data
}

export async function getStoreCategory(storeId: string, categoryId: string, signal?: AbortSignal): Promise<Category> {
  const response = await catalogGet<{ data: Category }>(
    `/stores/${enc(storeId)}/categories/${enc(categoryId)}`,
    undefined,
    signal,
  )
  return response.data
}

export async function listStoreCategoryChildren(storeId: string, categoryId: string, signal?: AbortSignal): Promise<Category[]> {
  const response = await catalogGet<{ data: Category[] }>(
    `/stores/${enc(storeId)}/categories/${enc(categoryId)}/children`,
    undefined,
    signal,
  )
  return response.data
}

export async function listProducts(params: ProductListParams = {}, signal?: AbortSignal): Promise<PaginatedResponse<Product>> {
  return catalogGet<PaginatedResponse<Product>>('/products', query(params), signal)
}

export async function getProduct(productId: string, signal?: AbortSignal): Promise<Product> {
  const response = await catalogGet<{ data: Product }>(`/products/${enc(productId)}`, undefined, signal)
  return response.data
}

export async function listProductImages(productId: string, signal?: AbortSignal): Promise<ProductImage[]> {
  const response = await catalogGet<{ data: ProductImage[] }>(`/products/${enc(productId)}/images`, undefined, signal)
  return response.data
}

export async function getProductImage(productId: string, imageId: string, signal?: AbortSignal): Promise<ProductImage> {
  const response = await catalogGet<{ data: ProductImage }>(
    `/products/${enc(productId)}/images/${enc(imageId)}`,
    undefined,
    signal,
  )
  return response.data
}

// ---------------------------------------------------------------------------
// Admin routes. Authorization is injected by the gateway from the HttpOnly
// access cookie; the frontend never sees or stores the token.
// ---------------------------------------------------------------------------

const ADMIN = '/admin'

/** Lists every store, including inactive ones (active= empty => no filter). */
export async function listAllStores(signal?: AbortSignal): Promise<PaginatedResponse<Store>> {
  return catalogGet<PaginatedResponse<Store>>('/stores', { active: '', page: 1, per_page: 100 }, signal)
}

export async function adminCreateStore(payload: CreateStorePayload): Promise<Store> {
  const response = await catalogAdmin<{ data: Store }>(`${ADMIN}/stores`, json('post', payload))
  return response.data
}

export async function adminUpdateStore(storeId: string, payload: UpdateStorePayload): Promise<Store> {
  const response = await catalogAdmin<{ data: Store }>(`${ADMIN}/stores/${enc(storeId)}`, json('patch', payload))
  return response.data
}

export async function adminDeleteStore(storeId: string): Promise<void> {
  await catalogAdmin<null>(`${ADMIN}/stores/${enc(storeId)}`, { method: 'delete' })
}

export async function adminCreateCategory(storeId: string, payload: CreateCategoryPayload): Promise<Category> {
  const response = await catalogAdmin<{ data: Category }>(
    `${ADMIN}/stores/${enc(storeId)}/categories`,
    json('post', payload),
  )
  return response.data
}

export async function adminUpdateCategory(
  storeId: string,
  categoryId: string,
  payload: UpdateCategoryPayload,
): Promise<Category> {
  const response = await catalogAdmin<{ data: Category }>(
    `${ADMIN}/stores/${enc(storeId)}/categories/${enc(categoryId)}`,
    json('patch', payload),
  )
  return response.data
}

export async function adminDeleteCategory(storeId: string, categoryId: string): Promise<void> {
  await catalogAdmin<null>(`${ADMIN}/stores/${enc(storeId)}/categories/${enc(categoryId)}`, { method: 'delete' })
}

export async function adminListProducts(
  storeId: string,
  params: Omit<ProductListParams, 'store_id'> = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<Product>> {
  return catalogAdmin<PaginatedResponse<Product>>(
    `${ADMIN}/stores/${enc(storeId)}/products`,
    { method: 'get', signal },
    query(params),
  )
}

export async function adminGetProduct(storeId: string, productId: string, signal?: AbortSignal): Promise<Product> {
  const response = await catalogAdmin<{ data: Product }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}`,
    { method: 'get', signal },
  )
  return response.data
}

export async function adminCreateProduct(storeId: string, payload: CreateProductPayload): Promise<Product> {
  const response = await catalogAdmin<{ data: Product }>(
    `${ADMIN}/stores/${enc(storeId)}/products`,
    json('post', payload),
  )
  return response.data
}

export async function adminUpdateProduct(
  storeId: string,
  productId: string,
  payload: UpdateProductPayload,
): Promise<Product> {
  const response = await catalogAdmin<{ data: Product }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}`,
    json('patch', payload),
  )
  return response.data
}

export async function adminDeleteProduct(storeId: string, productId: string): Promise<void> {
  await catalogAdmin<null>(`${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}`, { method: 'delete' })
}

export async function adminRestoreProduct(storeId: string, productId: string): Promise<Product> {
  const response = await catalogAdmin<{ data: Product }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/restore`,
    { method: 'post' },
  )
  return response.data
}

export async function adminUploadProductImage(
  storeId: string,
  productId: string,
  image: File,
): Promise<ProductImage> {
  const formData = new FormData()
  formData.append('image', image)
  const response = await catalogAdmin<{ data: ProductImage }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/images`,
    { method: 'post', body: formData },
  )
  return response.data
}

export async function adminSetMainProductImage(
  storeId: string,
  productId: string,
  imageId: string,
): Promise<ProductImage> {
  const response = await catalogAdmin<{ data: ProductImage }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/images/${enc(imageId)}/main`,
    { method: 'patch' },
  )
  return response.data
}

export async function adminDeleteProductImage(
  storeId: string,
  productId: string,
  imageId: string,
): Promise<void> {
  await catalogAdmin<null>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/images/${enc(imageId)}`,
    { method: 'delete' },
  )
}

export async function adminGetStock(storeId: string, productId: string, signal?: AbortSignal): Promise<Stock> {
  const response = await catalogAdmin<{ data: Stock }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/stock`,
    { method: 'get', signal },
  )
  return response.data
}

export async function adminUpdateStock(
  storeId: string,
  productId: string,
  quantity: number,
): Promise<Stock> {
  const response = await catalogAdmin<{ data: Stock }>(
    `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/stock`,
    json('patch', { quantity }),
  )
  return response.data
}
