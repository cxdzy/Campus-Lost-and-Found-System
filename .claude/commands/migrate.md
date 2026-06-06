---
description: Generate a Laravel Postgres migration from a plain-English description
argument-hint: [describe the table or schema change you need]
---

## Migration Request

$ARGUMENTS

---

Generate a Laravel migration for the above.

Rules to follow:
- Database is **PostgreSQL** — use Postgres-compatible column types only
- Reference the ERD in CLAUDE.md to stay consistent with existing table names and foreign keys
- Use `foreignId()->constrained()` with explicit table names where needed
- For status fields, use a string column with a PHP enum or a DB check constraint — not MySQL ENUM
- For location data, use `float` for `latitude` and `longitude` unless PostGIS is explicitly requested
- For AI tag confidence scores, use `float` (0.0–1.0 scale)
- Add appropriate indexes for any column used in WHERE clauses or JOINs
- Include `timestamps()` unless there is a specific reason not to
- If adding to an existing table, generate an `add_` migration, not a full rebuild

After the migration, also generate:
1. The corresponding **Eloquent model** (relationships, fillable, casts)
2. A short **factory** stub for testing

Name the migration file following Laravel convention: `create_<table>_table` or `add_<column>_to_<table>_table`.
