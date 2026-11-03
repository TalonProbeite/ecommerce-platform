-- Explicit DEMO seed. Run after migrations. Shared demo password: admin123.
-- Existing users and profiles are never updated.
BEGIN;
WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000010', 'analyst1@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'analyst', true, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000010', id, 'Anna', 'Kowalska', '+48100000010', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000011', 'analyst2@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'analyst', true, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000011', id, 'Pavel', 'Novak', '+48100000011', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000012', 'customer1@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'customer', true, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000012', id, 'Elena', 'Morozova', '+48100000012', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000013', 'customer2@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'customer', true, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000013', id, 'Mark', 'Petrov', '+48100000013', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000014', 'customer3@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'customer', true, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000014', id, 'Sofia', 'Volkova', '+48100000014', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000015', 'customer4@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'customer', true, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000015', id, 'Ivan', 'Sokolov', '+48100000015', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000016', 'unverified@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'customer', true, false, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000016', id, 'Daria', 'Smirnova', '+48100000016', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

WITH inserted AS (
    INSERT INTO users (id, email, password_hash, role, is_active, is_email_verified, created_at, updated_at)
    VALUES ('018f1234-5678-7000-8000-000000000017', 'blocked@shop.local', '$2y$10$FN6HNfEDhD1hZ.gIbKmzo.fLxw4Mlyuz0yZKxWOIXl05CvcBuiVta', 'customer', false, true, NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day')
    ON CONFLICT DO NOTHING
    RETURNING id
)
INSERT INTO profiles (id, user_id, first_name, last_name, phone, created_at, updated_at)
SELECT '018f1234-5678-7000-8001-000000000017', id, 'Alex', 'Orlov', '+48100000017', NOW() - INTERVAL '14 days', NOW() - INTERVAL '1 day'
FROM inserted;

COMMIT;
