# Фронтенд в Docker

## Общий стек: REAL

Для локальной разработки с общим HTTPS gateway, из корня проекта:

```sh
FRONTEND_URL=https://localhost FRONTEND_API_URL=https://localhost docker compose up -d --build
```

В production явно задайте `FRONTEND_URL` (HTTPS origin SPA), `FRONTEND_API_URL` (публичный origin API gateway) и `REDIRECT_URL` (backend callback Google OAuth) для своего домена через окружение или Compose `--env-file`. Значения localhost выше — только локальный пример. Production Compose требует непустой `FRONTEND_API_URL` и не подставляет localhost; backend также требует `FRONTEND_URL`.

Корневой `docker-compose.yaml` подключает `frontend/docker-compose.prod.yaml` через `include`, как compose сервисов бэкенда. `frontend` находится в сети `shop_global`, слушает внутренний порт `8080` и не публикует отдельный host-порт. Глобальный nginx ждёт здоровые frontend/auth-nginx/notification-nginx, проксирует `/` во фронт, а `/auth/` и `/notification/` — в существующие API-прокси.

Открыть `http://localhost`: существующий порт 80 перенаправляет на `https://localhost`. Фронт и API доступны под одним HTTPS origin, поэтому не требуется CORS между ними и сохраняется работа `Secure; SameSite=Strict` cookies. Локальный сертификат глобального nginx должен быть доверенным браузером. Сам фронтенд-контейнер обслуживает HTTP на 8080; TLS завершается на глобальном nginx.

Чтобы собрать только фронт, не запуская бэкенд:

```sh
docker compose build frontend
```

Чтобы применить новый фронт к уже запущенному общему стеку:

```sh
docker compose up -d --no-deps --build frontend
docker compose exec global-nginx nginx -s reload
```

Reload обновляет DNS-адрес upstream после пересоздания frontend-контейнера. Общий стек должен быть уже запущен; `--no-deps` не запускает отсутствующие сервисы.

## Независимый стек: MOCK

Из корня проекта:

```sh
docker compose -f frontend/docker-compose.local.yaml up -d --build --wait
```

Открыть `http://localhost:3000`. Только фронтенд, собственная compose-сеть, без API, баз данных, RabbitMQ, TLS и глобального nginx. Порт опубликован на loopback; доступ с других машин не открыт. Это собранная MOCK-версия SPA, не Vite dev server и не hot reload.

Другой порт:

```sh
FRONTEND_MOCK_PORT=3005 docker compose -f frontend/docker-compose.local.yaml up -d --build --wait
```

Остановка MOCK:

```sh
docker compose -f frontend/docker-compose.local.yaml down
```

Google OAuth требует настоящего сервера и в автономном MOCK не тестируется.

## Сборка и конфигурация

Один `frontend/Dockerfile`, три этапа:

1. `dependencies`: Node 22 Alpine, pnpm **12.3.4** из package.json, `--frozen-lockfile`, BuildKit cache для pnpm store.
2. `build`: TypeScript-проверка и Vite-сборка выбранного режима. Копируются только исходники, public и файлы конфигурации сборки.
3. `runtime`: nginx 1.28 Alpine, только результат сборки и конфигурация nginx. Стандартная welcome-страница удалена. Node, pnpm, зависимости, исходники и кеши сборщика не попадают в итоговый этап.

Аргументы сборки:

| Аргумент | REAL compose | MOCK compose |
| --- | --- | --- |
| `VITE_API_MODE` | `real` | `mock` |
| `VITE_API_URL` | обязательный `${FRONTEND_API_URL:?...}` | `https://localhost` (mock adapter не использует API) |

При размещении на другом домене указать его внешний HTTPS origin и пересобрать:

```sh
FRONTEND_API_URL=https://rentify.example docker compose build frontend
```

При последующем запуске использовать то же значение переменной. При необходимости сохранить `FRONTEND_API_URL` в корневой `.env` самостоятельно. Указывать внешний origin браузера, **не** Docker-имя `auth-app` и не `/auth` в base URL: API-префиксы уже есть в клиенте.

При прямой сборке Dockerfile передайте `--build-arg VITE_API_URL=https://rentify.example`: у этого аргумента нет значения по умолчанию. REAL-сборка без адреса завершается ошибкой валидации. Публичные `.env.mock` и `.env.real` содержат только селектор `VITE_API_MODE`; адрес локального API для Vite задаётся в игнорируемом `.env.real.local` по шаблону `.env.real.local.example`.

Vite встраивает значения в JavaScript во время сборки. Runtime `environment:` их не меняет; секреты в `VITE_*` недопустимы. `.dockerignore` исключает `.env*`, node_modules, старый dist, служебные каталоги, документацию и тестовые артефакты. REAL/MOCK образы имеют разные теги: `shop-frontend:real` и `shop-frontend:mock`.

## Права, nginx и healthcheck

- nginx master и workers работают от `101:101`. Статика принадлежит root, права каталогов 755, файлов 644; процесс не может их изменять.
- В compose: `read_only: true`, `cap_drop: [ALL]`, `no-new-privileges:true`; запись только в ограниченный tmpfs `/tmp` (32 МБ, noexec, nosuid).
- Логи идут в stdout/stderr, временные файлы и pid — в `/tmp`. Базовые entrypoint-скрипты nginx не запускаются и не переписывают конфигурацию.
- Слушается только `8080`; `EXPOSE` — метаданные, а не публикация host-порта. Базовый nginx-образ также наследует метаданные `80/tcp`, но сервер на 80 внутри фронта не запускается.
- Healthcheck проверяет `GET /healthz` самого фронтенд-контейнера, не сервисные API. Остановка через SIGQUIT даёт nginx завершить текущие запросы.
- SPA fallback открывает прямые ссылки `/login`, `/register` и другие. `index.html` не кешируется, хешированные `/assets/` кешируются на год с immutable. Отсутствующие статические файлы возвращают 404, а не HTML.
- Отдельный runtime фронта не проксирует `/auth/` или `/notification/`: эти запросы должен обслуживать глобальный nginx.

## Проверка

```sh
docker compose config --quiet
docker compose -f frontend/docker-compose.prod.yaml config --quiet
docker compose -f frontend/docker-compose.local.yaml config --quiet
docker compose -f frontend/docker-compose.local.yaml ps
docker compose -f frontend/docker-compose.local.yaml exec frontend nginx -t
```

В ходе реализации реально собраны оба образа, запущен независимый MOCK, проверены healthcheck, SPA fallback, MIME/cache заголовки, 404 и отсутствие инструментов сборки в runtime. Глобальная конфигурация nginx проверяется отдельно с REAL-фронтом и временными HTTPS-заглушками API; это проверка маршрутизации, не авторизации или логики бэкенда. Полный общий стек в ходе этой задачи не запускается, файлы `services/` не изменяются.
