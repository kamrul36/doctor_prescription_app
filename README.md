# Doctor Prescription & Patient Management System

A single-doctor / small-clinic practice management system built on **Laravel 12**. It lets a physician register patients, record visit/case history, prescribe medications, order tests, and generate printable prescriptions (PDF/DOCX).

## Tech Stack

- **Framework:** Laravel 12 (monolith — server-rendered UI + JSON API in one project)
- **Auth:** JWT via `php-open-source-saver/jwt-auth` (the `api` guard), session guard (`web`) reserved for the Blade UI
- **Database:** MySQL
- **Planned (not yet wired up):** Elasticsearch (search), Redis (cache/queue/sessions), DomPDF/PhpWord (document export) — see the doc's build order below

## Requirements

- PHP 8.2+ with the `sodium` extension enabled (required by the JWT library)
- Composer
- MySQL
- Node.js (for the Vite asset pipeline)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# configure DB credentials in .env, then:
php artisan migrate

npm install
npm run dev   # or: composer run dev
```

## Auth API

All endpoints are JSON, under `/api/auth`. The `login` endpoint is public; the rest require a `Bearer` JWT from a prior login.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/auth/login` | Log in with `email` + `password`, returns `access_token` |
| GET | `/api/auth/me` | Current authenticated user |
| POST | `/api/auth/refresh` | Exchange a valid token for a new one |
| POST | `/api/auth/logout` | Invalidate the current token |

Users have a `role` of `doctor` or `assistant` (default `doctor`), checked via a `doctor-only` Gate (`app/Providers/AppServiceProvider.php`) rather than a full permissions package.

## Tests

```bash
php artisan test
```

Auth coverage lives in `tests/Feature/Auth/AuthTest.php` (login, invalid credentials, `me`, `refresh`, `logout`).

## Build Status

Following the doc's suggested build order (§12):

- [x] **1. Auth (JWT) + role scaffolding** — done
- [ ] 2. Patient CRUD + UI
- [ ] 3. Case History (visit) CRUD, linked to patient
- [ ] 4. Medication catalog + Prescription items
- [ ] 5. Test catalog + results
- [ ] 6. PDF export → DOCX export
- [ ] 7. Search (DB-based)
- [ ] 8. Elasticsearch integration
- [ ] 9. Load balancer + stateless app server setup, security hardening
- [ ] 10. Polish: printable prescription styling, dashboard, pagination

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
