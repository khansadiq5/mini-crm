# Mini CRM — Project Scope

## 1. Overview
Mini CRM is a small backend-first Customer Relationship Management system for
sales teams. Sales reps work **Leads**. Each lead can have many **Activities**
logged against it (call, email, meeting, note). Leads are assigned to a rep,
move through a pipeline of statuses, and managers get a performance report
across all reps.

The core deliverable is a **JSON API** (Laravel + Sanctum). A minimal Blade +
Tailwind admin UI is added on top as a bonus, purely to visualize/test the API
manually — it is not part of the graded deliverable, it consumes the same API.

## 2. Tech Stack
| Layer | Choice |
|---|---|
| Backend framework | Laravel 12 |
| Language | PHP 8.2+ |
| Database | MySQL (via Laravel Sail / Docker) |
| Auth | Laravel Sanctum (token-based) |
| Frontend (bonus only) | Blade templates + HTML + Tailwind CSS (CDN) |
| Testing | PHPUnit / Laravel Feature Tests |
| Local dev | Laravel Sail (Docker) |
| Queue (bonus) | `database` queue driver |
| Cache (bonus) | Laravel Cache (file/array driver, swappable to Redis) |

## 3. Domain Rules
- A **User** has a role: `manager` or `rep`.
- A **Lead** belongs to one assigned rep (nullable until assigned). Fields:
  name, email, phone, company (nullable), source (enum), status (enum),
  expected_value (decimal 12,2 — never float).
- An **Activity** belongs to a lead and to the user who logged it. Fields:
  type (enum), body (text), occurred_at (timestamp).
- **Visibility:** a `rep` only sees/acts on leads assigned to them. A
  `manager` sees/acts on all leads. Enforced via Policies, not ad-hoc
  controller checks.
- **Won/Lost rule:** a lead can only move to `won` or `lost` if it has at
  least one logged activity. Otherwise reject with a clear 422 error.

## 4. API Surface (all under `auth:sanctum`, JSON only)
1. `POST /api/login`
2. `GET /api/leads` (filter: status, source, assigned_to; search: name/email/company; sort; pagination)
3. `POST /api/leads`
4. `GET /api/leads/{id}` (with activities + assigned rep, eager loaded)
5. `PATCH /api/leads/{id}`
6. `POST /api/leads/{id}/assign` (manager only)
7. `POST /api/leads/{id}/activities`
8. `GET /api/reports/rep-performance` (single aggregate query, no N+1, scoped by role)

## 5. Out of Scope / Non-Goals
- No public registration — users are seeded.
- No file uploads / attachments on activities.
- No real email/SMS notifications — queued job just logs the action.
- Multi-tenancy is documented as a design note in README, not implemented.

## 6. Build Phases (see PHASE_PROMPTS.md for exact prompts used)
| Phase | Goal |
|---|---|
| 0 | Repo, Laravel install, Sail, Sanctum bootstrap |
| 1 | Migrations, models, relationships, indexes |
| 2 | Auth (login + sanctum middleware) |
| 3 | Policies (rep vs manager visibility) |
| 4 | Leads CRUD + filters/search/sort/pagination + won/lost rule |
| 5 | Assign endpoint + Activities endpoint |
| 6 | Rep-performance report (optimized query) |
| 7 | API Resources (consistent JSON envelope) |
| 8 | Feature tests (auth, visibility, won/lost, report) |
| 9 | Seeders (manager + reps + leads + activities) |
| 10 | Bonus: Blade + Tailwind admin UI (read-only dashboard on top of API) |
| 11 | Bonus: pick 1 of (queued job on assign / event+listener on status change / cache report) |
| 12 | README finalization, cleanup, final review |

## 7. Deliverables
1. Git repo with clean, phase-wise commit history.
2. README.md — setup steps, assumptions, what I'd do with more time, one
   deliberate trade-off, phase-wise change log.
3. Seeder — usable data out of the box.

## 8. Assumptions (fill in as decisions are made during build)
- Sanctum is used in **token mode** (not SPA cookie mode) for the JSON API. `statefulApi()` middleware is enabled so the bonus Blade UI can also authenticate via session cookies.
- MySQL 8.4 is used via Laravel Sail (Docker). DB name: `mini_crm`, user: `sail`, password: `password`.
- PHP 8.2 runtime is pinned in `docker-compose.yml` (not the default 8.5).
- Lead `email` is **not unique** — the same contact can exist as multiple leads from different sources/campaigns.
- `phone` is stored as a free-form string (E.164 format in factories). No regex validation at schema level.
- Enum columns are stored as `string` (not MySQL `ENUM`) to avoid schema-level migrations when adding new values. Validation is done at the application layer via PHP backed enums.
- Deleting a rep nullifies `assigned_to` on their leads (`nullOnDelete`). Deleting a lead cascades to its activities (`cascadeOnDelete`).

## 9. Trade-offs (fill in during build)
- _(update this section with the 1 deliberate trade-off made)_
