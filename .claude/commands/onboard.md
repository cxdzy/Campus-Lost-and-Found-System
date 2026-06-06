---
description: Orient yourself to the existing codebase before starting any work — run this at the start of every session
allowed-tools: Read, Glob, Grep, Bash(find:*), Bash(php artisan:*)
---

## Codebase Orientation

Do NOT write or edit any code. This is a read-only exploration session.

Work through these steps in order:

### Step 1 — Project Structure
Read the directory tree to understand what exists:
- Top-level folder structure
- `app/` — list all subdirectories (Http, Models, Services, Jobs, etc.)
- `database/migrations/` — list all migration files in order
- `routes/` — read `api.php` and `web.php`
- `resources/` — check what Vue components exist

### Step 2 — Read CLAUDE.md
Read the full `CLAUDE.md` file. Note:
- The ERD (Section 5) — which tables exist vs which are missing
- Current State (Section 10) — what is done vs pending
- Conventions (Section 7) — rules you must follow

### Step 3 — Understand Existing Patterns
Find and read one example of each of the following that already exists in the project:
- A Model (Eloquent)
- A Controller
- A Service class (e.g. TelegramService or VisionService)
- A Queue Job
- A Migration
- A Form Request (if any)

Note their namespaces, imports, and structure — new code must match these patterns exactly.

### Step 4 — Check Environment
- Read `.env.example` to see what environment variables are expected
- Run `php artisan route:list` to see what routes already exist
- Run `php artisan migrate:status` to see which migrations have run

### Step 5 — Report Back

After exploring, give me a summary in this format:

**What is built:**
- (list models, services, controllers that exist)

**What is missing based on CLAUDE.md:**
- (list things in the ERD or modules that have no corresponding file yet)

**Patterns I will follow:**
- (describe the code style from existing files)

**Questions before I start:**
- (anything unclear or conflicting between the code and CLAUDE.md)

---
Do not suggest or write any code until I give you a task after reading your summary.
