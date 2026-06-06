---
description: Review staged or recent changes for quality, security, and project conventions
allowed-tools: Bash(git diff:*), Bash(git status:*)
---

## Code Review

Reviewing current changes:

**Staged changes:**
!`git diff --cached`

**Unstaged changes:**
!`git diff`

**Modified files:**
!`git status --short`

---

Review the above diff against the project standards in CLAUDE.md. Check for:

### Correctness
- Does the logic match what was intended?
- Are Eloquent relationships used correctly?
- Are queue jobs dispatched where slow work (OpenCV, Telegram) is involved?

### Security
- Are all inputs validated via Form Requests?
- Are admin/security routes protected by role middleware (RBAC)?
- Are OTP codes single-use and time-limited?
- Are secrets referenced from `env()` only — never hard-coded?

### Database (Postgres)
- Is the migration Postgres-compatible? (no MySQL-only syntax)
- Are column types appropriate? (`jsonb` where useful, correct enum handling)
- Are foreign keys and indexes defined?

### Project Conventions
- Does code follow the ERD table structure in CLAUDE.md?
- Is the 80% confidence threshold read from config, not hard-coded?
- Are API calls logged to `API_LOGS` with service, status code, and timestamp?
- Is there no reference to Google Maps API?

### Suggestions
Give specific, actionable feedback. Note the file and line if possible.
Separate must-fix issues from nice-to-have improvements.
