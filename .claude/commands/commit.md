---
description: Generate a conventional commit message based on staged changes
allowed-tools: Bash(git diff:*), Bash(git status:*)
---

## Commit Message Generator

**Staged changes:**
!`git diff --cached`

**Files staged:**
!`git status --short`

---

Write a **conventional commit** message for the above changes.

Format:
```
<type>(<scope>): <short summary>

<body — what changed and why, 2–4 sentences>

<footer — breaking changes or issue refs if relevant>
```

Types: `feat`, `fix`, `refactor`, `chore`, `docs`, `test`, `perf`, `ci`

Scopes for this project: `finder`, `loser`, `admin`, `matching`, `vision`, `telegram`, `auth`, `otp`, `db`, `queue`, `api-logs`, `ui`

Rules:
- Summary line max 72 characters, imperative mood ("add", "fix", "update" — not "added")
- Body explains *why*, not just *what* (the diff already shows what)
- If this involves a migration, mention the affected table
- If this involves OpenCV or Telegram, name the service
- Do not mention Google Maps — it is not used in this project

Give me one primary suggestion and one alternative if the scope is ambiguous.
