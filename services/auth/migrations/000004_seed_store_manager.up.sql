-- Store manager demo account for environments that already applied 000002.
INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified)
VALUES (
    '018f1234-5678-7000-8000-000000000003',
    'manager@shop.local',
    '$2y$10$kpgVeW5TkwIoLbUVc1a52OQ8TcKZC9wz/Gpyt10Oxma0EstyuMpaa',  -- manager123
    'store_manager',
    true,
    true
)
ON CONFLICT (email) DO NOTHING;

INSERT INTO profiles (id, user_id, first_name, last_name, phone)
VALUES (
    '018f1234-5678-7000-8000-000000000004',
    '018f1234-5678-7000-8000-000000000003',
    'Store',
    'Manager',
    '+70000000001'
)
ON CONFLICT (user_id) DO NOTHING;
