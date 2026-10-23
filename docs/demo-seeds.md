# Auth and notification DEMO seeds

Seeds are explicit commands, separate from migrations and application startup.
They require running databases; auth migrations must already be applied.
The existing admin migration and Catalog seeder are unchanged.

## Global stack

Run from the repository root after starting the global stack:

```sh
make seed-auth
make seed-notification
make seed              # auth + notification + existing Catalog seed
```

Auth and notification use the root `docker-compose.yaml`. Catalog keeps its
existing `make -C services/catalog seed` behavior and configured Laravel database.

## Isolated local stacks

```sh
make -C services/auth seed
make -C services/notification seed
```

These commands use each service's `docker-compose.local.yaml`. To target another
Compose stack, pass `COMPOSE_FILE=/absolute/path/to/compose.yaml`.
No host PostgreSQL/MongoDB client or Go compiler is required.
MongoDB database name comes from `MONGO_DB` inside the database container
(default `shop_notifications`), matching the service Compose configuration.

## Accounts

All eight new accounts use password `admin123` (bcrypt, cost 10).

| Email | Role | Active | Email verified |
| --- | --- | --- | --- |
| analyst1@shop.local | analyst | yes | yes |
| analyst2@shop.local | analyst | yes | yes |
| customer1@shop.local | customer | yes | yes |
| customer2@shop.local | customer | yes | yes |
| customer3@shop.local | customer | yes | yes |
| customer4@shop.local | customer | yes | yes |
| unverified@shop.local | customer | yes | no |
| blocked@shop.local | customer | no | yes |

Each account has a profile. The blocked account cannot log in. The unverified
account can log in and request email verification through the normal flow.
Notification history endpoints require an admin or analyst; customers cannot
read this audit history. Existing `admin@shop.local` is left untouched.

Auth inserts users and their profiles in one transaction. Existing users are
skipped by ID/email, and their password, role, status and profile are preserved.
Redis sessions, verification codes and OAuth identities are created by normal
application flows rather than seeded.

## Notification history

The seed inserts 22 email audit records for the same eight email addresses:
16 successful sends and six failed first attempts. Six of the successful sends
are second attempts after those failures. Each account has welcome and email
verification history. A delivered verification email does not imply that the
user completed verification, so `unverified@shop.local` remains unverified.

Payloads follow the actual event contracts. Verification codes are empty, as in
the notification handler. Failed records include an SMTP error; successful
records omit it. Dates are BSON dates, initially about 12–13 days before seeding,
following account creation 14 days earlier. Object IDs are stable. `$setOnInsert`
keeps existing records and their original dates unchanged on repeated runs.

These are simulated audit entries. Seeding does not send mail or publish events.
No fictitious orders or unsupported notification types are created.
