---
description: Generate a technical implementation plan for a specific feature without writing code yet
argument-hint: [feature or task to plan]
---

## Plan Request

$ARGUMENTS

---

Generate a detailed technical plan for the above. Do NOT write any code yet.

Structure the plan as follows:

### 1. Summary
One paragraph explaining what this feature does and why it is needed.

### 2. Affected Files
List every file that needs to be created or modified:
- Migrations
- Models (Eloquent)
- Controllers / Form Requests
- Service classes
- Jobs / Queue workers
- Routes (`api.php` / `web.php`)
- Vue components (if frontend)
- Tests

### 3. Database Changes
Describe any schema changes in plain English, referencing the ERD tables in CLAUDE.md.
Note any Postgres-specific types (e.g. `jsonb`, `enum`, PostGIS if needed).

### 4. API / External Integration
If OpenCV or Telegram Bot are involved, describe the flow:
- What triggers the call?
- What gets logged to `API_LOGS`?
- Which queue job handles it?

### 5. Potential Risks or Edge Cases
List anything that could go wrong — race conditions, missing env vars, OTP expiry, etc.

### 6. Open Questions
List anything you are unsure about and need me to clarify before proceeding.

---
Wait for my confirmation before implementing anything.
