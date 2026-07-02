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

# 6. Run database migrations and seed data
./vendor/bin/sail artisan migrate:fresh --seed

# 7. Verify the app is running
curl http://localhost/up
```

### Seeded Credentials

The database seeder generates initial users and mock records for testing:

* **Manager User:**
  * Email: `manager@minicrm.test`
  * Password: `password`
* **Sales Representatives:**
  * `rep1@minicrm.test` (Password: `password`)
  * `rep2@minicrm.test` (Password: `password`)
  * `rep3@minicrm.test` (Password: `password`)

Additionally, ~25 Leads are distributed randomly among the representatives with realistic expected values, and various activities (calls, emails, meetings, notes) are seeded against them.

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

### Phase 2 — Auth

**Goal:** Implement Sanctum-based API token authentication.

**Endpoints:**

| Method | URI | Auth | Description |
|---|---|---|---|
| `POST` | `/api/login` | Public | Accepts `email` + `password`, returns Sanctum token + user info |
| `POST` | `/api/logout` | `auth:sanctum` | Revokes the current API token |
| `GET` | `/api/me` | `auth:sanctum` | Returns the authenticated user |

**Implementation details:**

- `LoginRequest` Form Request class handles validation (not inline `validate()`).
- Invalid credentials throw a `ValidationException` → 422 with `"The provided credentials are incorrect."` on the `email` field.
- Token name is `api-token` — each login creates a new token (user can have multiple active sessions).
- `AuthController` lives under `App\Http\Controllers\Api` namespace for clear API separation.

**Example curl commands:**

```bash
# Login
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'

# Use the token
curl http://localhost/api/me \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Logout
curl -X POST http://localhost/api/logout \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

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

**Endpoints:**

| Method | URI | Auth | Description |
|---|---|---|---|
| `GET` | `/api/leads` | `auth:sanctum` | List/Filter/Search leads (scoped to rep if not manager) |
| `POST` | `/api/leads` | `auth:sanctum` | Create a lead |
| `GET` | `/api/leads/{lead}` | `auth:sanctum` | View a single lead (eager loads activities & assignedRep) |
| `PATCH` | `/api/leads/{lead}` | `auth:sanctum` | Update a lead (enforces Won/Lost activity constraint) |

**Design & Authorization Decisions:**

- **403 vs 404 Decision:** When a rep attempts to access a lead assigned to another rep, the system returns a `403 Forbidden` response. While a `404 Not Found` can be used to completely hide existence, the explicit `403` response is chosen because it accurately represents the authorization boundary, aligns naturally with Laravel's standard policy exception handling, and differentiates a resource-permission failure from a truly missing resource.
- **Query Scoping:** For the list endpoint, query-level scoping `when($user->isRep(), fn($q) => $q->where('assigned_to', $user->id))` ensures reps only fetch their own leads at the database level, preventing memory overhead or N+1 queries.
- **Won/Lost Constraint:** Updating status to `won` or `lost` checks `$lead->activities()->count() === 0`. If no activities are present, the request is rejected with `422 Unprocessable Content` to enforce logging prior interaction.
- **JSON Resources:** Responses are transformed via `LeadResource`, `UserResource`, and `ActivityResource` to maintain encapsulation, avoid leaking internal attributes (like password hashes), and normalize timestamps to ISO 8601 format.

**Example curl commands:**

```bash
# List Leads (with pagination, status filter, search, and sorting)
curl -X GET "http://localhost/api/leads?status=new&search=google&sort=expected_value&direction=desc&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create a Lead
curl -X POST http://localhost/api/leads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name": "Bruce Wayne", "email": "bruce@waynecorp.com", "phone": "+15550199", "source": "referral", "expected_value": 50000.00}'

# View a Lead (eager loads activities and assigned rep)
curl -X GET http://localhost/api/leads/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Update a Lead (PATCH)
curl -X PATCH http://localhost/api/leads/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"company": "Wayne Enterprises", "status": "contacted"}'
```

**Tests (11 passing in this module, 31 total):** Correctly checks rep isolation (403), manager access (200), pagination structures, search on multi-column whereAny, filtering, multi-direction sorting, validated creations, and won/lost constraints.

### Phase 5 — Assign & Activities

**Goal:** Implement assigning leads to reps and logging activities against leads.

**Endpoints:**

| Method | URI | Auth | Description |
|---|---|---|---|
| `POST` | `/api/leads/{lead}/assign` | `auth:sanctum` | Assign lead to a representative (Manager only) |
| `POST` | `/api/leads/{lead}/activities` | `auth:sanctum` | Log an activity (call, email, meeting, note) against a lead |

**Design & Validation Decisions:**

- **Assign Policy and Validation:** Authorized only for managers via `LeadPolicy@assign`. `AssignLeadRequest` validates that the `rep_id` is an integer, exists in the `users` table, and specifically has the role of `rep` (cannot assign leads to managers).
- **Log Activity Policy and Validation:** Anyone who has read access to the lead (Managers, or the assigned Rep) is allowed to log activities against it. `StoreActivityRequest` validates `type` (against `ActivityType` enum), `body` (string), and `occurred_at` (valid datetime string).
- **Created Activity Payload:** The authenticated user's ID is automatically stored as `user_id` when creating the activity. The endpoint returns `201 Created` with the loaded `ActivityResource` including user metadata.

**Example curl commands:**

```bash
# Assign Lead (Manager only)
curl -X POST http://localhost/api/leads/1/assign \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"rep_id": 2}'

# Log Activity against Lead
curl -X POST http://localhost/api/leads/1/activities \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "call", "body": "Spoke to lead, they requested pricing packages.", "occurred_at": "2026-07-02T01:30:00Z"}'
```

**Tests (6 passing in this module, 37 total):** Covers rep rejection on assign (403), manager assignment to reps, validation failure for non-reps, rep activity logging on owned leads, rep rejection on other leads, and integration between activity logging and won/lost status transition.

### Phase 6 — Report

**Goal:** Implement an efficient, N+1 free, Cartesian-product free sales representative performance report.

**Endpoints:**

| Method | URI | Auth | Description |
|---|---|---|---|
| `GET` | `/api/reports/rep-performance` | `auth:sanctum` | Retrieve aggregated performance metrics (scoped if rep) |

**Design & Performance Decisions:**

- **Solving Cartesian Duplication:** Joining both the `leads` table and the `activities` table directly in a flat query causes a Cartesian product multiplier (e.g., a lead with 3 activities duplicates the lead's monetary value 3 times in the `SUM()`). To avoid this, we query aggregates inside isolated subqueries (`lead_stats` grouped by rep and `activity_stats` grouped by rep), and then `LEFT JOIN` those subqueries onto the `users` table.
- **Role Scoping:** Managers can view performance statistics for all sales representatives, whereas Reps are scoped to only view their own performance statistics at the query builder level using `when($user->isRep(), fn($q) => $q->where('users.id', $user->id))`.
- **Query Complexity $O(1)$:** The database load scales constantly regardless of the number of reps because aggregation happens database-side in a single execution. We verify this via query log assertions in tests.

**Generated SQL Query:**

```sql
SELECT 
    `users`.`id`, 
    `users`.`name`, 
    COALESCE(lead_stats.total_leads, 0) as total_leads, 
    COALESCE(lead_stats.new_count, 0) as new_count, 
    COALESCE(lead_stats.contacted_count, 0) as contacted_count, 
    COALESCE(lead_stats.qualified_count, 0) as qualified_count, 
    COALESCE(lead_stats.won_count, 0) as won_count, 
    COALESCE(lead_stats.lost_count, 0) as lost_count, 
    COALESCE(lead_stats.total_expected_value, 0.00) as total_expected_value, 
    COALESCE(lead_stats.won_expected_value, 0.00) as won_expected_value, 
    COALESCE(activity_stats.total_activities, 0) as total_activities 
FROM `users` 
LEFT JOIN (
    SELECT 
        `assigned_to`, 
        COUNT(*) as total_leads, 
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count, 
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted_count, 
        SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_count, 
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_count, 
        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_count, 
        SUM(expected_value) as total_expected_value, 
        SUM(CASE WHEN status = 'won' THEN expected_value ELSE 0 END) as won_expected_value 
    FROM `leads` 
    GROUP BY `assigned_to`
) as `lead_stats` ON `lead_stats`.`assigned_to` = `users`.`id` 
LEFT JOIN (
    SELECT 
        `leads`.`assigned_to`, 
        COUNT(activities.id) as total_activities 
    FROM `activities` 
    INNER JOIN `leads` ON `activities`.`lead_id` = `leads`.`id` 
    GROUP BY `leads`.`assigned_to`
) as `activity_stats` ON `activity_stats`.`assigned_to` = `users`.`id` 
WHERE `users`.`role` = 'rep' 
  /* AND `users`.`id` = ? (when logged-in user is a rep) */
```

**Example curl command:**

```bash
# Retrieve Rep Performance report
curl -X GET http://localhost/api/reports/rep-performance \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

**Tests (3 passing in this module, 40 total):** Verifies accurate summation/grouping, manager vs rep visibility scoping, and validates that query count remains flat/constant ($O(1)$ complexity) when scale increases from 2 reps to 7 reps.

### Phase 7 — API Consistency

**Goal:** Standardize response envelopes across all endpoints (reads, updates, logins, logouts, actions, and errors) and configure clean JSON error formats.

**Design & Exception Tweaks:**

- **JSON Resource Enforcement:** Refactored `AuthController` (login/logout/me) to route their results through `LoginResource`, `MessageResource`, and `UserResource`.
- **Enforced JSON Exceptions:** Modified `bootstrap/app.php` exception handler using `shouldRenderJsonWhen` to automatically format all errors occurring on routes starting with `api/*` as JSON, bypassing standard browser HTML rendering even if the `Accept: application/json` header is absent.

**Standard Response Shapes:**

#### 1. Success Response (200 OK / 201 Created)
Wrapped in a standard `"data"` envelope:
```json
{
  "data": {
    "id": 1,
    "name": "Jane Rep",
    "email": "jane@example.com",
    "role": "rep"
  }
}
```

#### 2. Validation Error (422 Unprocessable Content)
Returns the default Laravel validation envelope:
```json
{
  "message": "The rep id field is required.",
  "errors": {
    "rep_id": [
      "The rep id field is required."
    ]
  }
}
```

#### 3. Authorization / Authentication Failure (403 Forbidden / 401 Unauthorized)
Returns standard message format:
```json
{
  "message": "This action is unauthorized."
}
```

**Tests (1 passing in this module, 41 total):** Verifies that exceptions on API routes return clean JSON formats containing a `message` parameter on 404, bypassing standard HTML views.

### Phase 8 — Testing

**Goal:** Assure quality of all requirements by checking that the Pest test suite executes cleanly and covers the whole project scope.

**Scope of Covered Tests:**

- **Authentication (`AuthTest.php`):** Validates logging in (success/failure scenarios), fetching own profile (`/api/me`), token revocation on logout, and rejecting unauthenticated requests.
- **Lead Visibility Policy (`LeadPolicyTest.php`):** Verifies role-based view/create/update/assign policies directly (managers can view/edit everything, reps can only view/edit their own assigned leads, reps cannot assign leads).
- **Leads CRUD (`LeadTest.php`):** Verifies rep isolation, manager override, full filtering/search/sort parameters, validation of input values, won/lost transition barriers (and their integration with activity logging), and JSON exception handling (e.g. 404 formatting).
- **Assignments & Activities (`LeadTest.php`):** Validates manager-only assign permissions, validation checks (cannot assign to managers), and rep activity logging authorization.
- **Report Endpoint (`ReportTest.php`):** Validates calculation accuracy (sum, status group count, total expected vs won expected, total activities), role scoping, and performance O(1) query complexity.

### Phase 9 — Seed Data

**Goal:** Create a reproducible dataset of reps, leads, and activities conforming to business constraints.

**Seeded Objects:**

- **Users:** 1 manager (`manager@minicrm.test`) and 3 reps (`rep1@minicrm.test`, `rep2@minicrm.test`, `rep3@minicrm.test`), all using `password` as password.
- **Leads:** 25 leads with randomized statuses (new, contacted, qualified, won, lost) and sources, realistic expected value amounts ($1,000 to $75,000), and ~80% assigned to reps.
- **Activities:** Generates 1 to 4 activities for 60% of normal leads, and guarantees 1 to 4 activities on any lead seeded with the status `won` or `lost` to adhere to Phase 4 transition constraints.

## Running Tests

To run the full suite using Pest, run the following Artisan command:

```bash
# If running locally
php artisan test

# If running within Laravel Sail
./vendor/bin/sail test
```



