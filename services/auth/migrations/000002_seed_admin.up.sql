INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified)
VALUES (
    '018f1234-5678-7000-8000-000000000001',
    'admin@shop.local',
    '$2a$10$wK1...хэш_пароля...', 
    'admin',
    true,
    true
)
ON CONFLICT (email) DO NOTHING;

INSERT INTO profiles (id, user_id, first_name, last_name, phone)
VALUES (
    '018f1234-5678-7000-8000-000000000002',
    '018f1234-5678-7000-8000-000000000001',
    'System',
    'Admin',
    '+70000000000'
)
ON CONFLICT (user_id) DO NOTHING;