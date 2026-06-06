---
description: Set a goal/feature to build — Claude will plan first, then wait for approval before coding
argument-hint: [describe what you want to build or fix]
---

## Goal

$ARGUMENTS

---

Before writing any code, follow these steps:

1. **Re-read CLAUDE.md** to confirm the tech stack, ERD, and conventions.

2. **Restate the goal** in your own words so I can confirm you understood it correctly.

3. **Identify what is affected:**
   - Which modules (Finder, Loser/User, Admin & Security)?
   - Which database tables from the ERD?
   - Which service classes (TelegramService, VisionService)?
   - Any queue jobs needed?

4. **Write a short plan:**
   - Files to create or edit
   - Migrations needed (Postgres)
   - Routes and controllers
   - Any environment variables to add in `.env` / Dokploy

5. **Stop and wait for my approval** before writing any code.

Constraints to always respect:
- Database is PostgreSQL (Dokploy service) — no MySQL syntax
- No Google Maps API — GPS is raw lat/long, map UI uses Leaflet/OSM
- OpenCV handles vision tagging only
- 80% confidence threshold must stay configurable, not hard-coded
- Secrets go in `.env` and Dokploy environment settings, never in code
- RBAC: admin/security routes must be gated by role middleware
- OTPs must be single-use and time-limited
