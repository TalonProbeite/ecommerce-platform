# Сверка Order Service с ТЗ (P12)

Источник требований: `notes/Order Service.md`. Источник истины для реализации — код в `services/order`. Проверка выполнена только чтением файлов; docker, composer, artisan, phpunit, pint, phpstan и deptrac не запускались. Код приложения не изменялся.

Статусы: `выполнено` — реализация и доказательство найдены; `частично` — реализация есть, но требование закрыто не полностью или доказательство неполное; `не выполнено` — реализации или доказательства нет; `изменено решением` — код расходится с ТЗ по зафиксированному решению из `docs/order/decisions.md`.

## Требования ТЗ

| Требование ТЗ | Статус | Доказательство (файл:строка или тест) | Отклонение (ссылка на decisions) |
|---|---|---|---|
| 1. Backend на PHP и Laravel, консистентно с Catalog | выполнено | `services/order/composer.json:11` (`laravel/framework ^13.17`); `services/catalog/composer.json:14` — та же версия Laravel | — |
| 1. Тестирование PHPUnit | выполнено | `services/order/composer.json:29` (`phpunit/phpunit ^12.5.12`); `tests/Unit/`, `tests/Feature/` | — |
| 2. PostgreSQL: заказы, позиции, платежи, снапшот и outbox в одной БД | выполнено | Миграции `database/migrations/2026_12_01_100001_create_orders_table.php:11`, `..._100002_create_order_items_table.php:11`, `..._100003_create_payments_table.php:11`, `..._100007_create_product_snapshots_table.php:11`, `..._100008_create_outbox_table.php:11`; единое подключение `config/database.php` | — |
| 3. Redis: idempotency key и кэш саги | изменено решением | Идемпотентность: `app/Infrastructure/Idempotency/RedisIdempotencyLock.php:26`, ключ `order:idem:{customer_id}:{key}` в `app/Application/Customer/CreateOrder/CreateOrderUseCase.php:169`; статус саги хранится в PostgreSQL, а не в Redis | `docs/order/decisions.md:347` (Redis не хранит статусы, деньги и состояние саги). Интеграционного теста Redis нет |
| 4. RabbitMQ, хореография саги без оркестратора | выполнено | `app/Infrastructure/Messaging/Topology.php:15-60`; consumer `app/Infrastructure/Messaging/Inbound/InboundMessageRouter.php`; шаги саги `app/Application/Saga/OnStockReserved.php:30-61`, `app/Application/Saga/OnStockReservationFailed.php:30`; отдельного класса-оркестратора нет | — |
| 5. Outbox в той же транзакции, что и изменение состояния, плюс relay | выполнено | `app/Application/Customer/CreateOrder/CreateOrderUseCase.php:52-56` (транзакция); `app/Infrastructure/Persistence/EloquentOrderRepository.php:127-132` (журнал и outbox при сохранении); `app/Infrastructure/Messaging/Outbox/OutboxRelay.php:43-51` (публикация); `app/Console/Commands/MessagingRelayCommand.php` | — |
| 6. Stripe test mode, официальный SDK, webhook | частично | `services/order/composer.json:18` (`stripe/stripe-php ^23.0`); `app/Infrastructure/Payments/Stripe/StripePaymentGateway.php`; `app/Infrastructure/Payments/Stripe/StripeWebhookSignatureVerifier.php:26` (`Webhook::constructEvent`); webhook `app/Http/Controllers/Webhooks/StripeWebhookController.php:28-66`. Тесты используют `FakePaymentGateway`, реальный Stripe test mode не запускался | Живой Stripe test mode не доказан; см. «Не доказано в репозитории» |
| 7. Frontend React/Next: чекаут и история заказов | не выполнено | В `services/order` фронтенда нет; реализация отложена на P13 | `docs/order/decisions.md:395` (P13) |
| 8. Nginx: сервис только на 443 | выполнено | `services/order/docker/nginx/conf.d/default.conf:8-16` — единственный рабочий `server` слушает 443 | — |
| 8. Nginx: редирект с 80 на 443 | выполнено | `services/order/docker/nginx/conf.d/default.conf:1-6` (`return 301 https://...`) | — |
| 8. Nginx: SSL, self-signed сертификат | частично | `services/order/docker/nginx/conf.d/default.conf:14-15`; файлы `services/order/docker/nginx/ssl/cert.pem`, `key.pem` существуют. Самоподписанность криптографически не проверялась: `openssl` не запускался по ограничениям | — |
| 8. Nginx: отдельный маршрут `/webhooks/stripe` без JWT | выполнено | `services/order/docker/nginx/conf.d/default.conf:28-35`; CSRF исключён в `bootstrap/app.php:29`; JWT middleware на webhook не навешан в `routes/web.php:10` | — |
| 9. Docker Compose: front, back, sql, redis, rabbitmq, nginx, stripe-cli | частично | `services/order/docker-compose.local.yaml:36-233`: back (`order-app`), sql (`order-db`), redis, rabbitmq, nginx, stripe-cli; front отсутствует. `services/order/docker-compose.prod.yaml:49-175`: back, sql, redis, nginx; front, rabbitmq и stripe-cli отсутствуют. Корневой `docker-compose.yaml:3-8` включает frontend и общий rabbitmq, но не stripe-cli | `docs/order/decisions.md:362` требует `order-front` и `stripe-cli`; `docs/order/decisions.md:393` (P11). Реализация расходится с решением — gap в compose |
| 10. JWT валидируется тем же способом, что в остальных сервисах | частично | Order: `app/Http/Middleware/Authenticate.php:118` (`RS256` захардкожен), `:126-132` проверяется только `exp`. Catalog: `services/catalog/app/Http/Middleware/EnsureAdminOrStoreManager.php:50-52` читает `jwt.algo`, `:73-89` проверяет `iss`/`aud` при наличии конфигурации | Отдельным решением не оформлено: Order не читает `jwt.algo`, не проверяет `iss`/`aud`. Записано как gap, код не менялся |
| 10. ABAC: Customer видит и создаёт только свои; Admin и Analyst видят все | выполнено | `app/Policies/OrderPolicy.php:14-39`; `app/Http/Controllers/Api/V1/OrderController.php:113-182`; тесты `tests/Feature/Http/ListOrdersHttpTest.php`, `tests/Feature/Http/GetOrderHttpTest.php`, `tests/Unit/Http/OrderPolicyTest.php` | — |

## Локальный снапшот товаров

| Требование ТЗ | Статус | Доказательство (файл:строка или тест) | Отклонение (ссылка на decisions) |
|---|---|---|---|
| Order консьюмит `product.created` / `product.updated` и хранит цену, название, `store_id` | выполнено | `app/Infrastructure/Messaging/Inbound/Handlers/ProductCreatedHandler.php:45-55`, `ProductUpdatedHandler.php:45-55`; `app/Application/Catalog/SyncProductSnapshot/SyncProductSnapshotUseCase.php:28-52`; таблица `database/migrations/2026_12_01_100007_create_product_snapshots_table.php:11-24` | — |
| При оформлении заказа нет синхронного вызова к Catalog, используется локальный снапшот | выполнено | `app/Application/Customer/CreateOrder/CreateOrderUseCase.php:145-160` (`findActiveByIds`); HTTP/Guzzle-клиента к Catalog в `app/` не найдено | — |
| Резерв остатка идёт через сагу, а не через снапшот | выполнено | `app/Domain/Catalog/ProductSnapshot.php:12-21` — в снапшоте нет остатка; `app/Application/Saga/OnStockReserved.php:30-61` обрабатывает `stock.reserved`; `app/Application/Saga/OnStockReservationFailed.php:30` | — |

## Saga

| Требование ТЗ | Статус | Доказательство (файл:строка или тест) | Отклонение (ссылка на decisions) |
|---|---|---|---|
| Хореография через RabbitMQ без центрального оркестратора | выполнено | `app/Infrastructure/Messaging/Topology.php:54-60`; `app/Infrastructure/Messaging/Inbound/InboundHandlerRegistry.php`; шаги `app/Application/Saga/` и `app/Application/Payments/Webhooks/`; оркестратора нет | — |
| Статусы: `created → stock_reserved → payment_pending → paid → confirmed → shipped → completed`, либо `cancelled` / `payment_failed` | изменено решением | `app/Domain/Order/OrderStatus.php:9-15`: `created`, `stock_reserved`, `payment_pending`, `paid`, `in_rental`, `completed`, `cancelled`. `confirmed` отсутствует, `shipped` заменён на `in_rental`, добавлен `cancelled`; отдельного `payment_failed` нет | `docs/order/decisions.md:148` (статусы заказа), `docs/order/decisions.md:421` (P9-3) |
| Создание заказа: `created`, `order.created` в outbox в той же транзакции, Idempotency-Key | выполнено | `app/Domain/Order/Order.php:120-143`; `app/Application/Customer/CreateOrder/CreateOrderUseCase.php:52-56`; `app/Infrastructure/Persistence/EloquentOrderRepository.php:127-132` | — |
| Relay публикует `order.created` | выполнено | `app/Infrastructure/Messaging/Outbox/OutboxRelay.php:43-51`; `app/Console/Commands/MessagingRelayCommand.php` | — |
| Catalog публикует `stock.reserved` / `stock.reservation_failed`; Order их консьюмит | выполнено | Order-сторона: `app/Infrastructure/Messaging/Inbound/Handlers/StockReservedHandler.php:40-82`, `StockReservationFailedHandler.php:41-84`; `app/Infrastructure/Messaging/Topology.php:54-60`. Публикация Catalog — вне `services/order` и здесь не верифицировалась | — |
| `stock.reserved` → `stock_reserved`, затем оплата; при успехе `paid` и `order.paid` + `order.confirmed` | изменено решением | `app/Application/Saga/OnStockReserved.php:61`; `app/Application/Customer/RequestPaymentIntent/RequestPaymentIntent.php`; `app/Application/Payments/Webhooks/OnPaymentSucceeded.php:86-89`; `app/Domain/Events/OrderPaid.php:33-51`; `order.confirmed` не публикуется | `docs/order/decisions.md:313` фиксирует только пять исходящих ключей. Отдельного решения об отмене `order.confirmed` нет — TZ-отклонение |
| При ошибке оплаты: статус `payment_failed` и `stock.release_requested` | изменено решением | `app/Application/Payments/Webhooks/OnPaymentFailed.php` оставляет заказ в `payment_pending`; `app/Domain/Order/OrderStatus.php` не содержит `payment_failed`; ключа `stock.release_requested` нет в `app/Domain/Messaging/RoutingKeys.php:9-19` | `docs/order/decisions.md:227` (OnPaymentFailed статус не меняет), `docs/order/decisions.md:313` (список публикуемых событий) |
| `stock.reservation_failed` → `cancelled` и `order.cancelled` | выполнено | `app/Application/Saga/OnStockReservationFailed.php:30`; `app/Domain/Order/OrderStatus.php:15`; `app/Domain/Events/OrderCancelled.php:34-37`; `app/Domain/Messaging/RoutingKeys.php:10` | — |
| Подтверждение оплаты приходит асинхронно через `/webhooks/stripe` | выполнено | `routes/web.php:10-11`; `app/Http/Controllers/Webhooks/StripeWebhookController.php:28-66`; `app/Application/Payments/Webhooks/HandleStripeEvent.php` | — |

## События (producer/consumer)

| Требование ТЗ | Статус | Доказательство (файл:строка или тест) | Отклонение (ссылка на decisions) |
|---|---|---|---|
| Publishes: `order.created`, `order.paid`, `order.confirmed`, `order.cancelled`, `order.payment_failed`, `stock.release_requested` | изменено решением | `app/Domain/Messaging/RoutingKeys.php:49-58` содержит только `order.created`, `order.cancelled`, `order.issued`, `order.returned`, `order.paid`; `order.confirmed`, `order.payment_failed`, `stock.release_requested` отсутствуют | `docs/order/decisions.md:313`; отдельным ID отмена этих событий не оформлена — gap с ТЗ |
| Consumes: `product.created`, `product.updated`, `stock.reserved`, `stock.reservation_failed` | выполнено | `app/Infrastructure/Messaging/Inbound/InboundRoutingKeys.php:11-15`; `app/Infrastructure/Messaging/Topology.php:54-60`; обработчики в `app/Infrastructure/Messaging/Inbound/Handlers/`. Дополнительно потребляется `product.deleted` | — |
| Notification консьюмит `order.paid`, `order.confirmed`, `order.cancelled` | частично | `order.paid` и `order.cancelled` публикуются (`app/Domain/Messaging/RoutingKeys.php:10,13`); `order.confirmed` не публикуется. Notification — внешний контракт: `docs/contracts/messaging.md:126-136` | `docs/order/decisions.md:313`; `order.confirmed` — TZ-отклонение, зафиксировано в `docs/contracts/messaging.md:131-134` |

## REST API

| Требование ТЗ | Статус | Доказательство (файл:строка или тест) | Отклонение (ссылка на decisions) |
|---|---|---|---|
| `POST /orders` — Customer, обязателен `Idempotency-Key` | выполнено | `routes/api.php:17-19`; `app/Http/Controllers/Api/V1/OrderController.php:41-111`; `tests/Feature/Http/CreateOrderHttpTest.php` (400 без ключа, 200/409/422) | — |
| `GET /orders` — Customer только свои, Admin/Analyst все | выполнено | `routes/api.php:21-22`; `app/Http/Controllers/Api/V1/OrderController.php:113-166`; `tests/Feature/Http/ListOrdersHttpTest.php` | — |
| `GET /orders/{id}` — детали заказа | выполнено | `routes/api.php:24-25`; `app/Http/Controllers/Api/V1/OrderController.php:168-182`; `tests/Feature/Http/GetOrderHttpTest.php` | — |
| `POST /orders/{id}/cancel` — отмена до `shipped` | изменено решением | `routes/api.php:31-33`; `app/Domain/Order/Order.php:376-383` — отмена разрешена из `created`, `stock_reserved`, `payment_pending`, `paid`, то есть до первой выдачи | `docs/order/decisions.md:421` (P9-3): отмена после первой выдачи запрещена, граница — не `shipped`, а первая выдача |
| `POST /webhooks/stripe` — подпись, без JWT | выполнено | `routes/web.php:10-11`; `app/Http/Controllers/Webhooks/StripeWebhookController.php:30-66`; `app/Infrastructure/Payments/Stripe/StripeWebhookSignatureVerifier.php:26` | — |

## Тестирование

| Требование ТЗ | Статус | Доказательство (файл:строка или тест) | Отклонение (ссылка на decisions) |
|---|---|---|---|
| PHPUnit: юнит-тесты шагов саги, компенсации, идемпотентности | выполнено | `tests/Unit/Application/Saga/OnStockReservedTest.php`, `OnStockReservationFailedTest.php`, `ExpireUnpaidOrdersTest.php`, `tests/Unit/Application/Customer/CreateOrderUseCaseTest.php`, `CancelOrderTest.php`, `tests/Unit/Application/Payments/*` | — |
| Интеграционные тесты PostgreSQL: outbox и транзакции | выполнено | `tests/Feature/Messaging/Outbox/OutboxRelayTest.php`, `tests/Feature/Messaging/Outbox/MessagingRelayCommandTest.php`, `tests/Feature/Persistence/*`, `tests/Feature/Payments/*PostgresTest.php` | — |
| Интеграционные тесты RabbitMQ: публикация и консюминг событий саги | выполнено | `tests/Feature/Messaging/Inbound/CatalogEventsConsumerRabbitMqTest.php:20-21` (gated by `RUN_RABBITMQ_TESTS`), `tests/Feature/Messaging/Inbound/OnStockReservedRabbitMqTest.php`, `tests/Feature/Messaging/Outbox/OutboxRelayTest.php`. В P12 не запускались | — |
| Интеграционные тесты Stripe test mode: создание платежа и webhook | частично | `tests/Feature/Payments/PaymentIntentPostgresTest.php` и `tests/Feature/Payments/StripeWebhookPostgresTest.php` используют фейковый gateway; подпись проверяется в `tests/Unit/Application/Payments/StripeWebhookSignatureVerifierTest.php`. Реальный Stripe test mode не вызывался | Живой Stripe test mode не доказан |
| Интеграционные тесты Redis: idempotency key | не выполнено | HTTP-тесты используют `FakeIdempotencyLock` (`tests/Feature/Http/ApiTestCase.php:84`); теста для `app/Infrastructure/Idempotency/RedisIdempotencyLock.php` нет | Покрытие Redis отсутствует; реализация есть, интеграционного теста нет |

## Счётчики

| Статус | Количество |
|---|---|
| выполнено | 26 |
| частично | 6 |
| не выполнено | 2 |
| изменено решением | 6 |
| **Всего** | **40** |

## Отклонения от ТЗ и связанные решения

1. **Кэш саги в Redis не используется.** Статус хранится в PostgreSQL. Решение: `docs/order/decisions.md:347` (раздел 12). Идемпотентность в Redis реализована.
2. **Статусы заказа отличаются от ТЗ.** В коде нет `confirmed`; `shipped` заменён на `in_rental`; добавлен `cancelled`; отдельного `payment_failed` нет. Решения: `docs/order/decisions.md:148` (раздел 4), `docs/order/decisions.md:421` (P9-3).
3. **Отмена разрешена до первой выдачи, а не до `shipped`.** Решения: `docs/order/decisions.md:421` (P9-3), `docs/order/decisions.md:422` (P9-4).
4. **Нет событий `order.confirmed`, `order.payment_failed`, `stock.release_requested`.** Публикуются только `order.created`, `order.cancelled`, `order.issued`, `order.returned`, `order.paid`. Решение о сокращении списка отдельным ID не оформлено; фактический список зафиксирован в `docs/order/decisions.md:313` (раздел 10). Это TZ-отклонение и gap.
5. **Ошибка оплаты не переводит заказ в `payment_failed` и не публикует компенсацию.** Заказ остаётся в `payment_pending`. Решение: `docs/order/decisions.md:227` (раздел 6, OnPaymentFailed).
6. **Frontend не реализован.** Решение: `docs/order/decisions.md:395` (P13).
7. **Docker Compose расходится с решением.** В `services/order/docker-compose.local.yaml` нет front-сервиса; в `docker-compose.prod.yaml` нет front, stripe-cli и RabbitMQ (RabbitMQ вынесен в корневой compose). Решение: `docs/order/decisions.md:362` (раздел 14), `docs/order/decisions.md:393` (P11). Это gap между кодом и решением.
8. **JWT в Order валидируется иначе, чем в Catalog.** Order жёстко использует RS256 и не проверяет `iss`/`aud`; Catalog читает `jwt.algo` и при наличии конфигурации проверяет `iss`/`aud`. Решением не оформлено; записано как gap.

## Не доказано в репозитории

- **Prod-запуск корневого `docker-compose.yaml` не выполнялся.** По ограничениям docker/compose не запускались. Наличие файла и include-секций не доказывает успешный запуск цепочки.
- **Frontend не реализован и не проверен.** В `services/order` front-сервиса нет; P13 не выполнена.
- **Notification-контракт не верифицирован.** `order.processing.q`, `dlx.exchange` и `failed.messages` находятся вне `services/order`; в `docs/contracts/messaging.md:126-128` они явно отмечены как внешний контракт. Реальное потребление `order.paid`/`order.cancelled` сервисом Notification не проверялось.
- **Stripe Dashboard и реальная подпись в проде не проверены.** Нет доказательств настройки webhook endpoint, секрета `whsec_...` и доставки событий из Stripe. Тесты используют фейковый gateway; живой Stripe test mode не запускался.
- **Отсутствие front-сервиса в Order Compose.** В `services/order/docker-compose.local.yaml` и `docker-compose.prod.yaml` front отсутствует. Корневой compose подключает `frontend/docker-compose.prod.yaml`, но это не проверено запуском.
- **Self-signed сертификат не проверен криптографически.** `openssl` не запускался; проверено только наличие файлов и пути в nginx-конфиге.
- **RabbitMQ-тесты не запускались.** Они существуют, но gated через `RUN_RABBITMQ_TESTS`; фактический прогон в P12 не выполнялся.
- **Redis-интеграция не проверена.** Интеграционного теста для `RedisIdempotencyLock` нет; HTTP-тесты используют fake.
- **Laravel Boost не установлен.** `services/order/AGENTS.md` остаётся bootstrap-инструкцией; `php artisan boost:install` не выполнялся. См. `docs/order/decisions.md:440`.
