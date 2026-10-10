# Campus Lost & Found

A Laravel back end that matches Telegram-reported found items against web-reported lost items using Google Cloud Vision tags and GPS proximity, then notifies the owner and issues a one-time claim code.

Live demo: https://campuslostfound.cxdzy.dev (offline for now)

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia.js-2-9553E9)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-blue?logo=postgresql&logoColor=white)
![Node](https://img.shields.io/badge/Node.js-22-339933?logo=node.js&logoColor=white)
![Telegraf](https://img.shields.io/badge/Telegraf-4.16-26A5E4?logo=telegram&logoColor=white)

## Related repositories

This repository is the Laravel back end and the Vue/Inertia web portal. The Telegram bot is a separate Node.js service in its own repository:

- **[Campus-Lost-and-Found-System-Bot](https://github.com/cxdzy/Campus-Lost-and-Found-System-Bot)**: Telegraf bot that walks a finder through `/found`, uploads the photo and GPS pin to this back end over HTTP, and handles `/link` for connecting a Telegram account to a web account.

The two services never share a database connection or PHP/Node code. They only talk over the HTTP API described below.

## Features

**Finder (Telegram bot)**
- `/found` flow: pick a category, upload a photo with a caption, then share GPS location
- `/link MATRIC_NUMBER` to attach a Telegram chat ID to an existing web account so match alerts can be delivered
- `/cancel` to reset an in-progress submission at any step

**User / Loser (web portal)**
- Register and log in with matric number and password
- Report a lost item: category, title/description, distinctive features, optional reference photo, and a Leaflet map pin for the last-known location
- Found item gallery with status badges and a search/category filter
- My Reports view showing a student's own lost reports and their current status
- "Generate Claim OTP" button once a report reaches `Matched` status, which sends a 6-digit code to the student's linked Telegram account
- Account settings page (name; matric number is read-only)

**Admin & Security (web dashboard)**
- Inventory table of all found items with photo, GPS, status, and an audit-details view of the raw AI tags
- Match Alerts screen: side-by-side comparison of the lost and found record, match score, "Verify & Notify" to (re)issue an OTP, and OTP confirmation to mark both items `Claimed`
- API Logs screen showing every Cloud Vision and Telegram call with HTTP status and timestamp
- User management and reports (list/create/update/delete)
- Role-based access via an `Admin`/`Security`/`User` enum on the `users` table

## How it works

1. A finder sends `/found` to the Telegram bot, picks a category, uploads a photo, then shares GPS location.
2. The bot POSTs the photo and caption to `/api/bot/submit`, then the GPS coordinates to `/api/bot/update-location`. Laravel creates an `Item` + `FoundItem` row and (once GPS is present) queues `ProcessVisionTagsJob`.
3. A student separately logs into the web portal and submits a lost-item report with a category, description, distinctive features, and a map pin. This creates an `Item` + `LostItem` row and runs the matching engine immediately.
4. For a found item, the queued job calls Google Cloud Vision to tag the photo, stores the tags in `ai_tags`, then runs the matching engine against every pending lost item.
5. If the composite score meets the configured threshold, a `MatchAlert` row is created, both items are marked `Matched`, and a Telegram message is sent to the student who reported the lost item.
6. The student (or an admin from the Match Alerts screen) generates a one-time 6-digit code, sent to the student's Telegram.
7. Security enters that code on the Match Alerts screen. If it matches and has not expired, both items are marked `Claimed`.

## Tech stack

| Layer | Technology | Repo |
|---|---|---|
| Back-end API / web app | Laravel 12, PHP ^8.2 | this repo |
| Web frontend | Vue 3 via Inertia.js 2, Tailwind CSS 3, Vite | this repo |
| Maps | Leaflet 1.9 + `@vue-leaflet/vue-leaflet` (OpenStreetMap tiles) | this repo |
| Vision tagging | Google Cloud Vision API, `LABEL_DETECTION`, called with native PHP curl | this repo |
| Telegram bot | Node.js ≥22, Telegraf 4.16 | bot repo |
| Database | PostgreSQL | this repo (migrations) |
| Auth/session | Laravel session auth, Sanctum installed | this repo |
| Deployment | Self-hosted VPS, Dokploy (Nixpacks build, no Dockerfile in-repo), Docker Swarm services | both |

## Architecture overview

```
 Telegram user
      │
      ▼
 ┌─────────────────────┐        HTTP + X-Bot-Secret header       ┌───────────────────────────┐
 │  Telegram Bot        │ ───────────────────────────────────▶  │  Laravel Back End          │
 │  (Node.js, Telegraf)  │ /api/bot/submit                       │  (this repo)                │
 │  separate repo/       │ /api/bot/update-location               │                             │
 │  Dokploy service       │ /api/bot/link-account                 │  ┌──────────────────────┐   │
 └─────────────────────┘                                         │  │ ProcessVisionTagsJob │   │
                                                                  │  └──────────┬───────────┘   │
                                                                  │             │ queued        │
      Student (web browser)                                     │             ▼                │
      │                                                          │  Google Cloud Vision API      │
      ▼  Inertia + session auth                                  │  (LABEL_DETECTION)             │
 ┌─────────────────────┐                                         │             │                │
 │  Vue 3 SPA / Inertia  │ ◀──────────────────────────────────── │             ▼                │
 │  Leaflet / OSM maps    │                                        │  MatchingService              │
 └─────────────────────┘                                         │  (Haversine + tag overlap)     │
                                                                  │             │                │
                                                                  │             ▼                │
                                                                  │  TelegramService.sendMessage   │
                                                                  └───────────────┬─────────────┘
                                                                                  │
                                                                                  ▼
                                                                      Telegram user (match alert / OTP)
                                                                                  │
                                                                                  ▼
                                                                       PostgreSQL (Dokploy service)
```

## Matching engine

Implemented in `app/Services/MatchingService.php`. For every pending lost item compared against a found item (or vice versa):

```
score = 0.60 × tagOverlapScore + 0.40 × proximityScore
```

- **tagOverlapScore**: fraction of the found item's Cloud Vision tags that appear in the lost item's `title_description` + `distinctive_features` text (case-insensitive substring match, checked in both directions: tag-in-description and description-word-in-tag). `0` if the found item has no tags.
- **proximityScore**: `1 - (distanceKm / 10)` using the Haversine formula between the two GPS points, floored at `0` once the distance reaches 10 km.
- **Threshold**: read from `config('matching.threshold')`, backed by `MATCH_CONFIDENCE_THRESHOLD` in `.env`, defaulting to `0.80`.

The code only creates a `MatchAlert` row and sends the Telegram notification when `score >= threshold`. There is no separate "low-confidence, logged but not notified" path: below the threshold, nothing is written and no message is sent. `MatchAlert.is_notified` is always set to `true` at creation time, since notification happens synchronously in the same call that creates the row.

Matching runs synchronously right after a lost item is submitted from the web portal, and inside `ProcessVisionTagsJob` (queued) after a found item's photo has been tagged.

## Database overview

Tables from `database/migrations/`:

- **users**: id, name, matric_number (unique, nullable), telegram_chat_id, role (`Admin`/`Security`/`User`), password
- **finders**: user_id (PK, FK → users), telegram_chat_id — Table-Per-Type extension of `users` for Telegram-only accounts
- **losers**: user_id (PK, FK → users), matric_number — TPT extension of `users` for web accounts
- **categories**: id, category_name, icon_identifier — 10 rows seeded by `CategorySeeder`
- **items**: id, category_id (FK), title_description, latitude, longitude, location_name, status (`Pending`/`Matched`/`Claimed`)
- **found_items**: item_id (PK, FK → items), finder_id (FK → finders), image_path
- **lost_items**: item_id (PK, FK → items), loser_id (FK → losers), distinctive_features, image_path (optional reference photo, added in a later migration)
- **ai_tags**: id, found_item_id (FK → found_items), tag_name, confidence_level
- **match_alerts**: id, lost_item_id (FK → items), found_item_id (FK → items), match_score, is_notified
- **reownership_claims**: id, found_item_id (FK → found_items), loser_id (FK → losers), security_guard_id (FK → users, nullable until an admin confirms), otp_code, expires_at, claimed_at
- **api_logs**: id, item_id (FK, nullable), service, http_status_code, payload_response, logged_at

Plus Laravel's own `password_reset_tokens`, `sessions`, `cache`, and `jobs` tables.

## Queue and jobs

- Queue driver: `database` (`QUEUE_CONNECTION=database` in `.env.example`). No Redis or SQS configured.
- `App\Jobs\ProcessVisionTagsJob` (3 tries): loads the `FoundItem`, calls `VisionService::analyse()` to tag the photo, then `MatchingService::matchFoundItem()`. Dispatched from `BotSubmissionController` once GPS is known, and from `ItemController@store` for web-submitted found items.
- Lost-item matching (`MatchingService::matchLostItem()`) runs synchronously in the request, not queued.
- No scheduler/cron tasks are registered; `routes/console.php` and `bootstrap/app.php` don't define any.
- The worker is started in the same container as the web process: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` runs in the background alongside `php-fpm`.

## Auth and roles

- Students register and log in with a matric number and password (`LoginRequest`, `RegisteredUserController`). There is no email/username login.
- Registration also creates a `Loser` row so the account can immediately report lost items.
- `users.role` is an enum: `Admin`, `Security`, `User`. `EnsureUserRole` middleware gates the `/admin/*` routes to `Admin,Security`; some maintenance routes (`/symlink-fix`, `/seed-categories`, `/run-migrations`) are `Admin`-only.
- The admin area has its own login route (`/admin/login`) and its own session guard flow, separate from the student login page.
- Telegram linking: a student sends `/link MATRIC_NUMBER` to the bot. The bot POSTs `{ matric_number, telegram_chat_id }` to `/api/bot/link-account`, which looks up the `Loser` by matric number and stores the chat ID on the parent `User` row. A Telegram-only user created automatically by a found-item submission (no matric number yet) is a separate `User`/`Finder` row until it is linked.

## External integrations

- **Google Cloud Vision API**: `VisionService::analyse()` builds a `LABEL_DETECTION` request (10 max results), base64-encodes the image, and posts it with native PHP `curl` (not Laravel's `Http` facade — see comment in the service, this was a deliberate workaround, not an oversight). Every call, success or failure, writes an `ApiLog` row with the raw label response or a `[DELIVERY FAILURE]` marker. If `GOOGLE_VISION_API_KEY` is empty, `MockCloudVisionService` is used instead, generating placeholder tags so the pipeline still runs end to end.
- **Leaflet / OpenStreetMap**: the lost-item report form and the map preview modal both use `@vue-leaflet/vue-leaflet` with OSM tiles. Google Maps is not used anywhere in the code.
- **Telegram Bot API**: all outbound messages go through `TelegramService::sendMessage()`, which logs to `ApiLog` (with an option to redact the payload for OTP messages) and is the only place that talks to Telegram from the PHP side. Inbound bot interaction is entirely handled by the separate Node.js/Telegraf service.

## Bot ↔ back end contract

All three endpoints require an `X-Bot-Secret` header matching `LARAVEL_BOT_SECRET` (compared after trimming both sides).

```
POST /api/bot/submit
Body: { telegram_chat_id, image_url, caption, category_id }
Response: { id }

POST /api/bot/update-location
Body: { found_item_id, latitude, longitude }
Response: { message, id }

POST /api/bot/link-account
Body: { matric_number, telegram_chat_id }
Response: { message }
```

`submit` also accepts optional `latitude`/`longitude` directly (used if a future bot flow sends GPS in one step), but the current bot always calls `update-location` as a second step.

## Getting started

### Prerequisites

- PHP 8.2+, Composer
- Node.js 22 (see `.nvmrc`), npm
- PostgreSQL

### Install

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

### Environment

Variables read by the app (see `.env.example` for the full local-dev template; names only, no values here):

```
APP_URL, LARAVEL_APP_URL
DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
SESSION_DRIVER, QUEUE_CONNECTION, CACHE_STORE
TELEGRAM_BOT_TOKEN
LARAVEL_BOT_SECRET
MATCH_CONFIDENCE_THRESHOLD
GOOGLE_VISION_API_KEY
STORAGE_PUBLIC_URL
DOKPLOY_SERVICE_NAME
```

Optional, used only by `AdminUserSeeder` to create the first admin account:

```
ADMIN_MATRIC, ADMIN_PASSWORD, ADMIN_NAME
```

`STORAGE_PUBLIC_URL` must be a publicly reachable URL, not `localhost`, since `VisionService` builds the image URL it hands to Google from this value.

### Database and run

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan queue:work
npm run dev
```

Or `composer run dev` to run the server, queue listener, log tail, and Vite dev server together.

### Running the bot

The bot lives in its own repository and its own `.env` (`TELEGRAM_BOT_TOKEN`, `LARAVEL_APP_URL`, `LARAVEL_BOT_SECRET`, the last two matching this repo's values). Clone it separately and follow its own setup:

```bash
git clone https://github.com/cxdzy/Campus-Lost-and-Found-System-Bot.git
cd Campus-Lost-and-Found-System-Bot
npm install
npm start
```

## Telegram bot commands

From the bot repo (`bot.js`):

- `/start`: welcome message and instructions
- `/found`: begins the found-item flow (category → photo + caption → GPS location)
- `/link MATRIC_NUMBER`: links this Telegram chat to an existing web account
- `/cancel`: clears any in-progress `/found` session

## Project structure

```
app/
  Http/Controllers/Api/       BotSubmissionController, ItemController, CategoryController
  Http/Controllers/Admin/     DashboardController, MatchAlertsController, ReportsController, UsersController, ApiLogsController
  Http/Controllers/           StudentClaimController and standard Breeze auth controllers
  Http/Middleware/            EnsureUserRole, HandleInertiaRequests
  Jobs/                       ProcessVisionTagsJob
  Models/                     User, Finder, Loser, Item, FoundItem, LostItem, Category, AiTag, MatchAlert, ReownershipClaim, ApiLog
  Services/                   VisionService, MockCloudVisionService, MatchingService, TelegramService
config/matching.php           match threshold
database/migrations/
database/seeders/             CategorySeeder, AdminUserSeeder
resources/js/Pages/           Dashboard.vue, Welcome.vue, Admin/*, Auth/*, Profile/*
routes/                       web.php, api.php, auth.php
scripts/deploy.sh             Dokploy post-deploy hook
docker/nginx-upload.conf       upload size patch
```

## Screenshots

_Add screenshots of the running app under `docs/screenshots/` and reference them here, for example:_

```
docs/screenshots/student-dashboard.png
docs/screenshots/report-lost-item.png
docs/screenshots/admin-match-alerts.png
docs/screenshots/admin-inventory.png
```

## Known limitations

- The bot keeps submission state in an in-memory object (`userSessions`), so an in-progress `/found` conversation is lost if the bot process restarts.
- The map/gallery view on the student dashboard is capped at 20 items server-side (no pagination on that endpoint yet).
- `package.json` in the bot repo lists `pg` as a dependency, but `bot.js` does not use it; the bot has no direct database connection, only HTTP calls to this back end.
- There is no scheduler/cron job in this repo; all background work goes through the single queued job described above.

## Course info

- Course: ITT626, Back-End Technology, UiTM
- Author: Mohamad Haziq Naqib Bin Zaid (2025181477)
- Lecturer: Idayati Mazlan
