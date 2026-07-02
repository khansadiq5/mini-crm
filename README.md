# Mini CRM

A backend-first Customer Relationship Management JSON API for sales teams, built with Laravel 12 and PHP 8.2+.

Sales reps work **Leads** through a pipeline of statuses (`new → contacted → qualified → won/lost`), log **Activities** (calls, emails, meetings, notes) against them, and managers get aggregate performance reports across all reps. Authentication is handled via [Laravel Sanctum](https://laravel.com/docs/sanctum) API tokens.

## Tech Stack

| Layer             | Choice                                |
|-------------------|---------------------------------------|
| Backend framework | Laravel 12                            |
| Language          | PHP 8.2+                              |
| Database          | MySQL 8.4 (via Laravel Sail / Docker) |
| Auth              | Laravel Sanctum v4 (token-based)      |
| Queue             | Database driver (`jobs` table)        |
| Testing           | Pest v3 / Laravel Feature Tests       |
| Local dev         | Laravel Sail (Docker)                 |

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

# 6. Run database migrations and seed data
./vendor/bin/sail artisan migrate:fresh --seed

# 7. Verify the app is running
curl http://localhost/up
```

### Seeded Credentials

| User            | Email                  | Password   | Role    |
|-----------------|------------------------|------------|---------|
| Manager         | `manager@minicrm.test` | `password` | manager |
| Sales Rep 1     | `rep1@minicrm.test`    | `password` | rep     |
| Sales Rep 2     | `rep2@minicrm.test`    | `password` | rep     |
| Sales Rep 3     | `rep3@minicrm.test`    | `password` | rep     |

Additionally, ~25 Leads are distributed randomly among the representatives with realistic expected values ($1K–$75K), and various activities (calls, emails, meetings, notes) are seeded against them.

### Stopping

```bash
./vendor/bin/sail down
```

## Running Tests

```bash
# Via Sail
./vendor/bin/sail artisan test --compact

# Or locally (if PHP 8.2+ and MySQL are available)
php artisan test --compact
```

**Current status: 45 tests passing, 139 assertions.**

## API Endpoints

All endpoints (except login) require an `Authorization: Bearer <token>` header.

| Method   | URI                             | Auth           | Description                                    |
|----------|---------------------------------|----------------|------------------------------------------------|
| `POST`   | `/api/login`                    | Public         | Authenticate, returns Sanctum token            |
| `POST`   | `/api/logout`                   | `auth:sanctum` | Revoke the current token                       |
| `GET`    | `/api/me`                       | `auth:sanctum` | Return the authenticated user                  |
| `GET`    | `/api/leads`                    | `auth:sanctum` | List leads (filter, search, sort, pagination)  |
| `POST`   | `/api/leads`                    | `auth:sanctum` | Create a new lead                              |
| `GET`    | `/api/leads/{lead}`             | `auth:sanctum` | View lead with activities & assigned rep       |
| `PATCH`  | `/api/leads/{lead}`             | `auth:sanctum` | Update a lead (enforces won/lost rule)         |
| `POST`   | `/api/leads/{lead}/assign`      | `auth:sanctum` | Assign lead to a rep (Manager only)            |
| `POST`   | `/api/leads/{lead}/activities`  | `auth:sanctum` | Log an activity against a lead                 |
| `GET`    | `/api/reports/rep-performance`  | `auth:sanctum` | Aggregated rep performance metrics             |

### Query Parameters for `GET /api/leads`

| Parameter     | Example                 | Description                             |
|---------------|-------------------------|-----------------------------------------|
| `status`      | `?status=new`           | Filter by lead status enum              |
| `source`      | `?source=referral`      | Filter by lead source enum              |
| `assigned_to` | `?assigned_to=3`        | Filter by assigned rep ID               |
| `search`      | `?search=google`        | Full-text search on name, email, company|
| `sort`        | `?sort=expected_value`  | Sort column (default: `created_at`)     |
| `direction`   | `?direction=asc`        | Sort direction (default: `desc`)        |
| `per_page`    | `?per_page=25`          | Pagination size (max 100, default 15)   |

## Assumptions

- **No public registration** — users are seeded via `DatabaseSeeder`. In production, an admin panel or CLI command would manage user creation.
- **No file uploads / attachments** on activities — activities are text-only records.
- **No real email/SMS notifications** — queued jobs just log the action via Laravel's logger. The job infrastructure is in place for when real notification channels are added.
- **Multi-tenancy** is not implemented — documented as a design note. The current architecture could support it via a `team_id` column on users/leads with a global scope.
- **Sanctum token mode** (not SPA cookie mode) is used for the JSON API. `statefulApi()` middleware is enabled for potential future Blade UI session auth.
- **Lead email is not unique** — the same contact can exist as multiple leads from different sources/campaigns.
- **Enum columns use `string` storage** (not MySQL `ENUM`) — avoids destructive migrations when adding new enum values. Validation is done at the application layer via PHP backed enums.
- **Phone is free-form string** — no regex validation at schema level; format is enforced by convention (E.164 in factories).

## One Deliberate Trade-off

**403 Forbidden vs 404 Not Found for unauthorized lead access.**

When a rep tries to access a lead assigned to another rep, the system returns `403 Forbidden` instead of `404 Not Found`. A `404` would hide the lead's existence entirely (more secure in adversarial contexts), but `403` was chosen because:

1. **Clarity for API consumers** — a `403` clearly tells the client "the resource exists but you lack permission," which is more debuggable than a `404` that could mean either "doesn't exist" or "you can't see it."
2. **Aligns with Laravel's policy system** — `Gate::authorize()` naturally throws `AuthorizationException` → `403`. Forcing a `404` would require extra code to intercept and re-throw, adding complexity without proportional benefit in a trusted, token-authenticated internal CRM.
3. **Internal tool context** — this is a sales team CRM, not a public-facing API. The attack surface where information leakage matters (e.g., user enumeration) is minimal compared to a public-facing system.

## Bonus Features

### Phase 11 — Queued Job on Lead Assignment (Option A)

**Goal:** Dispatch a `NotifyRepOfLeadAssignment` queued job whenever a lead is assigned or reassigned to a rep, using the database queue driver.

**What was done:**

- Created `App\Jobs\NotifyRepOfLeadAssignment` — a queued job (`ShouldQueue`) that accepts `repId` and `leadId` via constructor property promotion.
- The job's `handle()` method logs `"Rep {id} notified about lead {id}"` via Laravel's logger. No real email/notification is sent — this is a placeholder for future notification channels.
- The job includes exponential backoff (`[1, 5, 10]` seconds) and a `failed()` handler for production resilience per queue best practices.
- Modified `LeadController@assign` to dispatch the job after updating the lead's `assigned_to` field. This covers both initial assignments and reassignments.
- The `.env` file already configures `QUEUE_CONNECTION=database`, and the `jobs` table migration was already present from the Laravel bootstrap.

**Why Option A over B or C:**

- **Over Option B (Event + Listener):** Option A introduces the queue subsystem — a production-critical infrastructure concern — while Option B only exercises the event/listener pattern which is simpler to add later. Queued jobs are harder to retrofit correctly (backoff, failure handling, `retry_after` tuning) and worth establishing the pattern early.
- **Over Option C (Cache with Invalidation):** Caching is a performance optimization that only makes sense under measured load. Adding cache invalidation prematurely risks serving stale data without a demonstrated performance need. Option A provides immediate behavioral value (asynchronous notifications) that directly supports the business use case.

**Tests (4 passing):** Job dispatched on assignment, job dispatched on reassignment, job NOT dispatched on validation failure, job handler logs the expected message.

---

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

### Phase 2 — Auth

**Goal:** Implement Sanctum-based API token authentication.

**Endpoints:**

| Method | URI | Auth | Description |
|---|---|---|---|
| `POST` | `/api/login` | Public | Accepts `email` + `password`, returns Sanctum token + user info |
| `POST` | `/api/logout` | `auth:sanctum` | Revokes the current API token |
| `GET` | `/api/me` | `auth:sanctum` | Returns the authenticated user |

#### Auth API Snapshot
![Successful Login Response](https://github.com/khansadiq5/mini-crm/blob/main/public/Screenshots/Manager%20Login.png)

**Implementation details:**

- `LoginRequest` Form Request class handles validation (not inline `validate()`).
- Invalid credentials throw a `ValidationException` → 422 with `"The provided credentials are incorrect."` on the `email` field.
- Token name is `api-token` — each login creates a new token (user can have multiple active sessions).
- `AuthController` lives under `App\Http\Controllers\Api` namespace for clear API separation.

**Tests (7 passing):** successful login, wrong password → 422, non-existent email → 422, missing fields → 422, /me returns user, unauthenticated → 401, logout revokes token.

### Phase 3 — Authorization

**Goal:** Centralize lead visibility and permission rules in a `LeadPolicy` — no controller logic yet.

**Visibility model (from PROJECT_SCOPE.md §3):**

| Action | Manager | Rep |
|---|---|---|
| `viewAny` | ✅ all leads | ✅ (query scoped later) |
| `view` | ✅ any lead | ✅ only if `assigned_to === user.id` |
| `create` | ✅ | ✅ |
| `update` | ✅ any lead | ✅ only if `assigned_to === user.id` |
| `assign` | ✅ | ❌ |

**Why a Policy instead of controller if-checks:**

- **Single source of truth** — the same rules apply whether authorization is checked in a controller, a Blade view, a queue job, or a console command. No risk of copy-paste drift.
- **Laravel convention** — `$this->authorize('update', $lead)` in controllers is one line. The policy is auto-discovered by model name (`Lead` → `LeadPolicy`).
- **Testable in isolation** — the 11 policy tests exercise `$user->can()` directly without HTTP overhead, making them fast and focused.

**Tests (11 passing):** viewAny (manager + rep), view (manager any, rep own, rep denied other, rep denied unassigned), create (both roles), update (manager any, rep own, rep denied), assign (manager allowed, rep denied).

### Phase 4 — Leads CRUD

**Goal:** Implement Leads API endpoints with scoping, filtering, searching, sorting, pagination, and business validation rules.

**Design & Authorization Decisions:**

- **403 vs 404 Decision:** Explicit `403` response for unauthorized access (see "One Deliberate Trade-off" above).
- **Query Scoping:** `when($user->isRep(), ...)` ensures reps only fetch their own leads at the database level.
- **Won/Lost Constraint:** Updating status to `won` or `lost` requires at least one logged activity, otherwise rejected with `422`.

#### Validation Policy (422 Error)
![Lead Status Validation Error](https://github.com/khansadiq5/mini-crm/blob/main/public/Screenshots/Status%20Policy.png)

- **JSON Resources:** Responses transformed via `LeadResource`, `UserResource`, and `ActivityResource` for encapsulation and ISO 8601 timestamps.

**Tests (11 passing in this module, 31 total):** Rep isolation (403), manager access (200), pagination, search, filtering, sorting, validated creations, won/lost constraints.

### Phase 5 — Assign & Activities

**Goal:** Implement assigning leads to reps and logging activities against leads.

**Design & Validation Decisions:**

- **Assign Policy and Validation:** Authorized only for managers via `LeadPolicy@assign`. `AssignLeadRequest` validates that the `rep_id` exists and has the role of `rep`.
- **Log Activity Policy and Validation:** Anyone with read access to the lead can log activities. `StoreActivityRequest` validates `type` (against `ActivityType` enum), `body` (string), and `occurred_at` (datetime).
- **Created Activity Payload:** The authenticated user's ID is automatically stored as `user_id`. Returns `201 Created`.

**Tests (6 passing in this module, 37 total):** Rep rejection on assign (403), manager assignment, validation failure for non-reps, rep activity logging, rep rejection on other leads, activity + won/lost integration.

### Phase 6 — Report

**Goal:** Implement an efficient, N+1 free, Cartesian-product free sales representative performance report.

**Design & Performance Decisions:**

- **Solving Cartesian Duplication:** Aggregates inside isolated subqueries (`lead_stats` and `activity_stats` grouped by rep), then `LEFT JOIN` onto `users`.
- **Role Scoping:** Managers see all reps; reps see only their own stats.

#### Rep Performance Report
![Rep Performance Report Response](https://github.com/khansadiq5/mini-crm/blob/main/public/Screenshots/Rep%20Performance.png)

- **Query Complexity O(1):** Aggregation happens database-side in a single execution. Verified via query log assertions in tests.

**Tests (3 passing in this module, 40 total):** Accurate summation/grouping, role scoping, O(1) query complexity.

### Phase 7 — API Consistency

**Goal:** Standardize response envelopes and configure clean JSON error formats.

- Refactored `AuthController` to use `LoginResource`, `MessageResource`, and `UserResource`.
- Modified `bootstrap/app.php` with `shouldRenderJsonWhen` so `api/*` routes always return JSON errors.

**Tests (1 passing in this module, 41 total):** JSON error format on 404 without Accept header.

### Phase 8 — Testing

**Goal:** Verify the full Pest test suite covers the whole project scope.

**Coverage:** Authentication, lead visibility policy, leads CRUD, assignments & activities, report endpoint — all assertions validated.

### Phase 9 — Seed Data

**Goal:** Create a reproducible dataset conforming to business constraints.

- **Users:** 1 manager + 3 reps.
- **Leads:** 25 leads with randomized statuses/sources, realistic expected values, ~80% assigned.
- **Activities:** 1–4 activities for 60% of leads; guaranteed activities on `won`/`lost` leads.

### Phase 11 — Queued Job (Bonus)

See [Bonus Features](#bonus-features) above.

### Phase 12 — Final Polish

**Goal:** Final cleanup, README reorganization, and sanity checks.

- Reorganized README into: Overview, Tech Stack, Setup, Running Tests, API Endpoints, Assumptions, Trade-off, What I'd Do With More Time, Bonus Features, Build Log.
- Updated `PROJECT_SCOPE.md` §8 (Assumptions) and §9 (Trade-offs) with final content.
- Full code audit: zero dead code, zero unused imports, zero leftover TODOs.
- Verified `php artisan route:list --path=api` matches PROJECT_SCOPE.md §4 exactly (see appendix).
- Final test run: **45 tests passing, 139 assertions.**

---

## Appendix: Route List Sanity Check

Output of `php artisan route:list --path=api` (10 routes):

```
  GET|HEAD   api/leads .......................... leads.index › Api\LeadController@index
  POST       api/leads .......................... leads.store › Api\LeadController@store
  GET|HEAD   api/leads/{lead} .................... leads.show › Api\LeadController@show
  PATCH      api/leads/{lead} ................. leads.update › Api\LeadController@update
  POST       api/leads/{lead}/activities  leads.activities › Api\LeadController@logActivity
  POST       api/leads/{lead}/assign ......... leads.assign › Api\LeadController@assign
  POST       api/login .......................... api.login › Api\AuthController@login
  POST       api/logout ........................ api.logout › Api\AuthController@logout
  GET|HEAD   api/me .................................. api.me › Api\AuthController@me
  GET|HEAD   api/reports/rep-performance  reports.rep-performance › Api\ReportController@repPerformance
```

This matches PROJECT_SCOPE.md §4 exactly:
1. ✅ `POST /api/login`
2. ✅ `GET /api/leads` (filter, search, sort, pagination)
3. ✅ `POST /api/leads`
4. ✅ `GET /api/leads/{id}` (with activities + assigned rep)
5. ✅ `PATCH /api/leads/{id}`
6. ✅ `POST /api/leads/{id}/assign` (manager only)
7. ✅ `POST /api/leads/{id}/activities`
8. ✅ `GET /api/reports/rep-performance` (single aggregate query, scoped by role)

Plus auth support routes: `POST /api/logout`, `GET /api/me`.
