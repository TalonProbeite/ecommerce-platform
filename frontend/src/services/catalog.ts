import { catalogRequest } from '@/api/catalog-client'
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

function query(params: object): Record<string, string> {
  const result: Record<string, string> = {}
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '') continue
    result[key] = String(value)
  }
  return result
}

// ---------------------------------------------------------------------------
// Public routes (no token)
// ---------------------------------------------------------------------------

export async function listStores(params: StoreListParams = {}, signal?: AbortSignal): Promise<PaginatedResponse<Store>> {
  const { data } = await catalogRequest<PaginatedResponse<Store>>({
    url: '/stores',
    method: 'get',
    params: query(params),
    signal,
  })
  return data
}

export async function getStore(storeId: string, signal?: AbortSignal): Promise<Store> {
  const { data } = await catalogRequest<{ data: Store }>({
    url: `/stores/${enc(storeId)}`,
    method: 'get',
    signal,
  })
  return data.data
}

export async function listStoreCategories(storeId: string, signal?: AbortSignal): Promise<Category[]> {
  const { data } = await catalogRequest<{ data: Category[] }>({
    url: `/stores/${enc(storeId)}/categories`,
    method: 'get',
    signal,
  })
  return data.data
}

export async function getStoreCategory(storeId: string, categoryId: string, signal?: AbortSignal): Promise<Category> {
  const { data } = await catalogRequest<{ data: Category }>({
    url: `/stores/${enc(storeId)}/categories/${enc(categoryId)}`,
    method: 'get',
    signal,
  })
  return data.data
}

export async function listStoreCategoryChildren(storeId: string, categoryId: string, signal?: AbortSignal): Promise<Category[]> {
  const { data } = await catalogRequest<{ data: Category[] }>({
    url: `/stores/${enc(storeId)}/categories/${enc(categoryId)}/children`,
    method: 'get',
    signal,
  })
  return data.data
}

export async function listProducts(params: ProductListParams = {}, signal?: AbortSignal): Promise<PaginatedResponse<Product>> {
  const { data } = await catalogRequest<PaginatedResponse<Product>>({
    url: '/products',
    method: 'get',
    params: query(params),
    signal,
  })
  return data
}

export async function getProduct(productId: string, signal?: AbortSignal): Promise<Product> {
  const { data } = await catalogRequest<{ data: Product }>({
    url: `/products/${enc(productId)}`,
    method: 'get',
    signal,
  })
  return data.data
}

export async function listProductImages(productId: string, signal?: AbortSignal): Promise<ProductImage[]> {
  const { data } = await catalogRequest<{ data: ProductImage[] }>({
    url: `/products/${enc(productId)}/images`,
    method: 'get',
    signal,
  })
  return data.data
}

export async function getProductImage(productId: string, imageId: string, signal?: AbortSignal): Promise<ProductImage> {
  const { data } = await catalogRequest<{ data: ProductImage }>({
    url: `/products/${enc(productId)}/images/${enc(imageId)}`,
    method: 'get',
    signal,
  })
  return data.data
}

// ---------------------------------------------------------------------------
// Admin routes. They require an Authorization: Bearer token issued by Auth.
// The current cookie-based frontend session does not expose that token, so
// callers must provide it explicitly.
// ---------------------------------------------------------------------------

const ADMIN = '/admin'

export async function adminCreateStore(token: string, payload: CreateStorePayload): Promise<Store> {
  const { data } = await catalogRequest<{ data: Store }>({
    url: `${ADMIN}/stores`,
    method: 'post',
    data: payload,
  }, token)
  return data.data
}

export async function adminUpdateStore(token: string, storeId: string, payload: UpdateStorePayload): Promise<Store> {
  const { data } = await catalogRequest<{ data: Store }>({
    url: `${ADMIN}/stores/${enc(storeId)}`,
    method: 'patch',
    data: payload,
  }, token)
  return data.data
}

export async function adminDeleteStore(token: string, storeId: string): Promise<void> {
  await catalogRequest({
    url: `${ADMIN}/stores/${enc(storeId)}`,
    method: 'delete',
  }, token)
}

export async function adminCreateCategory(token: string, storeId: string, payload: CreateCategoryPayload): Promise<Category> {
  const { data } = await catalogRequest<{ data: Category }>({
    url: `${ADMIN}/stores/${enc(storeId)}/categories`,
    method: 'post',
    data: payload,
  }, token)
  return data.data
}

export async function adminUpdateCategory(
  token: string,
  storeId: string,
  categoryId: string,
  payload: UpdateCategoryPayload,
): Promise<Category> {
  const { data } = await catalogRequest<{ data: Category }>({
    url: `${ADMIN}/stores/${enc(storeId)}/categories/${enc(categoryId)}`,
    method: 'patch',
    data: payload,
  }, token)
  return data.data
}

export async function adminDeleteCategory(token: string, storeId: string, categoryId: string): Promise<void> {
  await catalogRequest({
    url: `${ADMIN}/stores/${enc(storeId)}/categories/${enc(categoryId)}`,
    method: 'delete',
  }, token)
}

export async function adminListProducts(
  token: string,
  storeId: string,
  params: Omit<ProductListParams, 'store_id'> = {},
  signal?: AbortSignal,
): Promise<PaginatedResponse<Product>> {
  const { data } = await catalogRequest<PaginatedResponse<Product>>({
    url: `${ADMIN}/stores/${enc(storeId)}/products`,
    method: 'get',
    params: query(params),
    signal,
  }, token)
  return data
}

export async function adminGetProduct(token: string, storeId: string, productId: string): Promise<Product> {
  const { data } = await catalogRequest<{ data: Product }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}`,
    method: 'get',
  }, token)
  return data.data
}

export async function adminCreateProduct(token: string, storeId: string, payload: CreateProductPayload): Promise<Product> {
  const { data } = await catalogRequest<{ data: Product }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products`,
    method: 'post',
    data: payload,
  }, token)
  return data.data
}

export async function adminUpdateProduct(
  token: string,
  storeId: string,
  productId: string,
  payload: UpdateProductPayload,
): Promise<Product> {
  const { data } = await catalogRequest<{ data: Product }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}`,
    method: 'patch',
    data: payload,
  }, token)
  return data.data
}

export async function adminDeleteProduct(token: string, storeId: string, productId: string): Promise<void> {
  await catalogRequest({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}`,
    method: 'delete',
  }, token)
}

export async function adminRestoreProduct(token: string, storeId: string, productId: string): Promise<Product> {
  const { data } = await catalogRequest<{ data: Product }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/restore`,
    method: 'post',
  }, token)
  return data.data
}

export async function adminUploadProductImage(
  token: string,
  storeId: string,
  productId: string,
  image: File,
): Promise<ProductImage> {
  const formData = new FormData()
  formData.append('image', image)
  const { data } = await catalogRequest<{ data: ProductImage }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/images`,
    method: 'post',
    formData,
  }, token)
  return data.data
}

export async function adminSetMainProductImage(
  token: string,
  storeId: string,
  productId: string,
  imageId: string,
): Promise<ProductImage> {
  const { data } = await catalogRequest<{ data: ProductImage }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/images/${enc(imageId)}/main`,
    method: 'patch',
  }, token)
  return data.data
}

export async function adminDeleteProductImage(
  token: string,
  storeId: string,
  productId: string,
  imageId: string,
): Promise<void> {
  await catalogRequest({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/images/${enc(imageId)}`,
    method: 'delete',
  }, token)
}

export async function adminGetStock(token: string, storeId: string, productId: string): Promise<Stock> {
  const { data } = await catalogRequest<{ data: Stock }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/stock`,
    method: 'get',
  }, token)
  return data.data
}

export async function adminUpdateStock(
  token: string,
  storeId: string,
  productId: string,
  quantity: number,
): Promise<Stock> {
  const { data } = await catalogRequest<{ data: Stock }>({
    url: `${ADMIN}/stores/${enc(storeId)}/products/${enc(productId)}/stock`,
    method: 'patch',
    data: { quantity },
  }, token)
  return data.data
}
