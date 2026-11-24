UPDATE users
SET store_id = NULL,
    updated_at = NOW()
WHERE email = 'manager@shop.local'
  AND store_id = '4122d059-3fc9-5cef-b48a-763053376266';
