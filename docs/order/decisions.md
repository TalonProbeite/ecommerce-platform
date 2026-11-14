# План сервиса Order (итоговый)

Документ собирает все решения из обсуждения. Он задаёт структуру и порядок. Каждая фаза из раздела 16 станет отдельным промптом агенту с теми же правилами, что и для Catalog: читать код перед правкой, тесты до и после, pint и PHPStan level 8, отдельные коммиты.

## 0. Решения

| № | Тема | Решение |
|---|---|---|
| O1 | Смешанные корзины | Нет. Заказ содержит позиции одного магазина, иначе 422 |
| O2 | Залог | Входит в PaymentIntent одной суммой вместе с арендой |
| O3 | Штрафы и залог | Штрафов нет. Залог возвращается полностью при возврате позиции. Просрочка фиксируется (`rental_end_at` прошёл, позиция не возвращена) и видна менеджеру и в аналитике. Повреждения вне MVP |
| O4 | Автоотмена неоплаченного | 30 минут с момента резерва |
| O5 | Отмена после оплаты до выдачи | Полный автоматический возврат |
| O6 | Роли и магазин | `store_id` всегда в JWT менеджера |
| O7 | Коды и ошибки | 201 при создании, 200 при повторе, 409 при параллельном повторе, 422 при другом теле, problem+json, 404 для чужих заказов |
| O8 | Часы аренды | Округление вверх, минимум один час |
| O9 | Брокер | Один RabbitMQ на весь проект |
| O10 | Notification | Есть, в другой части проекта. Объявляет `order.events` |
| T1 | `order.events` | Объявляют все участники идентично. Параметры фиксируются в `docs/contracts/messaging.md`, тест в каждом сервисе сверяет их с таблицей |
| T2 | Повторы | Механизм повторов уточняется по документации RabbitMQ 4.3+ (quorum). До уточнения: requeue и лимит попыток, как в Catalog |
| T3 | Валюта | EUR, одна на платформу |
| T4 | Таймеры и precision | Все времена `timestamptz(6)`, микросекунды сохраняются |

## 1. Архитектура

- **Domain.** Чистый PHP: агрегат `Order`, `OrderItem`, VO `Money`, `RentalPeriod`, enum статусов. Без Eloquent, фасадов и `now()`. Все переходы и инварианты живут здесь.
- **Application.** Use cases, один класс на сценарий, `execute(Command): Result`. Зависит только от доменных типов и портов.
- **Ports.** Интерфейсы в `Application/Ports`: `OrderRepository`, `ProductSnapshotRepository`, `PaymentGateway`, `RefundGateway` (можно одним `PaymentGateway`), `IdempotencyStore`, `OutboxWriter`, `ProcessedEventStore`, `TransactionRunner`, `Clock`.
- **Infrastructure.** Реализации портов, Eloquent-модели и мапперы. Модели не покидают слой.
- **Presentation.** Контроллеры, consumer-обработчики и команды тонкие: валидация в FormRequest, вывод через Resource, логика в use case.
- **DI.** Все привязки в провайдерах. `app()` вне провайдеров не используем.
- **Транзакция = один use case.** Загрузка агрегата, изменение, сохранение с проверкой `version`, запись в `order_events`, запись в outbox. Через `TransactionRunner`.
- **Время и деньги.** Время только через `Clock`. Суммы в центах, валюта EUR.
- **Слои проверяются deptrac** в CI: Domain не импортирует Application, Infrastructure и Presentation.

Цена подхода: два набора классов (доменный `Order` и Eloquent-модель) и больше кода. Зато переходы тестируются без БД и без Laravel.

## 2. Что переносим из Catalog

| Действие | Что | Заметки |
|---|---|---|
| Копируем | `OutboxWriter`, `OutboxRepository`, `OutboxRelay`, `AmqpOutboxPublisher`, `OutboxRow`, миграции `outbox` и `outbox_failed` | Форму не меняем, relay общий по смыслу |
| Копируем | `InboundMessageRouter`, `InboundHandlerRegistry`, `InboundHandler`, `InboundOutcome`, базовые исключения, `PayloadValidator` | Лимиты в `InboundRules` подгоняем под контракт Order |
| Копируем | `DbTransactionRunner`, `TransactionRunnerInterface`, `EnsureRole`, `ReadyzController`, Dockerfile, pint.json, phpstan.neon | В readiness добавляем Redis |
| Копируем | `PostgresTestCase`, флаг `RUN_RABBITMQ_TESTS`, фейки (`FakeOutboxWriter`, `FakeTransactionRunner`, in-memory репозитории) | Тот же приём |
| Адаптируем | `Topology` как шаблон | Exchange `order.events` объявляем идентично, очередь `order.catalog-events` |
| Адаптируем | `EnsureStoreAccess` | Проверка магазина переезжает в `OrderPolicy` |
| Адаптируем | `OutboundContractTest` | Сверка с `messaging.md` |
| Не переносим | Products, Categories, Stores, ProductImage, S3/MinIO, Search, ProductCache, CategoryCache, Stock, StockService, StockReservation, CatalogSeeder | Относится к Catalog |

JWT-мидлварь в выгрузке Catalog не видна. Найти её и перенести без изменений.

## 3. Структура каталогов

```
services/order/
  app/
    Domain/
      Order/        Order.php, OrderItem.php, OrderStatus.php, ItemStatus.php, CancelReason.php
      Money/        Money.php
      Rental/       RentalPeriod.php, BilledDuration.php
      Pricing/      PriceCalculator.php
      Exceptions/   InvalidOrderTransition.php, InvalidItemTransition.php, MixedStoreOrder.php
    Application/
      Ports/
      Customer/     CreateOrder/, RequestPaymentIntent/, CancelOrder/
      Staff/        IssueItems/, ReturnItems/
      Saga/         OnStockReserved/, OnStockReservationFailed/, OnPaymentSucceeded/,
                    OnPaymentFailed/, ExpireUnpaidOrders/
      Catalog/      SyncProductSnapshot/
      Query/        ListOrders/, GetOrder/, ListOverdueRentals/
      DTO/          Command и Result (readonly)
    Infrastructure/
      Persistence/  Models/, Repositories/, Mappers/, Transactions/
      Payments/Stripe/   StripePaymentGateway.php, StripeWebhookVerifier.php
      Idempotency/       RedisIdempotencyStore.php
      Messaging/         Outbox, Inbound, Topology (скопированы и адаптированы)
      Time/              SystemClock.php
    Http/
      Controllers/Api/V1/    OrderController, OrderActionController, AnalyticsController
      Controllers/Webhooks/  StripeWebhookController
      Requests/  Resources/  Middleware/  Policies/OrderPolicy.php
    Console/Commands/
    Providers/
  config/   orders.php, stripe.php, inbound.php, outbox.php, rabbitmq.php, idempotency.php
  database/migrations/
  tests/
    Unit/Domain/  Unit/Application/
    Feature/Http/  Feature/Messaging/  Feature/Payments/  Feature/Contract/
    Support/Fakes/
```

## 4. Домен

Агрегат `Order` владеет `OrderItem`. Изменения только через методы агрегата.

**Статусы заказа:** `created`, `stock_reserved`, `payment_pending`, `paid`, `in_rental`, `completed`, `cancelled`.

**Статусы позиции:** `reserved`, `issued`, `returned`, `cancelled`.

**Переходы заказа:**

| Из | В | Триггер |
|---|---|---|
| created | stock_reserved | `stock.reserved` |
| created | cancelled | `stock.reservation_failed`, причина `stock_unavailable` |
| stock_reserved | payment_pending | клиент запросил PaymentIntent |
| payment_pending | payment_pending | `payment_intent.payment_failed`, новая попытка разрешена |
| payment_pending | paid | `payment_intent.succeeded` |
| stock_reserved, payment_pending | cancelled | клиент, администратор или таймаут |
| paid | cancelled | отмена до первой выдачи, полный возврат |
| paid | in_rental | первая выдача |
| in_rental | in_rental | частичная выдача или приёмка |
| in_rental | completed | все позиции возвращены |

`cancelled` и `completed` терминальны. Переход `in_rental → cancelled` запрещён.

**Инвариант:** PaymentIntent создаётся только в `stock_reserved` и `payment_pending`, поэтому `payment_intent.succeeded` не может прийти раньше `stock.reserved`.

**Правила позиций:**
- `cancelled` ставится каскадом при отмене заказа до выдачи.
- `reserved → issued` выполняет менеджер, только для оплаченного заказа.
- `issued → returned` выполняет менеджер. Залог возвращается полностью по этой позиции.
- Повторная выдача или приёмка уже обработанной позиции безвредна: позиция пропускается и помечается в ответе.

**Расчёт при создании (фиксируется в `order_items`):**
- `billed_hours = max(1, ceil((rental_end_at − rental_start_at) / 1 час))`
- `line_total = price_per_hour × billed_hours × quantity`
- `deposit_total = deposit_per_unit × quantity`
- `subtotal_amount` = сумма `line_total`, `deposit_amount` = сумма `deposit_total`, итог к оплате = сумма обоих

Цены в заказе фиксируются. Последующее изменение цены в Catalog на заказ не влияет.

**Возвраты:**
- Отмена до выдачи: полный возврат оплаченной суммы.
- Приёмка позиции: возврат залога по позиции (`deposit_total` этой позиции). Аренда не возвращается, она уже отработана.
- Опоздание не влияет на деньги.

## 5. Схема БД

Порядок миграций. Все `timestampTz(6)`. Id `uuid`, генерирует приложение. Исключения: `order_events` (bigint identity) и outbox-таблицы (bigint id). Миграции пишет человек на Eloquent, здесь только состав и ключевые ограничения.

1. **orders**: `id`, `customer_id` (not null, индекс), `store_id` (not null, индекс), `status` (check по списку статусов), `cancel_reason` (null, check: `stock_unavailable`, `payment_timeout`, `customer_request`, `admin_request`), `currency` (check `= 'EUR'`), `subtotal_amount` и `deposit_amount` (check >= 0), `idempotency_key` и `request_fingerprint` (not null), `version` (default 1), `cancelled_at`, `completed_at`, timestamps. Unique `(customer_id, idempotency_key)`. Индексы `(customer_id, created_at DESC)`, `(store_id, status)`.

2. **order_items**: `id` (это `line_id` из контракта), `order_id` (FK, restrict), `product_id` (индекс, без FK), `product_name`, `quantity` (check 1..1000), `rental_start_at`, `rental_end_at` (check `end > start`), `billed_hours` (check > 0), `price_per_hour`, `deposit_per_unit`, `line_total`, `deposit_total` (check >= 0), `item_status` (check по списку), `issued_at`, `issued_by` (id менеджера, без FK), `returned_at`, `returned_by`. Check: `returned_at IS NULL OR issued_at IS NOT NULL`. Не уникальность по `(order_id, product_id)`: один товар может встречаться несколько раз с разными датами. Лимит 100 позиций проверяет приложение.

3. **payments**: `id`, `order_id` (FK), `provider` (`stripe`), `provider_payment_id` (unique, `pi_...`), `amount` (> 0), `currency` (check EUR), `status`, `failure_code`, `succeeded_at`. Несколько попыток оплаты допустимы.

4. **refunds**: `id`, `order_id` (FK), `order_item_id` (null, FK), `payment_id` (FK), `provider_refund_id` (unique, null до ответа Stripe), `amount` (> 0), `reason` (`order_cancelled`, `item_returned`), `status`, timestamps. Ключ идемпотентности для Stripe: `order:{id}:refund:{item_or_all}`.

5. **order_events**: append-only журнал. `id` bigint identity, `order_id` (FK), `order_item_id` (null, FK), `from_status`, `to_status`, `actor_id` (null), `actor_role` (check: `customer`, `manager`, `admin`, `analyst`, `system`, `stripe`), `payload` jsonb, `occurred_at`. Индекс `(order_id, occurred_at)`.

6. **order_comments** (опционально): `id`, `order_id` (FK, cascade), `author_id`, `author_role`, `body` (check длины 1..2000), `created_at`.

7. **product_snapshots**: `product_id` (PK), `store_id` (индекс), `category_id`, `name`, `price_per_hour`, `deposit_amount`, `is_active`, `source_occurred_at`, `deleted_at` (null). Обновление только если `incoming.occurred_at > source_occurred_at`.

8. **outbox**: форма из Catalog (`id` bigint, `event_id` uuid unique, `routing_key`, `aggregate_key` null, `payload` jsonb, `occurred_at`, `published_at` null, `attempts`, `last_error`). Частичный индекс на `id WHERE published_at IS NULL`.

9. **outbox_failed**: та же форма, плюс `failed_at`.

10. **processed_events**: `event_id` (PK), `routing_key`, `processed_at`.

11. **stripe_events**: `id` varchar (PK, `evt_...`), `type`, `processed_at`.

Перед миграцией outbox сверить тип `occurred_at` в Catalog: должно быть `timestamp(6)`. Если в Catalog `(0)`, это отдельная миграция там, до старта Order.

## 6. Use cases

| Use case | Вызывает | Что делает | Событие в outbox |
|---|---|---|---|
| CreateOrder | HTTP, customer | Проверяет магазин (один на все позиции), активность и цены по снапшотам, считает суммы, создаёт заказ в `created` | `order.created` |
| OnStockReserved | consumer | `created → stock_reserved`. Для заказа в другом статусе ничего не делает | нет |
| OnStockReservationFailed | consumer | `created → cancelled`, причина `stock_unavailable` | нет |
| RequestPaymentIntent | HTTP, customer | Создаёт или переиспользует PaymentIntent, переводит в `payment_pending`, возвращает `client_secret` | нет |
| OnPaymentSucceeded | webhook | `payment_pending → paid`. Если заказ уже отменён, полный возврат, событие не публикуется | `order.paid` |
| OnPaymentFailed | webhook | Фиксирует попытку в `payments`, статус заказа не меняет | нет |
| CancelOrder | HTTP (customer, manager, admin) или таймер | Проверяет переход, отменяет открытый PaymentIntent, возвращает оплату если она прошла | `order.cancelled` |
| IssueItems | HTTP, manager, admin | Переводит выбранные позиции в `issued`, заказ в `in_rental` | `order.issued` (только выбранные позиции) |
| ReturnItems | HTTP, manager, admin | Переводит в `returned`, возвращает залог по позициям. Все вернули, заказ в `completed` | `order.returned` (только выбранные позиции) |
| ExpireUnpaidOrders | scheduler | Заказы в `stock_reserved` или `payment_pending` старше 30 минут вызывают CancelOrder с причиной `payment_timeout` | `order.cancelled` на каждый заказ |
| SyncProductSnapshot | consumer | Обновляет снапшот, если `occurred_at` новее. Для `product.deleted` ставит `deleted_at` | нет |
| ListOrders, GetOrder, ListOverdueRentals | HTTP | Только чтение | нет |

`order.cancelled` в контракте означает отмену заказа целиком до выдачи. Catalog освобождает резерв по нему.

## 7. Сценарии

**Основной путь:**
1. Клиент: `POST /api/v1/orders` с `Idempotency-Key`. CreateOrder сохраняет заказ, позиции, журнал и `order.created` в одной транзакции. 201.
2. Relay публикует `order.created`.
3. Catalog резервирует сток, публикует `stock.reserved`.
4. Consumer: OnStockReserved переводит заказ в `stock_reserved`.
5. Фронт: `POST /orders/{id}/payment-intent`. Order создаёт PaymentIntent на сумму аренды и залога, переводит в `payment_pending`, отдаёт `client_secret`.
6. Клиент платит через Stripe Elements.
7. Stripe присылает `payment_intent.succeeded`. Подпись проверяется, событие дедуплицируется, заказ переходит в `paid`, `order.paid` в outbox.
8. Менеджер: `POST /orders/{id}/issue` с `item_ids`. Заказ в `in_rental`, `order.issued` в outbox.
9. Менеджер: `POST /orders/{id}/return` с `item_ids`. Позиции в `returned`, залог возвращается, `order.returned` в outbox. Все позиции возвращены, заказ в `completed`.

**Отказные и гоночные пути:**
- **Товара нет.** `stock.reservation_failed`, заказ в `cancelled`, клиент видит причину.
- **Клиент не платит.** Через 30 минут заказ отменяется с `payment_timeout`, Catalog освобождает сток.
- **Карту отклонили.** `payment_intent.payment_failed`, заказ остаётся в `payment_pending`, клиент пробует снова.
- **Отмена сразу после создания.** Catalog получает `order.created` и `order.cancelled` по порядку. Order игнорирует `stock.reserved` для уже отменённого заказа.
- **Отмена во время оплаты.** Заказ отменён, PaymentIntent отменяется. Если `payment_intent.succeeded` всё же пришёл, OnPaymentSucceeded видит `cancelled`, делает полный возврат, `order.paid` не публикует.
- **Повтор `POST /orders`.** Тот же ключ и то же тело: 200 и существующий заказ. Другое тело: 422. Параллельный повтор: 409.
- **Повтор выдачи или приёмки.** Позиции уже в нужном статусе, пропускаются, ответ 200 с пометкой.
- **Потеря webhook.** Stripe повторяет доставку. Сверка с Stripe вне MVP.

## 8. Маршруты

| Метод | Путь | Роли | Примечание |
|---|---|---|---|
| POST | `/api/v1/orders` | customer | Обязателен `Idempotency-Key`. 201, 200 при повторе, 409 при параллельном |
| GET | `/api/v1/orders` | customer (свои), manager (свой магазин), admin, analyst (все) | Фильтры `status`, `store_id`. Курсорная пагинация |
| GET | `/api/v1/orders/{order}` | владелец, manager своего магазина, admin, analyst | Чужой заказ: 404 |
| POST | `/api/v1/orders/{order}/payment-intent` | customer (владелец) | Только в `stock_reserved` и `payment_pending` |
| POST | `/api/v1/orders/{order}/cancel` | customer (владелец), manager (свой магазин), admin | Только до первой выдачи |
| POST | `/api/v1/orders/{order}/issue` | manager (свой магазин), admin | Тело: `item_ids` |
| POST | `/api/v1/orders/{order}/return` | manager (свой магазин), admin | Тело: `item_ids` |
| GET | `/api/v1/analytics/overdue-rentals` | analyst, admin | Позиции в `issued` с `rental_end_at` в прошлом |
| POST | `/webhooks/stripe` | Stripe, без JWT | Проверка подписи на сыром теле |
| GET | `/healthz`, `/readyz` | публично | Readiness: PostgreSQL, Redis, RabbitMQ |

Все маршруты, кроме webhook и health, под `/api/v1` и требуют JWT.

## 9. Авторизация и middleware

Роли и `store_id` берутся из JWT. Названия ролей сверяем с `Enums/Role` в Catalog. `store_id` в токене менеджера обязателен.

**ABAC в `OrderPolicy`:**

| Действие | customer | manager | admin | analyst |
|---|---|---|---|---|
| view своего заказа | да | — | да | да |
| view чужого заказа | 404 | если тот же магазин, иначе 404 | да | да |
| create | да | нет | нет | нет |
| pay | владелец | нет | нет | нет |
| cancel | владелец, до выдачи | свой магазин, до выдачи | да, до выдачи | нет |
| issue, return | нет | свой магазин | да | нет |

Чужие заказы всегда 404, не 403, чтобы не раскрывать существование.

**Middleware:**

| Middleware | Где | Что делает | Статус |
|---|---|---|---|
| Authenticate (JWT) | `/api/v1` | Проверяет токен, кладёт claims в запрос | перенос |
| EnsureRole | по маршрутам | Проверка роли | перенос |
| ForceJson | `/api/v1`, webhook | Требует JSON, отдаёт problem+json | перенос |
| Idempotency | `POST /orders` | Заголовок 1–64 видимых ASCII символа, хэш тела, Redis-блокировка | новый |
| Throttle | `POST /orders`, `payment-intent` | Лимит на пользователя | фреймворк |
| VerifyStripeSignature | `/webhooks/stripe` | Подпись на сыром теле | новый |
| CorrelationId | все | `X-Request-Id` в логах и ответе | новый или перенос |
| SubstituteBindings | маршруты с `{order}` | Загрузка заказа | фреймворк |

Octane: claims и текущий пользователь не хранятся в статических свойствах.

## 10. Messaging

**Exchange `order.events`** (topic, durable, auto_delete false, internal false, без аргументов). Объявляют Order, Catalog и Notification идентично при старте. Параметры в `messaging.md`, тест каждого сервиса сверяет их с таблицей.

**Публикуем:** `order.created`, `order.cancelled`, `order.issued`, `order.returned`, `order.paid`.

**Конверт:** `event_id` (uuid), `schema_version` (1), `occurred_at` (UTC, `Y-m-d\TH:i:s.uP`). `event_id` совпадает с AMQP `message_id`.

**Consumer:**
- Exchange `catalog.events` (объявляет Catalog, мы объявляем идентично).
- Очередь `order.catalog-events`: quorum, durable, DLX, `x-delivery-limit` 5, prefetch 1.
- Биндинги: `stock.reserved`, `stock.reservation_failed`, `product.created`, `product.updated`, `product.deleted`. `stock.changed` не биндим.
- `schema_version` не равен 1: reject.
- Дедупликация через `processed_events` в той же транзакции, что и изменение состояния.
- Исходы ack, reject, retry, как в Catalog.

**Повторы (T2):** до подтверждения по документации RabbitMQ используем requeue и лимит попыток, как в Catalog. Сообщение, исчерпавшее лимит, уходит в DLX. Если подтвердится отложенный ресенд для quorum-очередей, его добавляем отдельным шагом, с точными аргументами из документации. Не закладываем флаги в код по памяти.

**Ограничение:** один экземпляр consumer на очередь. Порядок `product.*` защищён сравнением `source_occurred_at`.

**Порядок событий для Catalog:** `order.created` до `order.cancelled` в одном заказе гарантирован одним relay (один экземпляр) и `aggregate_key` = `order_id`.

## 11. Stripe

- Конфиг: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, валюта EUR. Тестовые ключи.
- Порт `PaymentGateway`: `createIntent`, `cancelIntent`, `refund`. Реализация на `stripe/stripe-php`, тонкая, без бизнес-логики.
- Ключи идемпотентности вызовов: `order:{id}:pi:{attempt}`, `order:{id}:refund:{item_or_all}`.
- Webhook: сырое тело, `Stripe\Webhook::constructEvent`, дедупликация по `stripe_events.id`, диспетчер по типу, как `InboundHandlerRegistry`.
- Перед обработкой сверяем сумму и валюту с записью в `payments`.
- 200 только после коммита. Ошибка: 500, Stripe повторит.
- Подписываемся на `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.canceled`.
- Refund сохраняем по ответу API. Отдельный webhook возврата в MVP не нужен.
- Локально `stripe-cli listen --forward-to` на `order-back` по http, секрет `whsec_...` в `.env`.

## 12. Redis

- Блокировка идемпотентности `order:idem:{customer_id}:{key}`, `SET NX EX 30`. Параллельный повтор: 409.
- Окончательную защиту от дублей даёт unique `(customer_id, idempotency_key)` в PostgreSQL.
- Redis не хранит статусы, деньги и состояние саги.
- Redis также store для throttle.
- phpredis в Docker-образе обязателен.

## 13. Фоновые процессы

| Процесс | Команда | Запуск |
|---|---|---|
| HTTP | Octane (RoadRunner) | `order-back` |
| Relay | `messaging:relay` | отдельный контейнер, один экземпляр |
| Consumer | `messaging:consume-catalog-events` | отдельный контейнер, один экземпляр |
| Таймер | `orders:expire-unpaid`, раз в минуту | scheduler, `FOR UPDATE SKIP LOCKED` |

## 14. Инфраструктура

- Compose: `order-back`, `order-relay`, `order-consumer`, `order-postgres`, `order-redis`, `order-front`, `nginx`, `stripe-cli`. RabbitMQ общий на проект, один экземпляр.
- Имена exchange и очередей не пересекаются между сервисами.
- nginx: 443, редирект с 80, self-signed, `/webhooks/stripe` без JWT.
- Секреты в `.env`, не в репозиторий.

## 15. Тестирование

- **Unit, Domain.** Все переходы из раздела 4, `Money`, `RentalPeriod`, `PriceCalculator`.
- **Unit, Application.** Use cases с фейками портов: переходы, outbox, вызовы Stripe.
- **Feature, Http.** PostgreSQL. ABAC-матрица, идемпотентность (200, 409, 422), 404 вместо 403, курсорная пагинация.
- **Feature, Messaging.** Consumer и relay на RabbitMQ за `RUN_RABBITMQ_TESTS`.
- **Feature, Payments.** Webhook с подписью, дедупликация, гонка отмены и оплаты.
- **Contract.** `messaging.md` против payload и таблицы exchange.
- **Concurrency.** Два `POST /orders` с одним ключом, два `issue` одной позиции.
- **Manual E2E.** Полный путь через stripe-cli в конце.

## 16. Фазы

| Фаза | Содержание | Критерий |
|---|---|---|
| P0 | Проверка precision в Catalog outbox. Файл `docs/order/decisions.md` с решениями из раздела 0. Ссылка и параметры ресенда от вас | Решения записаны, precision подтверждён |
| P1 | Каркас Laravel, перенос базы из раздела 2, провайдеры, healthz и readyz | Health 200, pint и PHPStan чисты, deptrac проходит |
| P2 | Миграции, Eloquent-модели, мапперы | `migrate:fresh` и откат проходят, схема по разделу 5 |
| P3 | Домен: VO, агрегат, переходы, расчёт | Все переходы раздела 4 покрыты unit-тестами |
| P4 | Порты и реализации, фейки | Один набор тестов проходит на фейке и на Eloquent |
| P5 | Снапшоты товаров, handlers `product.*` | Устаревшее сообщение отбрасывается |
| P6 | CreateOrder, GET-эндпоинты, идемпотентность, ABAC | 200 при повторе, 422 при другом теле, 409 при параллельном, ABAC зелёная |
| P7 | Сага: OnStockReserved, OnStockReservationFailed, consumer, outbox | Гонка отмены с `stock.reserved` не ломает состояние |
| P8 | Stripe: gateway, PaymentIntent, webhook, оплата | Гонка отмены и оплаты даёт полный возврат, прогон через stripe-cli |
| P9 | Отмена, выдача, приёмка, возврат залога, expire-unpaid | Полный путь из раздела 7 проходит |
| P10 | Аналитика просрочек, права analyst | Analyst только читает |
| P11 | Compose, nginx, stripe-cli, env | `docker compose up` поднимает цепочку |
| P12 | OpenAPI, финализация контракта, сверка с ТЗ | Чеклист ТЗ закрыт пункт за пунктом |
| P13 | Фронтенд | Сквозной сценарий из браузера |

Ресенд (T2) вводится отдельной фазой после подтверждения по документации, не блокирует P0–P12.

## 17. Открыто

1. Аргументы отложенного ресенда для quorum в RabbitMQ 4.3/4.4 с источником. До этого не реализуем.
2. Точные роли в `Enums/Role` Catalog. Сверить в P0.
3. Версия Stripe API и SDK. Зафиксировать в P0.
4. Перенос JWT-мидлвари: найти исходный код, он не был в выгрузке.
