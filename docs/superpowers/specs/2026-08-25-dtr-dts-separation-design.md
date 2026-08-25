# DTR / DTS System Separation — Design

**Date:** 2026-08-25
**Status:** Approved (pending implementation)

## Goal

Split the current single Laravel app (`dtr-laravel-dev`), which contains both the DTR (Duty/Timerecord) system and the DTS (Document Tracking System), into **two independent Laravel apps**, while both continue to use the **same centralized MySQL database**.

## Decisions (agreed with user)

| Question | Decision |
|---|---|
| Project layout | Two separate repos/folders |
| Shared-data admin (users/offices/sections) | Lives in DTR app only; DTS treats them as read-only reference data |
| Authentication | Separate login pages per app, both against the same `users` table (current behavior) |
| Migrations | Split by table ownership; shared `migrations` tracking table |
| Portal landing page | Lives in DTR app at `/`; links to DTS via env-configured URL |
| Laravel version for DTS app | Match current stack: Laravel 8, PHP ^7.3 |
| Creation method | Fork-copy-strip: copy project dir (incl. `vendor/`, excl. `.git`) to `C:\xampp\htdocs\dts-laravel-dev`, then strip each side |

## Architecture

```
C:\xampp\htdocs\
├── dtr-laravel-dev\     ← existing repo, becomes DTR-only (+ portal, admin, auth)
└── dts-laravel-dev\     ← NEW app (fresh git repo), DTS-only
        both .env → same DB_HOST / DB_DATABASE
```

- **DTR app**: employee DTR views/printing, biometric sync (`SyncZktecoEmployees`, `iclock_transaction`, `punch_sync_logs`), edit requests & approvals, supervisor screens, admin panel (offices, sections, employees/users, holidays, work arrangement, monitoring, memos, settings/backup), portal home page, registration + password-reset flows.
- **DTS app**: document tracking (create/route/receive/process/forward), routing-slip printing, analytics, notifications (`dts_notifications`), chat threads/messages, own `/dts/login`. Password changes happen via the DTR app's profile page (shared `users` table).
- Both apps authenticate against the same centralized `users`, `offices`, `sections` tables.

## Database Table Ownership

| Owner | Tables | Migration files kept |
|---|---|---|
| DTR app | `users`, `password_resets`, `failed_jobs`, `dtr_users`, `dtr_settings`, `offices`, `sections`, `dtr_edit_requests`, `notifications`, `user_logs`, `password_reset_requests`, `memos`, `dtr_monthly_shares`, `global_holidays`, `dtr_day_overrides`, `punch_sync_logs`, pre-existing `iclock_transaction` | All except the 11 DTS/chat migrations |
| DTS app | `dts_documents`, `dts_document_logs`, `dts_notifications`, `chat_threads`, `chat_messages`, `chat_thread_closures` | `2026_06_30_100000*`, `2026_06_30_100001*`, `2026_06_30_110000*`, `2026_06_30_110001*`, `2026_07_01_000001*`, `2026_07_01_100000*`, `2026_07_01_110000*`, `2026_07_13_000001*` … `2026_07_13_000004*` |

Rules:

1. Each app's `database/migrations/` contains only migrations for tables it owns.
2. The shared `migrations` table already records the DTS migrations as run, so the new DTS app sees them as already executed — no schema changes or re-runs.
3. Future schema changes to a table are committed only in its owner's repo. Changes touching shared org tables go through DTR.

## App-by-App Changes

### DTR app (existing repo)

Delete:

- Controllers: `app/Http/Controllers/DtsController.php`, `ChatController.php`
- Models: `DtsDocument`, `DtsDocumentLog`, `DtsNotification`, `ChatThread`, `ChatMessage`, `ChatThreadClosure`
- Migrations: the 11 DTS/chat migration files listed above
- Views: `resources/views/dts/` (documents, chat, analytics, layouts, login, notifications)
- Routes: the entire `Route::prefix('dts')` group in `routes/web.php`

Keep unchanged: auth controllers/routes, portal, admin, supervisor, all DTR routes/controllers/models/views/migrations.

Change:

- `resources/views/portal.blade.php`: DTS card href becomes `routed through config('app.dts_url') . '/dts/login'` instead of `route('dts.login')`.
- `config/app.php`: add `'dts_url' => env('DTS_APP_URL', 'http://localhost/dts-laravel-dev/public')`.

### DTS app (new copy at `C:\xampp\htdocs\dts-laravel-dev`)

Copy the whole project including `vendor/`, excluding `.git`. Then delete:

- Controllers: `DtrController`, `DtrEditRequestController`, `SupervisorController`, `AdminController`, and `Auth\RegisterController`, `Auth\ForgotPasswordController`, `Auth\ProfileController`
- Models: `DtrUser`, `DtrSetting`, `DtrEditRequest`, `DtrDayOverride`, `DtrMonthlyShare`, `GlobalHoliday`, `IclockTransaction`, `Memo`, `PasswordResetRequest`, `UserLog`
- Console commands: any DTR-only commands (e.g., `SyncZktecoEmployees`) and their registration in `app/Console/Kernel.php`
- Migrations: everything except the 11 DTS/chat migrations
- Views: `resources/views/{admin,dtr,supervisor,vendor}/`, `welcome.blade.php`, the whole `resources/views/auth/` folder, top-level `layouts/app.blade.php`. All DTS pages extend `dts.layouts.app`; nothing kept extends the top-level layout.
- Routes: `/dtr/*`, `/admin/*`, `/supervisor/*` groups, global `sections-by-office` route, `/register`, `/login`, `/forgot-password*`, `/profile`, `/password` routes
- Rationale: accounts are created by DTR admin / biometric sync; because the DB is centralized, users change their password via the DTR app's profile page and the change applies everywhere.

Keep: `User`, `Office`, `Section` models (trim DTR-only relations such as `User::dtrUser()` if they reference removed classes), `Auth\LoginController` + `Auth\LogoutController` (`/logout` used by the DTS layout; login handled by `DtsController` at `/dts/login`), DTS controllers/models/migrations/views/routes, `public/dtr.css` (shared stylesheet used by DTS layout).

Change:

- DTS layout links "Back to Portal" and "DTR Dashboard" → config-based URLs.
- `config/app.php`: `'name' => 'DTS'`, add `'portal_url'` / `'dtr_url'` entries with defaults.

## Shared Concerns

### Sessions & cookies

Both apps will run under the same host (`http://localhost`). Cookies are host-scoped, so identical cookie names would make the two apps clobber each other's sessions. Mitigations in DTS `.env`:

- `APP_NAME=DTS` (distinct remember-me/session cookie derivation)
- Explicit `SESSION_COOKIE=dts_session`

Sessions/cache stay on each app's local file driver (default) — no shared state is needed because logins are independent.

### Environment files

DTS `.env` (new):

```
APP_NAME=DTS
APP_URL=http://localhost/dts-laravel-dev/public
DB_HOST/DB_DATABASE/... = same centralized DB credentials as DTR
SESSION_COOKIE=dts_session
DTR_APP_URL=http://localhost/dtr-laravel-dev/public
```

DTR `.env` addition: `DTS_APP_URL=http://localhost/dts-laravel-dev/public`.

### Passwords

Both apps use Laravel 8 bcrypt defaults against the same `users` table — no changes.

### Graceful degradation

- Cross-app URLs come from `config/app.php` entries backed by `env()` with sensible localhost defaults — missing env vars never break rendering.
- Existing `is_active` checks preserved in both login paths; soft-deleted users cannot log in anywhere.

## Error Handling & Edge Cases

- Stale rows in the shared `migrations` table referencing migrations removed from the DTR app are harmless (Laravel ignores records whose files no longer exist).
- Any lingering references in Blade/PHP to deleted classes or route names must be eliminated; verified by grep + `route:list` + boot test.
- Docker/nginx deployment files currently untracked in the working tree are out of scope for this split; each app can get its own service definitions later.

## Verification Checklist

1. `php artisan migrate:status` in both apps — DTR lists only its migrations; DTS lists its 11 as already run, with nothing unexpectedly pending.
2. `php artisan route:list` in each app — clean, no orphaned names; every Blade `route()` call resolves (checked via grep + page loads).
3. Grep both apps for deleted class names (`DtrController` in DTS copy, `DtsController` in DTR, etc.) — zero hits.
4. Manual smoke test: login to DTR, exercise DTR pages, logout; login to DTS, create/forward/receive a document, open chat, logout; verify logging in/out of one app does not terminate the other's session.
5. Portal renders and its DTS card opens the DTS login page.

## Out of Scope

- Unifying the codebases into packages/shared components.
- SSO / shared session authentication.
- Production deployment reconfiguration (nginx/Docker) beyond what exists today.
