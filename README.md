# Mini CRM

A backend-first Customer Relationship Management JSON API for sales teams, built with Laravel 12 and PHP 8.2+.

Sales reps work **Leads** through a pipeline of statuses, log **Activities** (calls, emails, meetings, notes) against them, and managers get aggregate performance reports across all reps. Authentication is handled via [Laravel Sanctum](https://laravel.com/docs/sanctum) API tokens.

## Tech Stack

| Layer             | Choice                                 |
|-------------------|----------------------------------------|
| Backend framework | Laravel 12                             |
| Language          | PHP 8.2+                               |
| Database          | MySQL 8.4 (via Laravel Sail / Docker)  |
| Auth              | Laravel Sanctum (token-based)          |
| Testing           | Pest / Laravel Feature Tests           |
| Local dev         | Laravel Sail (Docker)                  |

## Setup

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running.
- Git.

### Quick Start

```bash
# 1. Clone the repository
git clone <repo-url> mini-crm && cd mini-crm

# 2. Copy environment file
cp .env.example .env

# 3. Install PHP dependencies via Sail (no local PHP needed)
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs

# 4. Start the containers
./vendor/bin/sail up -d

# 5. Generate application key
./vendor/bin/sail artisan key:generate

# 6. Run database migrations
./vendor/bin/sail artisan migrate

# 7. Verify the app is running
curl http://localhost/up
```

### Stopping

```bash
./vendor/bin/sail down
```

### Running Tests

```bash
./vendor/bin/sail artisan test --compact
```

## Assumptions

- No public user registration — users are seeded.
- No file uploads / attachments on activities.
- No real email/SMS notifications — queued jobs just log the action.
- Multi-tenancy is documented as a design note, not implemented.

## Trade-offs

_(to be filled in during build)_

## What I'd Do With More Time

_(to be filled in during final review)_

## Build Log

### Phase 0 — Bootstrap

**Goal:** Set up the project infrastructure — Laravel install, Docker dev environment, and API auth scaffolding.

**What was done:**

- Initialized a fresh Laravel 12 project (PHP 8.2+).
- Installed and configured **Laravel Sail** with a MySQL 8.4 service (`docker-compose.yml`).
- Installed **Laravel Sanctum v4** for API token authentication.
  - Published Sanctum config and migration (`personal_access_tokens` table).
  - Added `HasApiTokens` trait to the `User` model.
  - Configured `statefulApi()` middleware in `bootstrap/app.php`.
  - Registered `api.php` routes file (with default `/api/user` Sanctum-guarded route).
- Set up `.env.example` with Sail-oriented defaults (DB name: `mini_crm`, DB host: `mysql`, user: `sail`).
- Created a proper `.gitignore` for Laravel (includes `docker-compose.override.yml`).
- Default Laravel migrations present: `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `personal_access_tokens`.

**No CRM-specific models, migrations, or logic were added in this phase.**

### Phase 1 — Data Modelling

**Goal:** Define the database schema, Eloquent models, relationships, and factories — no API endpoints yet.

**Schema:**

| Table | Key columns | Notes |
|---|---|---|
| `users` | `role` (string, default `rep`) | Added to existing migration. Cast to `UserRole` enum. |
| `leads` | `name`, `email`, `phone`, `company` (nullable), `source`, `status` (default `new`), `expected_value` (decimal 12,2), `assigned_to` (nullable FK → users) | Three individual indexes on `status`, `source`, `assigned_to`. |
| `activities` | `lead_id` (FK, cascade delete), `user_id` (FK), `type`, `body` (text), `occurred_at` | Composite index on `(lead_id, occurred_at)`. |

**Design decisions:**

- **`decimal(12,2)` not `float`** for `expected_value` — floats cause rounding errors in currency arithmetic (e.g. `0.1 + 0.2 ≠ 0.3`). `decimal` is stored as an exact fixed-point value in MySQL.
- **String columns + PHP enum casts** instead of MySQL `ENUM` type — MySQL `ENUM` requires a migration to add new values. Storing as `string` with a PHP backed enum (`LeadStatus`, `LeadSource`, `ActivityType`, `UserRole`) keeps validation in the application layer and makes schema changes non-destructive.
- **`nullOnDelete`** on `assigned_to` — if a rep user is deleted, their leads become unassigned rather than deleted.
- **`cascadeOnDelete`** on `activities.lead_id` — if a lead is removed, its activities are meaningless and should be cleaned up.
- **Composite index `(lead_id, occurred_at)`** — optimizes the most common query pattern: fetching a lead's activity timeline in chronological order.
- **Individual indexes** on `status`, `source`, `assigned_to` — these columns are used heavily for filtering (`GET /api/leads?status=...`) and for the rep-performance aggregate report.
- **Lead email is not unique** — the same person can appear as multiple leads from different sources/campaigns. Uniqueness is a business decision, not a data constraint here.

