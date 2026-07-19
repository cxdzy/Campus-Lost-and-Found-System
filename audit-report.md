# Campus Lost & Found — Technical Audit Report

Audit date: 2026-07-19. Scope: this repository only (Laravel app). The Telegram bot lives in a
separate repo and was not inspected — statements about it below are inferred from the Laravel
side of the API contract, not from bot source code.

---

## 1. Matching Engine

**File:** `app/Services/MatchingService.php`

### Trigger mechanism
Not event/observer-based — it's a **directly-called service method**, invoked from two places:

- `app/Jobs/ProcessVisionTagsJob.php:37` — `$matching->matchFoundItem($foundItem)`, called
  **after** `VisionService::analyse()` inside a queued job (`ShouldQueue`, 3 tries). Triggered
  when a Found item gets GPS coordinates (bot `/api/bot/submit` with GPS, or `/api/bot/update-location`).
- `app/Http/Controllers/Api/ItemController.php:144` — `$this->matchingService->matchLostItem($lostItem)`,
  called **synchronously in the HTTP request** (not queued) right after a Lost item is created via
  the web SPA. Wrapped in try/catch that only logs a warning — a matching failure does not fail
  the item-creation request.

So: Found-item matching is async (queued job), Lost-item matching is synchronous (inline in the
controller). Both call the same `computeScore()` logic.

### Confidence score formula
```php
// MatchingService.php:101-110
private function computeScore(array $foundTags, Item $foundBase, Item $lostBase): float
{
    $tagScore       = $this->tagOverlapScore($foundTags, $lostBase->title_description);
    $proximityScore = $this->proximityScore(
        $foundBase->latitude, $foundBase->longitude,
        $lostBase->latitude,  $lostBase->longitude,
    );

    return 0.60 * $tagScore + 0.40 * $proximityScore;
}
```
Weighted sum: **60% tag overlap + 40% GPS proximity**. This is a hardcoded weighting (0.60/0.40
literals in code, not configurable).

**Tag overlap** (`tagOverlapScore`, lines 114-149): compares the Found item's AI Vision tags
against the Lost item's free-text `title_description` field (not a structured tag list — Lost
items have no AI tags of their own). Bidirectional substring match:
1. Direct: does the full tag string appear as a substring of the lowercased description?
2. Bidirectional: does any word (≥3 chars, not in a small stopword list) from the description
   appear as a substring of the tag?

Score = `hits / count($foundTags)`. Returns `0.0` if the found item has no tags. This is a crude
substring-matching heuristic, not embeddings/NLP similarity.

**Proximity score** (`proximityScore`/`haversineKm`, lines 151-173): standard **Haversine formula**
(great-circle distance, Earth radius 6371 km), then linear decay:
```php
private const MAX_DISTANCE_KM = 10.0;
// 0 km → 1.0, MAX_DISTANCE_KM → 0.0, beyond MAX_DISTANCE_KM → 0.0 (score floor)
return 1.0 - ($distKm / self::MAX_DISTANCE_KM);
```
No bounding-box pre-filter — every Pending item is compared against every other Pending item on
each call (O(n×m), full table scan via Eloquent `::get()`).

### Threshold
`config/matching.php`:
```php
return ['threshold' => (float) env('MATCH_CONFIDENCE_THRESHOLD', 0.80)];
```
Real default is **0.80**, but it is env-configurable (`MATCH_CONFIDENCE_THRESHOLD`), not a literal
hardcoded in the comparison — `MatchingService.php:44,85` reads `config('matching.threshold', 0.80)`
each time. This matches the CLAUDE.md convention.

### What happens when threshold is met
On `$score >= threshold`:
- Creates a `MatchAlert` row (`match_score` stored as a 0–1 float, rounded to 4 decimals).
- Immediately sets **both** items' `status` to `'Matched'` (`updateItemStatus`).
- Sends a Telegram message to the loser via `TelegramService::sendMessage()` **if** the loser's
  user record has a `telegram_chat_id`; otherwise silently skipped (no alert, no error).
- Alerts are deduplicated by checking `MatchAlert::where(lost_item_id, found_item_id)->exists()`
  before creating a new one — a given lost/found pair triggers a match alert at most once, even if
  `matchFoundItem`/`matchLostItem` runs again later.

**Not implemented:** no re-scoring or re-matching once an item is `Matched`; no admin-adjustable
threshold UI (it's `.env`-only, no config table/screen touches `matching.threshold`).

---

## 2. Database Schema

Postgres-only, all in `database/migrations/`. TPT (table-per-type) pattern for `Item`/`FoundItem`/`LostItem`
and `User`/`Finder`/`Loser`.

### `users`
| Column | Type |
|---|---|
| id | bigint PK |
| name | string |
| matric_number | string, unique, nullable |
| telegram_chat_id | string, nullable |
| role | enum('Admin','Security','User') default 'User' |
| password | string |
| timestamps | |

Note: `matric_number` is **nullable** on `users` (a Telegram-only Finder never gets one — see
`BotSubmissionController::store`, which creates a `User` with only `telegram_chat_id`).

### `finders` (TPT child of users)
`user_id` (PK, FK→users.id, cascade delete), `telegram_chat_id` nullable, timestamps.

### `losers` (TPT child of users)
`user_id` (PK, FK→users.id, cascade delete), `matric_number` string **unique, NOT nullable**,
timestamps. (So `losers.matric_number` is required even though `users.matric_number` is nullable —
two separate columns, can drift if not kept in sync manually.)

### `categories`
`id`, `category_name`, `icon_identifier`, timestamps. (10 rows seeded per CLAUDE.md; seeder not
independently audited here.)

### `items` (base table)
| Column | Type |
|---|---|
| id | bigint PK |
| category_id | FK→categories, constrained |
| title_description | string |
| latitude | double |
| longitude | double |
| location_name | string |
| status | enum('Pending','Matched','Claimed') default 'Pending' |
| timestamps | |

Note: `latitude`/`longitude` are `double`, not `float` as CLAUDE.md §9 states ("float for
coordinates"). `ai_tags.confidence_level` and `match_alerts.match_score` are the ones actually
declared `float`.

### `found_items` (TPT child of items)
`item_id` (PK, FK→items.id cascade), `finder_id` (**nullable**, FK→finders.user_id, `nullOnDelete`),
`image_path` string, timestamps.

### `lost_items` (TPT child of items)
`item_id` (PK, FK→items.id cascade), `loser_id` (FK→losers.user_id, cascade, NOT nullable),
`image_path` string nullable (added in a later migration `...000009_add_image_path...`), timestamps.

### `ai_tags`
`id`, `found_item_id` (FK→found_items.item_id, cascade), `tag_name` string, `confidence_level` float,
timestamps. Only Found items get AI tags — Lost items have none (matching compares tags against
free text, per §1).

### `match_alerts`
`id`, `lost_item_id` (FK→**items**.id, not lost_items), `found_item_id` (FK→**items**.id, not
found_items), `match_score` float, `is_notified` boolean default false, timestamps. Both FKs point
at the base `items` table, not the TPT child tables — consistent with how the model relations are
built (`$alert->lostItem`/`$alert->foundItem` resolve to `Item`, then a nested relation resolves to
the TPT child, e.g. `$alert->lostItem->lostItem` in `MatchAlertsController::formatAlert`).

### `reownership_claims` (table name — code has both a `Claim` and `ReownershipClaim` model; only
`ReownershipClaim` is actually used by controllers)
| Column | Type |
|---|---|
| id | bigint PK |
| found_item_id | FK→found_items.item_id, cascade |
| loser_id | FK→losers.user_id, cascade |
| security_guard_id | FK→users.id — **originally NOT NULL**, made nullable by migration `2026_06_13_...` (raw `ALTER TABLE ... DROP NOT NULL` via `DB::statement`, not a Blueprint change) |
| otp_code | string |
| expires_at | timestamp, nullable — added by migration `2026_06_05_085617_...` |
| claimed_at | timestamp, nullable |
| timestamps | |

`security_guard_id` is nullable specifically so a student-initiated OTP (`StudentClaimController::requestOtp`,
no admin/security actor yet) can create a row before a guard confirms it at the desk — matches
CLAUDE.md's description ("null until admin confirms at desk").

### `api_logs`
`id`, `item_id` (FK→items.id, **made nullable** by a later migration `2026_06_05_083112_...`),
`service` string, `http_status_code` int, `payload_response` text (not `jsonb` — CLAUDE.md §9 says
"jsonb for payloads" but the actual column is `text` storing a JSON-encoded string), `logged_at`
timestamp default current, timestamps.

### Discrepancies vs. CLAUDE.md §5/§9
- Coordinates are `double`, not `float`.
- `api_logs.payload_response` is `text`, not `jsonb`.
- There is both a `Claim` model and a `ReownershipClaim` model pointing at the same
  `reownership_claims` table (`app/Models/Claim.php` and `app/Models/ReownershipClaim.php`) — only
  `ReownershipClaim` appears to be wired into controllers (`StudentClaimController`,
  `Admin\MatchAlertsController`, `Admin\DashboardController::destroy`). `Claim.php` looks unused —
  worth checking for dead code if this matters for the report.

---

## 3. API / External Integrations

### Google Cloud Vision API — real integration, native cURL
**File:** `app/Services/VisionService.php`

- Endpoint: `https://vision.googleapis.com/v1/images:annotate`, called with **native PHP `curl_*`**
  functions (`curlPost()`/`curlGet()` helpers), *not* Guzzle/Laravel's `Http` facade — confirmed in
  code (`CURLOPT_SSL_VERIFYPEER => false`, `CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1` forced),
  matching the CLAUDE.md note about Guzzle being unable to reach googleapis.com in the container.
- Request: `LABEL_DETECTION`, `maxResults: 10`, image sent as **base64-encoded content** (not a
  hosted-image URL request) — image bytes are fetched from `Storage::disk('public')` (or via
  `curlGet` if `image_path` is already an absolute URL) and base64-encoded before sending.
- Response parsing: pulls `responses[0].labelAnnotations`, writes one `AiTag` row per label
  (`tag_name` = lowercased `description`, `confidence_level` = `score` rounded to 4 dp).
- Every call — success or failure — writes an `ApiLog` row with `service = 'CloudVisionAPI'`.
- **Falls back to `MockCloudVisionService`** (`app/Services/MockCloudVisionService.php`) if
  `config('services.google.vision_api_key')` (env `GOOGLE_VISION_API_KEY`) is empty. The mock picks
  3–5 random tags from a hardcoded 35-word pool and random confidence 0.75–0.98, then writes an
  identically-shaped `ApiLog` entry (`http_status_code: 200`) — so a downstream reader of `api_logs`
  cannot distinguish a real Vision call from a mocked one except by knowing whether the API key was
  set at the time.

### Google Maps API — **not actually integrated**
- No `google_maps` config key, no Maps SDK, no geocoding HTTP calls anywhere in `app/` or `config/`.
- Frontend uses **Leaflet + OpenStreetMap tiles only** (`@vue-leaflet/vue-leaflet`, `leaflet/dist/leaflet.css`
  imported in `resources/js/app.js`; `LMap`/`LMarker`/`LTileLayer` used in `Dashboard.vue`,
  `Admin/Reports.vue`, `Admin/AdminDashboard.vue`).
- **Flag:** `resources/js/Pages/Admin/AdminDashboard.vue:347-353` has a hardcoded `apiLogs` ref
  array containing fake rows like `{ service: 'Maps', endpoint: 'GET /maps/api/geocode/json', ... }`
  and a hardcoded `matchAlerts` ref array with fake data (`score: 96`, `lostItem: {...}` etc. —
  never populated from the backend or from `defineProps`). These two `ref()`s are **static mock
  data that is never overwritten** — only the `inventory` ref (line 302) is populated from real
  Inertia props (`props.items`, from `Admin\DashboardController::index`). The Match Alerts and API
  Logs *tabs inside `AdminDashboard.vue`* are decorative/non-functional. The real, live equivalents
  are separate pages/routes: `GET /admin/match-alerts` → `Admin\MatchAlertsController::index` →
  `Admin/MatchAlerts.vue`, and `GET /admin/api-logs` → `Admin\ApiLogsController::index` →
  `Admin/ApiLogs.vue`, both backed by real DB queries. If `/admin/dashboard`'s in-page tabs are
  what's being demoed or screenshotted for the report, be aware the Match Alerts/API Logs content
  shown there is not real data.

### Telegram — separate bot service confirmed; Laravel side sends via `Http` facade (not cURL)
- `app/Services/TelegramService.php` builds `https://api.telegram.org/bot{token}/sendMessage` and
  posts with Laravel's `Http::timeout(10)->post(...)` (Guzzle), unlike VisionService. This works
  fine per the codebase (no documented DNS/reachability issue for Telegram's API).
- Every send — success or failure — logs to `api_logs` with `service = 'Telegram'`; call sites can
  pass `redactPayload: true` (used for OTP messages in `StudentClaimController` and
  `Admin\MatchAlertsController::verify`) so the OTP code itself is never persisted in
  `payload_response` — logs `'[REDACTED]'` instead.
- No Telegraf/Node code exists in this repo — confirmed only via the API contract
  (`routes/api.php`: `/bot/submit`, `/bot/update-location`, `/bot/link-account`, all guarded by
  `authorizeBot()` checking header `X-Bot-Secret` against `config('services.bot.secret')`
  / env `LARAVEL_BOT_SECRET`). The bot's actual Node/Telegraf implementation is out of scope of
  this repo and was not verified here.

### No other external integrations
`config/services.php` also declares `postmark`, `resend`, `ses`, `slack` keys — these are Laravel
default-scaffold entries (`slack` notification channel config is Laravel's stock notification
config), **not called anywhere in `app/`** — grep of the codebase found no usage of Slack, mail
providers, or any other third-party API beyond Vision + Telegram.

---

## 4. Auth & User Roles

- **Laravel Breeze** scaffolding (`composer.json`: `laravel/breeze ^2.4`, `laravel/sanctum ^4.0`),
  using the standard session-guard (`Auth::attempt` / cookie session), not Sanctum tokens for the
  web SPA — Sanctum is present as a dependency but no `Sanctum::actingAs`/token-auth code was found
  in the audited controllers; API routes under `Route::middleware('auth')` in `routes/api.php` rely
  on the same session guard (Inertia SPA, same-origin).
- **Matric Number as login credential**, confirmed in
  `app/Http/Requests/Auth/LoginRequest.php:31`: `Auth::attempt($this->only('matric_number', 'password'), ...)`.
  Laravel's default auth guard authenticates against whatever field is passed to `Auth::attempt`,
  so this works because `users.matric_number` is a real column — this is a **non-Breeze-default
  customization** (stock Breeze uses `email`).
- **Roles**: `users.role` enum `('Admin', 'Security', 'User')`, default `'User'`.
- **RBAC enforcement**: `app/Http/Middleware/EnsureUserRole.php` — accepts a comma-separated role
  list as middleware param (e.g. `role:Admin,Security`), 403s if `$user->role` isn't in the list,
  redirects to login if unauthenticated. Confirmed applied to the `admin` route group in
  `routes/web.php` (not individually re-verified per route in this pass, but the middleware exists
  and is the only RBAC mechanism found — no inline `if ($user->role === ...)` checks turned up in
  the controllers read).

---

## 5. OTP / Claim Flow

Two code paths write to `reownership_claims`, both producing 6-digit zero-padded numeric OTPs via
`random_int(0, 999999)` + `str_pad(..., 6, '0', STR_PAD_LEFT)`:

1. **Student-initiated**: `app/Http/Controllers/StudentClaimController.php::requestOtp($item)`
   — student must own the matching `LostItem`, item status must be `'Matched'`, student must have
   a linked `telegram_chat_id`. Looks up the best `MatchAlert` for the item (highest `match_score`),
   upserts a `ReownershipClaim` (`updateOrCreate` keyed on `found_item_id` + `loser_id` +
   `claimed_at = null`, i.e. only one "pending" claim row per found-item/loser pair at a time —
   re-requesting refreshes the same row's OTP/expiry rather than duplicating). `security_guard_id`
   is **not set** here (nullable, filled in later at the desk).
2. **Security/admin-initiated**: `app/Http/Controllers/Admin/MatchAlertsController.php::verify($matchAlert)`
   — same upsert pattern, but sets `security_guard_id = Auth::id()` (the logged-in admin/security
   user) at creation time.

Both paths:
- Set `expires_at = now()->addMinutes(15)` — confirmed **15-minute expiry**, matches CLAUDE.md.
- Deliver the OTP via `TelegramService::sendMessage(..., redactPayload: true)` — Telegram only, no
  email/SMS fallback.
- OTP is plaintext in the `otp_code` column (not hashed) — never logged in plaintext to `api_logs`
  (redacted), but is stored in plaintext in the DB itself.

**Verification**: `Admin\MatchAlertsController::confirmOtp($matchAlert)` — validates
`otp_code` is a required 6-char string, looks up a `ReownershipClaim` matching
`found_item_id` + `otp_code` + `claimed_at IS NULL`, checks `expires_at->isPast()` (expired → 422),
then in a transaction: sets `claimed_at = now()` and both the found and lost `items.status` to
`'Claimed'`. **Note:** it matches only on `found_item_id` + `otp_code`, not also on the loser —
functionally fine since OTPs are random 6-digit codes scoped to one pending claim per found item,
but there's no explicit check that the *security guard* is different from anyone, i.e. any
authenticated Admin/Security user can confirm any pending OTP for any item (this is presumably
intentional — a physical desk process — but worth flagging for a security-oriented report section).

**Single-use**: enforced by `claimed_at` — once claimed, the same claim row is excluded from future
`whereNull('claimed_at')` lookups by `confirmOtp`, and `requestOtp`'s `updateOrCreate` key also
includes `claimed_at: null`, so a new request after a claim creates a **new** row rather than
reusing the claimed one (old OTP is naturally invalidated by being for a different row than what
`confirmOtp` will find, though it's technically still in the DB — no explicit `otp_code` invalidation
beyond `expires_at`/`claimed_at`).

---

## 6. Frontend Stack

- **Vue 3.4** (`package.json`: `"vue": "^3.4.0"`) via **Inertia.js v2** (`@inertiajs/vue3: ^2.0.0`).
  No Blade views beyond the root Inertia layout (`resources/views/app.blade.php`, not separately
  audited here) — this matches CLAUDE.md's "Inertia for web routes, JSON for API routes" rule.
- Tailwind CSS v4 (`@tailwindcss/vite`, `@tailwindcss/forms`).
- Map rendering: `@vue-leaflet/vue-leaflet` (Leaflet/OSM), not Google Maps JS SDK.

### Pages that actually exist (`resources/js/Pages/`)
- `Welcome.vue`
- `Dashboard.vue` — student-facing: gallery, lost-item report form with Leaflet pin, my-reports,
  settings (confirmed by grep, structure not fully re-read line-by-line this pass)
- `Auth/`: `Login.vue`, `Register.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `ConfirmPassword.vue`,
  `VerifyEmail.vue` — stock Breeze auth pages
- `Profile/Edit.vue` + 3 partials — stock Breeze profile pages
- `Admin/AdminLogin.vue`
- `Admin/AdminDashboard.vue` — real for the Inventory tab (backed by `DashboardController::index`);
  **Match Alerts and API Logs tabs inside this page are hardcoded mock data, not wired to any
  controller/props** (see §3 flag above)
- `Admin/MatchAlerts.vue` — real, separate page, backed by `MatchAlertsController::index`
- `Admin/ApiLogs.vue` — real, separate page, backed by `ApiLogsController::index`
- `Admin/Reports.vue`, `Admin/Users.vue` — backed by `ReportsController`, `UsersController`
  (not deep-audited this pass, but routes exist and are wired: `routes/web.php:165-167`)

---

## 7. Hosting / Infra

- `.dokploy/` directory and `docker/nginx-upload.conf` exist in-repo; no `Dockerfile` or
  `docker-compose.yml` was found at the repo root in this pass (Dokploy likely builds via
  Nixpacks, consistent with CLAUDE.md's Nixpacks references — not independently confirmed by a
  Dockerfile in this repo).
- **Deploy script**: `scripts/deploy.sh` — a bash post-deploy hook, not a Dockerfile build step. It:
  - `git fetch` + `git reset --hard origin/main`
  - `composer install --no-dev`
  - Node build (`npm ci && npm run build`), using `.nvmrc`-pinned Node via `nvm`
  - `php artisan storage:link`, `migrate --force`, `db:seed --force`
  - Conditionally runs two custom Artisan commands if they exist:
    `items:fetch-remote-images --limit=200` and `items:fix-extensions` (both guarded by checking
    `php artisan list` output first — so these are optional/best-effort, not guaranteed to exist)
  - Clears/rebuilds Laravel caches
  - Patches `/nginx.conf` in-container with `client_max_body_size 20M` if not already present
    (idempotent `grep` check, confirms CLAUDE.md's upload-limit note)
  - Mounts a Docker named volume `campus-lf-storage` to `/app/storage` via
    `docker service update --mount-add ...` (Swarm), gated on `DOKPLOY_SERVICE_NAME` env var
  - Force-updates the Swarm service (`docker service update --force`) at the end to pick up the new
    image — hardcoded service name `campus-lost-and-found-cxdzy-zpbakj` as a fallback in the final
    step (not gated behind the `DOKPLOY_SERVICE_NAME` var like the mount step is)

- **Queue/scheduler**: **no Laravel Scheduler usage found** — no `Schedule::` calls anywhere in
  `routes/` or `app/`, no `app/Console/Kernel.php` schedule method with entries (not independently
  opened this pass, but grep for `Schedule::` across `routes/`/`app/`/`bootstrap/` returned nothing).
  Queued work (`ProcessVisionTagsJob`) relies entirely on a long-running `php artisan queue:work`
  process, started inline in the same container as `php-fpm` per CLAUDE.md's documented start
  command — this repo does not itself define that start command (it's a Dokploy service
  setting, not a repo file), so it could not be independently verified here.

---

## Summary of flagged issues for the report

1. **`AdminDashboard.vue` Match Alerts / API Logs tabs are non-functional mock UI** — hardcoded
   `ref()` arrays, never populated from the backend. The real data-backed equivalents are the
   separate `/admin/match-alerts` and `/admin/api-logs` routes/pages.
2. **No Google Maps API integration exists** — only Leaflet/OpenStreetMap. Any case-study claim of
   "Google Maps" should be corrected to "Leaflet/OpenStreetMap"; the one place "Maps" appears in
   code is inside the mock API-log data noted in (1), not a real API call.
3. **Matching engine is a substring-heuristic + linear-decay Haversine model**, not ML/embedding-based
   similarity — worth stating precisely if the report claims "AI-based matching" beyond the Vision
   labeling step itself.
4. **Duplicate `Claim`/`ReownershipClaim` models** on the same table — only `ReownershipClaim` is
   used by any controller found in this audit.
5. **Column type mismatches vs. CLAUDE.md**: `items.latitude/longitude` are `double` not `float`;
   `api_logs.payload_response` is `text` not `jsonb`.
6. **`confirmOtp` doesn't scope by which student the OTP was issued to** beyond the OTP code + found
   item — any Admin/Security account can confirm any pending OTP.
