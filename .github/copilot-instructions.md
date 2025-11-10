## Quick orientation

This repository is a small PHP service focused on tracking programs, subdomains, live hosts and HTTP fingerprints in a Postgres database. The entry point is `index.php` (CLI), which currently bootstraps database tables via `db/db.php`.

Core facts an AI coder should know up front:
- PHP runtime: modern PHP (typed properties and nullable types used) — assume PHP 8+.
- Dependency management: `composer.json` (uses `vlucas/phpdotenv`). Run `composer install` to get `vendor/autoload.php`.
- Environment: runtime configuration is in the repo root `.env` (DB_*, WEBHOOK_URL, DEBUG).

## Big-picture architecture
- Entry/boot: `index.php` -> requires `db/db.php` -> `Database::createTables()`.
- DB layer: `db/db.php` contains the `Database` class (PDO to Postgres). Table SQL lives in `db/tables/*.php` and are returned as strings.
- Services: `db/services/` is the intended place for data access/service helpers (currently mostly stubs). Follow that layout when adding business logic around the database.
- Tools: utility scripts live in `tools/` (example: `tools/send_discord_message.php`). These are lightweight helpers invoked by other processes or CLI tasks.

## Project-specific patterns & conventions (important to follow)
- Table creation: each table SQL is a PHP file under `db/tables/` that returns the SQL string (see `db/tables/program.php`). `Database::createTables()` currently does `require_once` for those files and passes the returned SQL string to `$db->exec(...)`.
  - When adding a new table: add `db/tables/your_table.php` returning the SQL string, and add a corresponding `require_once` + `exec` line in `Database::createTables()`.
- Database connection: `db/db.php` uses `Dotenv\Dotenv` to load `.env` and constructs a PDO connection to Postgres. Use `$_ENV['DB_*']` values.
- Minimal namespacing / style: the codebase is small and currently uses simple global classes/files (no strict PSR-4 layout). New code may use classes but prefer to keep file placement consistent with the existing layout (e.g., DB utilities in `db/`, scripts in `tools/`).
- Return values: small helper scripts (like `tools/send_discord_message.php`) return booleans or die on missing env; follow the current simple error handling style when changing behavior unless you intentionally add a different error-handling contract.

## Important files to inspect when working here
- `index.php` — CLI entry used to bootstrap the DB tables.
- `db/db.php` — Database connector and `createTables()` function.
- `db/tables/*.php` — Table SQL definitions (each returns a SQL string).
- `db/services/*` — Place to implement DB-related service logic (data access layer).
- `tools/send_discord_message.php` — Example of a small utility that reads `WEBHOOK_URL` from `.env`.
- `.env` — Project environment variables (secrets are present here in this workspace copy).
- `composer.json` and `vendor/` — dependency management and autoload.

## How to run common tasks (concrete)
- Install deps: run composer install so `vendor/autoload.php` is available.
- Create/ensure tables: run the entry script via CLI (from repo root): `php index.php` — this calls `Database::createTables()` which loads `db/tables/*.php` and executes the SQL.

## What to avoid / watchouts
- Do not assume a web framework or routing exists — this repo is not a full web app. `index.php` is a tiny bootstrap for DB setup.
- There are no tests or CI config present; changes to DB schemas should be applied carefully and follow the existing pattern for table SQL files.
- Some files in `db/services/` are empty stubs. Prefer adding service functionality into that folder instead of scattering DB SQL across unrelated files.

## Example edits (copy-paste friendly)
- Add a table: create `db/tables/new_table.php` that returns a SQL string, then update `Database::createTables()` in `db/db.php`:

```php
// in db/db.php
$db->exec(require_once __DIR__ . '/tables/new_table.php');
```

## When you need more context
- Read `db/db.php` and the files in `db/tables/` first — they explain the schema and connection details.
- If making changes that integrate with external services (e.g. webhooks), inspect `tools/send_discord_message.php` for the existing conventions.

If any section is unclear or you'd like concrete examples for tests or CI integration, tell me which area to expand and I will update this file.
