# CLAUDE.md

Guidance for AI coding agents (Claude Code / VS Code) working in this repository.

---

## 1. Project Overview

**Campus Lost & Found** is an AI-powered, back-end-driven system that modernizes the manual
lost-and-found process on the UiTM campus. It replaces paper logbooks and notice boards with an
automated recovery pipeline built around a **Laravel back-end** orchestrating vision analysis,
spatial matching, and Telegram notifications.

The defining feature is **AI Vision + spatial matching**: when a "Found" item is reported via the
Telegram bot, the system extracts visual tags (currently mocked; OpenCV target) and stores GPS
coordinates. A matching engine compares tags + proximity against Lost reports. A notification fires
only when the confidence score exceeds the configured threshold (default 80%).

- **Course context:** ITT626 — Back-End Technology
- **Deployment:** Self-hosted VPS managed with **Dokploy** — Laravel app, Node.js bot, and
  PostgreSQL each run as separate Dokploy services
- **Primary frontends:** Telegram Bot (Node.js/Telegraf, separate GitHub repo) + Vue.js SPA via Inertia.js (web)

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| Back-end API | Laravel (PHP) |
| Web frontend | Vue.js SPA via **Inertia.js** |
| Telegram Bot | **Separate Node.js service** using **Telegraf** — own Dokploy service, own GitHub repo (`Campus-Lost-and-Found-System-Bot`) |
| Vision tagging | **OpenCV** target — currently `MockCloudVisionService` (random tags + ApiLog) |
| Geolocation | Raw GPS lat/long — Haversine in `MatchingService`; map UI uses **Leaflet/OSM** |
| Database | **PostgreSQL** (Dokploy service) |
| Hosting / deploy | **VPS + Dokploy** — three separate services: Laravel, Node.js bot, Postgres |

> **Critical architecture boundary:** The Telegram bot (`bot.js`) is a **completely separate
> Node.js process in its own GitHub repo**. It communicates with Laravel only via HTTP API calls.
> It does NOT share PHP code, sessions, or a direct DB connection. Never mix Node.js and PHP logic.

When adding Laravel code: Eloquent models, Form Requests for validation, service classes for
integrations, queued jobs for slow work (vision, notifications), route-model binding.

---

## 3. Architecture & Workflows

### Found workflow (Telegram bot → Laravel API) ✅ FULLY WORKING

1. Finder sends `/found` to bot → bot shows category inline keyboard (10 categories).
2. Finder selects a category → bot prompts for photo.
3. Finder uploads photo with caption → bot POSTs to `POST /api/bot/submit` with
   `{telegram_chat_id, image_url, caption, category_id}`.
4. Laravel `BotSubmissionController@store`:
   - Finds or creates `User` + `Finder` from `telegram_chat_id` (inside DB transaction)
   - Downloads and stores image to VPS
   - Creates `Item` + `FoundItem` records
   - Dispatches `ProcessVisionTagsJob` after commit
5. Bot receives `{id}` response → stores `found_item_id` in session → asks for GPS.
6. Finder shares location → bot POSTs to `POST /api/bot/update-location` with
   `{found_item_id, latitude, longitude}`.
7. Laravel updates Item coordinates → job runs `MockCloudVisionService` → saves `AiTag` records.
8. `MatchingService` runs → if score > threshold → fires Telegram alert to loser via `TelegramService`.

### Lost workflow (Web SPA → Laravel) ✅ FULLY WORKING

1. User logs in via Matric Number + password.
2. Fills "Report Lost Item" form — title, category, distinctive features, optional reference photo.
3. Drops a Leaflet/OSM pin for last-known location → lat/long stored.
4. Submits → Laravel creates `Item` + `LostItem` → `MatchingService` runs synchronously.

### Matching & resolution ✅ WORKING

- `MatchingService`: Haversine distance + tag overlap → composite score.
- Threshold read from `config('matching.threshold')` → env `MATCH_CONFIDENCE_THRESHOLD` (default 0.80).
- Score > threshold → writes `MatchAlert` row → `TelegramService::sendMessage()` notifies loser.
- Admin sees match in **Match Alerts** dashboard → clicks "Verify & Notify" → OTP generated in
  `reownership_claims` with `expires_at` 5 min → sent to student via Telegram.
- Student presents OTP at security desk → staff verifies → item marked `Claimed`.

### Bot → Laravel API contract

```
POST /api/bot/submit
Headers: X-Bot-Secret: <LARAVEL_BOT_SECRET>
Body: { telegram_chat_id, image_url, caption, category_id }
Response: { id: found_item_id }

POST /api/bot/update-location
Headers: X-Bot-Secret: <LARAVEL_BOT_SECRET>
Body: { found_item_id, latitude, longitude }
Response: { status: 'ok' }
```

Both endpoints require the `X-Bot-Secret` header — checked against `config('services.bot.secret')`.

---

## 4. Modules

### 4.1 Finder Module (Telegram Bot — Node.js, separate repo) ✅
- `/found` → category inline keyboard → photo + caption → GPS location → complete
- `/cancel` escape hatch at any step
- Auto creates `User` + `Finder` record on first submission
- Bot runs on Dokploy, auto-deploys from `Campus-Lost-and-Found-System-Bot` repo

### 4.2 Loser / User Module (Web SPA — Inertia + Vue) ✅
- Auth via Matric Number, lost-item form, Leaflet map pin, My Reports tracking
- Alert badge shows real unread `match_alerts` count (`is_notified = false`)
- "Generate Claim OTP" triggers OTP creation and Telegram notification

### 4.3 Administrator & Security Module (Web Dashboard) ✅
- Inventory list — Found items with photo, GPS, status badges, Audit Details modal
- Match Alerts — side-by-side Lost vs Found comparison, match score, "Verify & Notify"
- API Logs — real-time OpenCV/Telegram transaction monitoring
- Users management, Reports

---

## 5. Database Schema (all migrations exist and have run)

### Core
- **users**: `id`, `name`, `role` enum(`Admin`,`Security`,`User`), `telegram_chat_id`, `matric_number`, timestamps
- **finders**: `user_id` PK+FK→users, `telegram_chat_id`
- **losers**: `user_id` PK+FK→users, `matric_number`
- **categories**: `id`, `category_name`, `icon_identifier` — 10 categories seeded (id 1=Others through id 10=Stationery)
- **items**: `id`, `category_id` FK, `title_description`, `latitude`, `longitude`, `location_name`, `status` enum(`Pending`,`Matched`,`Claimed`)
- **found_items**: `item_id` PK+FK→items, `finder_id` FK→finders, `image_path`
- **lost_items**: `item_id` PK+FK→items, `loser_id` FK→losers, `image_path`

### AI & Logic
- **ai_tags**: `id`, `found_item_id` FK, `tag_name`, `confidence_level` float
- **match_alerts**: `id`, `lost_item_id` FK, `found_item_id` FK, `match_score` float, `is_notified` bool

### Security & Monitoring
- **reownership_claims**: `id`, `found_item_id` FK, `loser_id` FK, `security_guard_id` FK, `otp_code`, `expires_at` timestamp, `claimed_at`
- **api_logs**: `id`, `item_id` FK nullable, `service` string, `http_status_code` int, `payload_response` text, `logged_at`

---

## 6. Existing Code — What Is Actually Built

### Models (all exist)
`User`, `Finder`, `Loser`, `Item`, `FoundItem`, `LostItem`, `Category`, `AiTag`,
`MatchAlert`, `Claim` / `ReownershipClaim` (table: `reownership_claims`), `ApiLog`

TPT pattern: derived models set `$primaryKey = 'item_id'` (or `user_id`) and
`$incrementing = false`. Follow this on any new derived model.

### Services (all exist)
- `MatchingService` — Haversine + tag-overlap scoring, dispatches via `TelegramService` ✅
- `MockCloudVisionService` — random tags + ApiLog entry ✅
- `TelegramService` — `sendMessage(chatId, text, ?itemId, redactPayload)`, logs to ApiLog ✅

### Controllers (all exist)
- `BotSubmissionController` — `store()` + `updateLocation()`, both gated by `X-Bot-Secret` ✅
- `ItemController` — full CRUD, dispatches `ProcessVisionTagsJob` for Found items ✅
- `CategoryController` ✅
- `Admin\MatchAlertsController` — `index()` + `verify()` (OTP generation + Telegram send) ✅
- `DashboardController`, `ReportsController`, `UsersController`, `AuthenticatedSessionController` ✅

### Middleware
- `EnsureUserRole` — RBAC, gates Admin/Security routes ✅
- `HandleInertiaRequests` ✅

### Form Requests (all exist)
`StoreReportRequest` (20MB image limit), `UpdateReportRequest`, `StoreUserRequest`,
`UpdateUserRequest`, `LoginRequest`, `ProfileUpdateRequest`

### Jobs
- `ProcessVisionTagsJob` — 3 retries, runs `MockCloudVisionService` then `MatchingService` ✅

### Vue Pages (all exist)
- Student: `Dashboard` (gallery + Leaflet report form + my-reports + settings + map modal)
- Admin: `Admin/Dashboard`, `Admin/MatchAlerts`, `Admin/Reports`, `Admin/Users`
- Auth: all standard pages

---

## 7. Known Issues & Remaining Work

### ✅ All P1/P2/P3 items resolved

### ⚪ P4 — UI polish (low priority)
11. **Map modal shows max 20 items** — capped by SSR gallery query.
    Fix later: separate paginated endpoint for map pins.
12. **Popup z-index inside Leaflet DOM** — may clip on some browsers.
    Fix: render popup outside `<LMap>` element.

### 🔵 P5 — Future (blocked on external dependency)
13. **Real OpenCV integration** — swap `MockCloudVisionService` for real HTTP call to OpenCV
    microservice inside `ProcessVisionTagsJob`. Everything else (ApiLog, MatchingService,
    queue) stays the same — single class swap when microservice is ready.

---

## 8. Existing Code Patterns to Follow

Before writing any new file, read an existing file of the same type and match its structure exactly.

- **TPT Model:** `app/Models/FoundItem.php`
- **Service class:** `app/Services/TelegramService.php` — constructor injection, logs to ApiLog
- **Controller:** `app/Http/Controllers/Api/BotSubmissionController.php` — transaction pattern, bot secret guard
- **Admin Controller:** `app/Http/Controllers/Admin/MatchAlertsController.php`
- **Form Request:** `app/Http/Requests/StoreReportRequest.php`
- **Migration:** any file in `database/migrations/` — Postgres-compatible types only
- **Vue page:** `resources/js/Pages/Dashboard.vue` — Inertia props, Leaflet, Tailwind

### DB transaction pattern (always use for multi-table writes)
```php
DB::beginTransaction();
try {
    // writes here
    DB::commit();
    // vision + matching calls AFTER commit, in separate try/catch
} catch (\Exception $e) {
    DB::rollBack();
    return response()->json(['error' => $e->getMessage()], 500);
}
```

### Bot secret guard pattern (use in all bot-facing endpoints)
```php
private function authorizeBot(): ?JsonResponse
{
    $secret = config('services.bot.secret');
    if ($secret && request()->header('X-Bot-Secret') !== $secret) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    return null;
}
```

### ApiLog pattern (log every external service call)
```php
ApiLog::create([
    'item_id'          => $item->id,   // nullable for non-item calls
    'service'          => 'OpenCV',    // or 'Telegram'
    'http_status_code' => $statusCode,
    'payload_response' => $redact ? '[REDACTED]' : json_encode($response),
    'logged_at'        => now(),
]);
```

---

## 9. Conventions & Guardrails

- **Never hard-code secrets.** Bot token, DB credentials, threshold → `.env` + Dokploy env vars.
- **Threshold:** read from `config('matching.threshold')` everywhere. Never `0.80` as a literal.
- **Telegram messages:** only via `TelegramService::sendMessage()`. Never bare `Http::post` to Telegram.
- **Bot secret:** all bot-facing endpoints must call `authorizeBot()`. Secret in `config('services.bot.secret')`.
- **Queue all slow work.** Vision and matching run in `ProcessVisionTagsJob`, not in HTTP requests.
- **Every external service call gets an ApiLog row.** No exceptions.
- **RBAC:** `EnsureUserRole` middleware only. Never inline role checks in controllers.
- **OTPs:** single-use, `expires_at` enforced, never logged in plaintext to ApiLog.
- **Bot ↔ Laravel boundary:** HTTP only. No shared DB connection from Node.js.
- **Postgres only.** No MySQL syntax. `float` for coordinates, `jsonb` for payloads.
- **Inertia for web routes, JSON for API routes.** Never mix.
- **Upload limit:** 20MB enforced at three layers — `public/.user.ini`, `docker/nginx-upload.conf`, `StoreReportRequest`.

---

## 10. Bot Architecture (separate repo: `Campus-Lost-and-Found-System-Bot`)

### Bot flow (fully working)
```
/found
  → inline keyboard: 10 category buttons (2 per row)
  → user taps category → session: { step: AWAITING_PHOTO, category_id }
  → bot asks for photo
  → user uploads photo + caption
  → bot POSTs to Laravel /api/bot/submit
  → session: { step: AWAITING_LOCATION, found_item_id }
  → bot asks for GPS location
  → user shares location
  → bot POSTs to Laravel /api/bot/update-location
  → bot replies: "Thank you! 🎉"
```

### Bot categories (hardcoded — matches DB)
```javascript
const CATEGORIES = [
    { id: 2, name: 'Electronics' }, { id: 3, name: 'Wallets' },
    { id: 4, name: 'Keys' },        { id: 5, name: 'IDs' },
    { id: 6, name: 'Accessories' }, { id: 7, name: 'Bags & Backpacks' },
    { id: 8, name: 'Clothing' },    { id: 9, name: 'Books' },
    { id: 10, name: 'Stationery' }, { id: 1, name: 'Others' },
];
```

> `GET /api/categories` exists if you want to switch to dynamic loading later.

### Bot `.env` (in bot repo, not committed)
```env
TELEGRAM_BOT_TOKEN=...
LARAVEL_APP_URL=https://your-domain.com
LARAVEL_BOT_SECRET=...   # must match Laravel's LARAVEL_BOT_SECRET exactly
```

### Known limitation
- In-memory sessions (`userSessions` object) — lost on bot restart. Use Redis for persistence later.

---

## 11. Common Commands

```bash
# Laravel setup
composer install && cp .env.example .env
php artisan key:generate && php artisan migrate --seed

# Laravel local dev
php artisan serve          # web server
php artisan queue:work     # job worker (vision + matching)
npm run dev                # Vue/Inertia SPA

# Artisan on production (Dokploy terminal → cd /app)
php artisan migrate --force
php artisan tinker

# Code quality
php artisan test && ./vendor/bin/pint
```

### `.env` required variables
```env
DB_CONNECTION=pgsql
DB_HOST=postgres-service-name   # Dokploy internal name, not localhost
DB_PORT=5432
DB_DATABASE=lostfound
DB_USERNAME=postgres
DB_PASSWORD=...

TELEGRAM_BOT_TOKEN=...
LARAVEL_BOT_SECRET=...
LARAVEL_APP_URL=https://your-domain.com
MATCH_CONFIDENCE_THRESHOLD=0.80
```

---

## 12. Deployment (VPS + Dokploy — 3 services)

| Service | Repo | Start command |
|---------|------|---------------|
| Laravel app | `Campus-Lost-and-Found-System` | `/bin/sh -c 'php artisan migrate --force && php artisan config:cache && php artisan queue:work --sleep=3 --tries=3 --max-time=3600 & php-fpm'` |
| Node.js bot | `Campus-Lost-and-Found-System-Bot` | `npm start` (runs `node --dns-result-order=ipv4first bot.js`) |
| PostgreSQL | Dokploy managed | — |

- All secrets in **Dokploy environment variables**, never in the repo.
- Migrations run automatically on every Laravel deploy (`migrate --force` in start command).
- Queue worker runs permanently in the same container as the web process (background `&`).
- Nginx upload limit patched by `deploy.sh` on every deploy (idempotent `grep` check).
- **To run artisan on production:** Dokploy → Laravel service → Open Terminal → `cd /app && php artisan <command>`.

---

## 13. Glossary

- **Finder** — student who reports a found item via the Telegram bot.
- **Loser / User** — student who reports a lost item via the web SPA.
- **Match Alert** — automated notification fired when composite score > configured threshold.
- **OTP Handover** — claim flow: student presents a one-time code to security to verify ownership.
- **Confidence Threshold** — admin-adjustable float (default 0.80) read from `config('matching.threshold')`.
- **TPT** — Table-Per-Type: `FoundItem`/`LostItem` share the `items` base table via FK.
- **Bot boundary** — the HTTP API layer separating the Node.js bot from the PHP Laravel backend.
- **MockCloudVisionService** — placeholder for real OpenCV; generates random tags + ApiLog entry.