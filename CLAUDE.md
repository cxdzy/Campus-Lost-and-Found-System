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
- **Primary frontends:** Telegram Bot (Node.js/Telegraf) + Vue.js SPA via Inertia.js (web)

---

## 2. Tech Stack

| Layer | Technology |
|-------|-----------|
| Back-end API | Laravel (PHP) |
| Web frontend | Vue.js SPA via **Inertia.js** |
| Telegram Bot | **Separate Node.js service** using **Telegraf** — own Dokploy service, own process |
| Vision tagging | **OpenCV** target — currently `MockCloudVisionService` (random tags + ApiLog) |
| Geolocation | Raw GPS lat/long — Haversine in `MatchingService`; map UI uses **Leaflet/OSM** |
| Database | **PostgreSQL** (Dokploy service) |
| Hosting / deploy | **VPS + Dokploy** — three separate services: Laravel, Node.js bot, Postgres |

> **Critical architecture boundary:** The Telegram bot (`bot.js`) is a **completely separate
> Node.js process**. It communicates with Laravel only by calling Laravel's HTTP API routes.
> It does NOT share PHP code, sessions, or a direct DB connection. Every feature that spans
> both the bot and Laravel must go through an API call (bot → POST Laravel endpoint).
> Never mix Node.js and PHP logic. Keep this boundary explicit in all new code.

When adding Laravel code: Eloquent models, Form Requests for validation, service classes for
integrations, queued jobs for slow work (vision, notifications), route-model binding.

---

## 3. Architecture & Workflows

### Found workflow (Telegram bot → Laravel API)

Current actual state:
1. Finder sends `/found` to bot.
2. Bot sets `userSessions[userId] = { step: 'AWAITING_PHOTO' }` (in-memory).
3. Finder uploads a photo with a caption.
4. Bot calls `ctx.telegram.getFileLink()` to get the image URL — then **stops** (`(coming soon)`).
5. ❌ Bot does NOT yet call the Laravel API (`BotSubmissionController` exists but is unreached).
6. ❌ No GPS step exists in the bot yet.

Target state (what needs to be built):
1. After photo received → bot calls `POST /api/bot/found` on Laravel with `{telegram_chat_id, image_url, caption}`.
2. Bot then asks for GPS location share.
3. After GPS received → bot calls `POST /api/bot/found/{id}/location` with `{latitude, longitude}`.
4. Laravel queues a vision job → runs MockCloudVisionService (or real OpenCV) → saves AI_TAGS.
5. Laravel runs MatchingService → if score > threshold, fires Telegram alert to loser.

### Lost workflow (Web SPA → Laravel)

Working. User registers via Inertia web form, drops a Leaflet pin, submits to Laravel.
Known bug: `fetchGalleryItems()` is called after successful report submission in
`Dashboard.vue:295` but the function is never defined → **runtime crash on the happy path**.

### Matching & resolution

`MatchingService` exists and works: Haversine distance + tag overlap → composite score.
Threshold is **hardcoded at `0.80` (line 16)** — must be moved to config.
Telegram dispatch is wired inside `MatchingService` but calls `Http::` directly — no `TelegramService` class exists yet.

---

## 4. Modules

### 4.1 Finder Module (Telegram Bot — Node.js)
- `/found` command, photo + caption capture, `/cancel` escape hatch.
- **Missing:** GPS step, HTTP call to Laravel API, session persistence across bot restarts.

### 4.2 Loser / User Module (Web SPA — Inertia + Vue)
- Auth via Matric Number, lost-item form, Leaflet map pin, My Reports tracking.
- **Bug:** `fetchGalleryItems` undefined crash after report submission.

### 4.3 Administrator & Security Module (Web Dashboard)
- Inventory list (Found items), basic Reports, Users management.
- **Missing:** Match Alerts UI (side-by-side comparison, "Verify & Notify", OTP handover).

---

## 5. Database Schema (from ERD — all migrations exist)

### Core (all tables created)
- **users**: `id`, `name`, `role` enum(`Admin`,`Security`,`User`), timestamps
- **finders**: `user_id` PK+FK→users, `telegram_chat_id`
- **losers**: `user_id` PK+FK→users, `matric_number`
- **categories**: `id`, `category_name`, `icon_identifier`
- **items**: `id`, `category_id` FK, `title_description`, `latitude`, `longitude`, `location_name`, `status` enum(`Pending`,`Matched`,`Claimed`)
- **found_items**: `item_id` PK+FK→items, `finder_id` FK→finders, `image_path`
- **lost_items**: `item_id` PK+FK→items, `loser_id` FK→losers, `image_path` (added via later migration)

### AI & Logic (all tables created)
- **ai_tags**: `id`, `found_item_id` FK, `tag_name`, `confidence_level` float
- **match_alerts**: `id`, `lost_item_id` FK, `found_item_id` FK, `match_score` float, `is_notified` bool

### Security & Monitoring (all tables created)
- **reownership_claims**: `id`, `found_item_id` FK, `loser_id` FK, `security_guard_id` FK, `otp_code`, `claimed_at`
- **api_logs**: `id`, `item_id` FK, `service` string, `http_status_code` int, `payload_response` text, `logged_at`

---

## 6. Existing Code — What Is Actually Built

### Models (all exist)
`User`, `Finder`, `Loser`, `Item`, `FoundItem`, `LostItem`, `Category`, `AiTag`,
`MatchAlert`, `Claim` (table: `reownership_claims`), `ReownershipClaim`, `ApiLog`

TPT pattern: derived models set `$primaryKey = 'item_id'` (or `user_id`) and
`$incrementing = false`. Follow this on any new derived model.

### Services
- `MatchingService` — Haversine + tag-overlap scoring + Telegram dispatch via `Http::`. ✅
- `MockCloudVisionService` — random tags + ApiLog entry. ✅ (real OpenCV integration missing)
- `TelegramService` — ❌ **does not exist.** `MatchingService` and `BotSubmissionController`
  call `Http::` directly. Must be created.

### Controllers
- `BotSubmissionController` — Laravel-side endpoint for bot → Laravel calls. ✅ exists, ❌ bot doesn't call it yet.
- `ItemController` — full CRUD. ✅
- `CategoryController` ✅
- `DashboardController`, `ReportsController`, `UsersController`, `AuthenticatedSessionController` ✅
- Standard Laravel Breeze auth controllers ✅

### Middleware
- `EnsureUserRole` — RBAC, gates Admin/Security routes. ✅
- `HandleInertiaRequests` ✅

### Form Requests
`StoreReportRequest`, `UpdateReportRequest`, `StoreUserRequest`, `UpdateUserRequest`,
`LoginRequest`, `ProfileUpdateRequest` — all exist.

### Vue Pages
Full student `Dashboard` (gallery + Leaflet form + my-reports + settings),
admin dashboard/reports/users, all auth pages — all exist.

### Queue Jobs
❌ **`app/Jobs/` directory does not exist.** Vision and matching run synchronously
inside the HTTP request. This must be fixed before real OpenCV is added.

---

## 7. Known Bugs & Missing Pieces

### 🔴 P1 — Crash
1. ~~**`fetchGalleryItems` undefined**~~ ✅ Fixed — defined at `Dashboard.vue:173`

### 🟡 P2 — CLAUDE.md violations
2. ~~**Hardcoded threshold**~~ ✅ Fixed — `config/matching.php` reads `MATCH_CONFIDENCE_THRESHOLD`
3. ~~**No TelegramService**~~ ✅ Fixed — `app/Services/TelegramService.php`
4. **`.env.example` gaps** — Missing full Postgres block and `LARAVEL_BOT_SECRET`

### 🟠 P3 — Core missing features
5. ~~**Bot → Laravel integration**~~ ✅ Fixed
6. ~~**GPS step in bot**~~ ✅ Fixed
7. ~~**Queue job for vision**~~ ✅ Fixed — `app/Jobs/ProcessVisionTagsJob.php`, dispatched
   from `BotSubmissionController` and `ItemController` after commit. Worker runs permanently
   in Dokploy via Advanced → Run Command.
8. ~~**Admin Match Alerts UI**~~ ✅ Fixed — `Admin/MatchAlerts.vue` + 
   `MatchAlertsController`, OTP via `TelegramService` with redacted payload log,
   `expires_at` on `reownership_claims`

### ⚪ P4 — UI polish
9. **"View on Map" button** — `Dashboard.vue:441` exists but does nothing
10. **"1 Alert" badge** — `Dashboard.vue:397` hardcoded, not driven by real data
11. **Map modal shows max 20 items** — capped by SSR gallery query. 
    Fix later: separate paginated endpoint for map pins.
12. **Popup z-index inside Leaflet DOM** — may clip on some browsers. 
    Fix: render popup outside LMap element.
    

## 8. Existing Code Patterns to Follow

Before writing any new file, read an existing file of the same type and match its structure exactly.

- **TPT Model:** `app/Models/FoundItem.php` — `$primaryKey`, `$incrementing`, `$fillable`, relationships
- **Service class:** `app/Services/MatchingService.php` — constructor injection, no static methods
- **Controller:** `app/Http/Controllers/ItemController.php` — DB transaction pattern, JSON responses
- **Form Request:** `app/Http/Requests/StoreReportRequest.php`
- **Migration:** any file in `database/migrations/` — Postgres-compatible types only
- **Vue page:** `resources/js/Pages/Dashboard.vue` — Inertia props, Leaflet usage, Tailwind classes

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

### JSON response pattern
```php
return response()->json(['data' => $resource], 201);   // created
return response()->json($paginated);                    // paginated list
```

### API_LOGS pattern (log every external service call)
```php
ApiLog::create([
    'item_id'          => $item->id,
    'service'          => 'OpenCV',   // or 'Telegram'
    'http_status_code' => $statusCode,
    'payload_response' => json_encode($response),
    'logged_at'        => now(),
]);
```

---

## 9. Conventions & Guardrails

- **Never hard-code secrets.** Bot token, DB credentials, threshold → `.env` and Dokploy env vars.
- **Threshold must be configurable.** Read from `config('lostfound.match_threshold')` everywhere.
  Admins adjust it via the dashboard (no code redeploy needed).
- **TelegramService is the only way to send Telegram messages.** Never call `Http::post` to
  Telegram directly from controllers or other services.
- **Queue all slow work.** Vision analysis and matching must run in jobs, not in the HTTP request.
- **Every external service call gets an ApiLog row.** No exceptions.
- **RBAC via `EnsureUserRole` middleware.** Never inline role checks in controllers.
- **OTPs are single-use and time-limited.** Invalidate on first use; add `expires_at` column.
- **Bot ↔ Laravel boundary.** Bot is Node.js. Laravel is PHP. Communication is HTTP only.
  Do not suggest merging them or sharing a DB connection from Node.js.
- **Postgres only.** No MySQL syntax. Use `string` + check constraints for enums, `float` for
  coordinates, `jsonb` for arbitrary payloads.
- **Inertia for web routes, JSON for API routes.** Don't mix them.

---

## 10. Bot Architecture Detail (Node.js — `bot.js`)

The Telegram bot is a standalone Node.js application using **Telegraf**. It runs as its own
Dokploy service on the VPS.

### Current bot state
- `/found` → sets in-memory session `{ step: 'AWAITING_PHOTO' }` ✅
- `/cancel` → clears session ✅
- Photo handler → gets file link, logs to console → **stops here** (`coming soon`) ⚠️
- Text fallback → nudges user to use `/found` ✅
- ❌ No GPS collection step
- ❌ No HTTP call to Laravel API
- ❌ In-memory sessions lost on bot restart (use Redis or DB for persistence later)

### Bot → Laravel API contract (to be built)
The bot must POST to these Laravel endpoints:

```
POST /api/bot/found
Body: { telegram_chat_id, image_url, caption, telegram_user_id }
Response: { found_item_id }   ← bot stores this in session for GPS step

POST /api/bot/found/{found_item_id}/location
Body: { latitude, longitude }
Response: { status: 'processing' }
```

These routes are handled by `BotSubmissionController` in Laravel (already exists).

### Bot `.env` variables needed
```env
TELEGRAM_BOT_TOKEN=your_token_here
LARAVEL_API_URL=http://laravel-service-name/api   # internal Dokploy network URL
LARAVEL_BOT_SECRET=a_shared_secret_for_auth       # so Laravel knows the call is from the bot
```

### Starting the bot
```bash
cd bot/          # or wherever bot.js lives
npm install
node bot.js      # or: pm2 start bot.js (if using pm2 on the VPS)
```

---

## 11. Common Commands

```bash
# Laravel setup
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Laravel run (local dev)
php artisan serve              # web server
php artisan queue:work         # job worker (vision + matching)
npm run dev                    # Vue/Inertia SPA build

# Bot run (local dev)
cd bot && node bot.js

# Code quality
php artisan test
./vendor/bin/pint
```

### `.env` required variables (Postgres + Dokploy)

```env
DB_CONNECTION=pgsql
DB_HOST=postgres-service-name   # Dokploy internal service name, not localhost
DB_PORT=5432
DB_DATABASE=lostfound
DB_USERNAME=postgres
DB_PASSWORD=...

TELEGRAM_BOT_TOKEN=...
LARAVEL_BOT_SECRET=...          # shared secret bot uses when calling Laravel API
MATCH_CONFIDENCE_THRESHOLD=0.80 # float, read by config/lostfound.php
```

---

## 12. Deployment (VPS + Dokploy — 3 services)

Three separate Dokploy services:
1. **Laravel app** — PHP web process + queue worker process
2. **Node.js bot** — `node bot.js`, long-running
3. **PostgreSQL** — managed by Dokploy, internal network only

All secrets go in **Dokploy environment variables**, never in the repo.

Laravel deploy steps: `composer install --no-dev` → `php artisan migrate --force` →
`php artisan config:cache` → `php artisan queue:restart`.

The queue worker must be a **long-lived process** in Dokploy — not a one-off command.
Without it, dispatched vision and matching jobs sit in the queue forever.

Bot deploy steps: `npm install --production` → `node bot.js`.

The bot and Laravel communicate over the **Dokploy internal network** using service names as
hostnames. Use the service name (e.g. `laravel-app`) as `LARAVEL_API_URL` in the bot's env,
not a public domain, so traffic stays internal and fast.

---

## 13. Glossary

- **Finder** — student who reports a found item via the Telegram bot.
- **Loser / User** — student who reports a lost item via the web SPA.
- **Match Alert** — automated notification fired when composite score > configured threshold.
- **OTP Handover** — claim flow: student presents a one-time code to security to verify ownership.
- **Confidence Threshold** — admin-adjustable float (default 0.80) read from `config/lostfound.php`.
- **TPT** — Table-Per-Type inheritance: `FoundItem` and `LostItem` share the `items` base table via FK.
- **Bot boundary** — the HTTP API layer separating the Node.js bot from the PHP Laravel backend.