# DTR / DTS Separation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split `dtr-laravel-dev` into two standalone Laravel 8 apps — a DTR-only app (existing repo) and a new DTS-only app at `C:\xampp\htdocs\dts-laravel-dev` — both pointing at the same centralized MySQL database.

**Architecture:** Fork-copy-strip. Copy the whole project (incl. `vendor/`, excl. `.git`) to the sibling folder, commit a baseline, then strip DTR code from the copy and DTS code from the original. Shared data (`users`, `offices`, `sections`, `user_logs`) lives in one DB; migrations are split by table ownership; each app has its own login against the same `users` table. Cross-app links are env-configured URLs.

**Tech Stack:** Laravel 8, PHP ^7.3, MySQL, XAMPP (Apache serving `/public` of each app folder).

**Spec:** `docs/superpowers/specs/2026-08-25-dtr-dts-separation-design.md`

## Global Constraints

- Laravel 8 / PHP ^7.3 — do not upgrade frameworks or syntax.
- One shared database: do NOT create new databases or re-run migrations. The DTS app must report its 11 migrations as already run via the shared `migrations` table.
- Migration ownership: DTS app owns ONLY these 11 files (keep identical filenames so the `migrations` table matches):
  - `2026_06_30_100000_create_dts_documents_table.php`
  - `2026_06_30_100001_create_dts_document_logs_table.php`
  - `2026_06_30_110000_add_category_to_dts_documents_table.php`
  - `2026_06_30_110001_create_dts_notifications_table.php`
  - `2026_07_01_000001_add_action_requested_to_dts_documents_table.php`
  - `2026_07_01_100000_add_communication_type_to_dts_documents_table.php`
  - `2026_07_01_110000_add_action_requested_to_dts_document_logs_table.php`
  - `2026_07_13_000001_create_chat_threads_table.php`
  - `2026_07_13_000002_create_chat_messages_table.php`
  - `2026_07_13_000003_create_chat_thread_closures_table.php`
  - `2026_07_13_000004_add_recipient_id_to_dts_document_logs_table.php`
- Never run `php artisan migrate:fresh`, `migrate:rollback`, or `db:wipe` on either app — this is live centralized data.
- No test suite exists (`tests/` contains only Laravel defaults and tests would hit the real DB). Verification = artisan boot commands + `git grep` + HTTP smoke checks with expected output listed per step.
- Keep code style of surrounding files; no refactoring beyond what the split requires.
- Cross-app URL config keys (defined in `config/app.php`, backed by `env()`): `dts_url`, `dtr_url`, `portal_url`.
- This repo has unrelated uncommitted local changes (a modified migration file, docker files). Do not stage or revert them when committing plan tasks.

---

### Task 1: Create the DTS app copy with baseline commit and environment config

**Files:**
- Create: `C:\xampp\htdocs\dts-laravel-dev\` (full project copy)
- Modify: `C:\xampp\htdocs\dts-laravel-dev\.env`

**Interfaces:**
- Produces: a bootable copy at `C:\xampp\htdocs\dts-laravel-dev` with its own git history, `APP_NAME=DTS`, distinct `SESSION_COOKIE=dts_session`, fresh `APP_KEY`, same DB credentials as DTR. Later tasks strip it down.

- [ ] **Step 1: Copy the project excluding .git**

```powershell
robocopy C:\xampp\htdocs\dtr-laravel-dev C:\xampp\htdocs\dts-laravel-dev /E /XD ".git"
```

Expected: robocopy summary listing thousands of files copied. Exit codes 0–7 mean success (robocopy uses nonzero exit codes for "files copied" — this is normal). Verify the folder exists:

```powershell
Test-Path C:\xampp\htdocs\dts-laravel-dev\artisan
```

Expected: `True`

- [ ] **Step 2: Initialize git and commit the untouched baseline**

Stripping is reviewable only if the pre-strip state is committed first.

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
git init
git add -A
git commit -m "Baseline: fork of dtr-laravel-dev before DTR/DTS separation strip"
```

Expected: one commit containing the full project.

- [ ] **Step 3: Point the copy's .env at itself**

Edit `C:\xampp\htdocs\dts-laravel-dev\.env`. Change/add these lines (DB credentials lines stay exactly as copied):

```
APP_NAME=DTS
APP_URL=http://localhost/dts-laravel-dev/public
SESSION_COOKIE=dts_session
DTR_APP_URL=http://localhost/dtr-laravel-dev/public
PORTAL_URL=http://localhost/dtr-laravel-dev/public
```

Note: leave `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` untouched — same centralized DB.

- [ ] **Step 4: Generate an independent APP_KEY**

Both apps must not share encryption keys.

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
php artisan key:generate --force
```

Expected: `Application key set successfully.`

- [ ] **Step 5: Verify the copy boots**

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
php artisan route:list
```

Expected: full route list including `dts.*` AND `dtr.*`/`admin.*` routes (not stripped yet). No exceptions.

- [ ] **Step 6: Update .env.example in the copy**

Edit `C:\xampp\htdocs\dts-laravel-dev\.env.example`: change `APP_NAME=` line value to `DTS`, add:

```
SESSION_COOKIE=dts_session
DTR_APP_URL=http://localhost/dtr-laravel-dev/public
PORTAL_URL=http://localhost/dtr-laravel-dev/public
```

- [ ] **Step 7: Commit**

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
git add .env.example
git commit -m "Configure DTS app identity and cross-app URLs"
```

(Do NOT commit the real `.env` — confirm `.gitignore` already excludes it.)

---

### Task 2: Strip all DTR code from the DTS app

**Files:**
- Delete: controllers `app\Http\Controllers\{DtrController,DtrEditRequestController,SupervisorController,AdminController}.php`, `app\Http\Controllers\Auth\{RegisterController,ForgotPasswordController,ProfileController,LoginController}.php`, `app\Http\Controllers\Api\BiometricPunchController.php`
- Delete: models `app\Models\{DtrUser,DtrSetting,DtrEditRequest,DtrDayOverride,DtrMonthlyShare,GlobalHoliday,IclockTransaction,Memo,PasswordResetRequest}.php`
- Delete: console commands `app\Console\Commands\{BackupDatabase,SyncBiometricPunches,SyncZktecoEmployees}.php`
- Delete: every migration EXCEPT the 11 DTS/chat files listed in Global Constraints
- Delete: view folders `resources\views\admin`, `resources\views\dtr`, `resources\views\supervisor`; files `resources\views\layouts\app.blade.php`, `resources\views\auth\*` (whole folder), `resources\views\portal.blade.php`, `resources\views\welcome.blade.php`
- Modify: `routes\web.php` (full rewrite), `routes\api.php`, `app\Console\Kernel.php`, `app\Http\Middleware\Authenticate.php`, `app\Providers\RouteServiceProvider.php`, `config\app.php`, `app\Models\User.php`, `app\Models\Office.php`, `app\Models\Section.php`, `resources\views\dts\layouts\app.blade.php`

**Interfaces:**
- Consumes: config keys `dtr_url`, `portal_url` defined in this task's `config/app.php` edit.
- Produces: DTS-only app where `/` redirects to `/dts`; login only via `dts.login`; `route('logout')` still exists; models `User`/`Office`/`Section` have no references to deleted classes.

- [ ] **Step 1: Delete DTR-side PHP classes and views**

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
Remove-Item -LiteralPath app\Http\Controllers\DtrController.php, app\Http\Controllers\DtrEditRequestController.php, app\Http\Controllers\SupervisorController.php, app\Http\Controllers\AdminController.php, app\Http\Controllers\Auth\RegisterController.php, app\Http\Controllers\Auth\ForgotPasswordController.php, app\Http\Controllers\Auth\ProfileController.php, app\Http\Controllers\Auth\LoginController.php
Remove-Item -Recurse app\Http\Controllers\Api
Remove-Item -LiteralPath app\Models\DtrUser.php, app\Models\DtrSetting.php, app\Models\DtrEditRequest.php, app\Models\DtrDayOverride.php, app\Models\DtrMonthlyShare.php, app\Models\GlobalHoliday.php, app\Models\IclockTransaction.php, app\Models\Memo.php, app\Models\PasswordResetRequest.php
Remove-Item app\Console\Commands\BackupDatabase.php, app\Console\Commands\SyncBiometricPunches.php, app\Console\Commands\SyncZktecoEmployees.php
Remove-Item -Recurse resources\views\admin, resources\views\dtr, resources\views\supervisor
Remove-Item -Recurse resources\views\auth
Remove-Item -LiteralPath resources\views\layouts\app.blade.php, resources\views\portal.blade.php, resources\views\welcome.blade.php
```

Expected: silent success. Do NOT delete `app\Services\UserLogService.php`, `app\Services\LogsUserActivity.php`, `app\Models\UserLog.php` — DTS uses them.

- [ ] **Step 2: Delete non-DTS migrations**

In PowerShell, from `C:\xampp\htdocs\dts-laravel-dev`, delete everything in `database/migrations` except the 11 keepers:

```powershell
$keep = @(
  '2026_06_30_100000_create_dts_documents_table.php',
  '2026_06_30_100001_create_dts_document_logs_table.php',
  '2026_06_30_110000_add_category_to_dts_documents_table.php',
  '2026_06_30_110001_create_dts_notifications_table.php',
  '2026_07_01_000001_add_action_requested_to_dts_documents_table.php',
  '2026_07_01_100000_add_communication_type_to_dts_documents_table.php',
  '2026_07_01_110000_add_action_requested_to_dts_document_logs_table.php',
  '2026_07_13_000001_create_chat_threads_table.php',
  '2026_07_13_000002_create_chat_messages_table.php',
  '2026_07_13_000003_create_chat_thread_closures_table.php',
  '2026_07_13_000004_add_recipient_id_to_dts_document_logs_table.php'
)
Get-ChildItem database\migrations -Filter *.php | Where-Object { $keep -notcontains $_.Name } | Remove-Item
Get-ChildItem database\migrations | Measure-Object | Select-Object Count
```

Expected: `Count : 11`

- [ ] **Step 3: Rewrite routes/web.php**

Replace the ENTIRE content of `C:\xampp\htdocs\dts-laravel-dev\routes\web.php` with:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dts');

Route::post('/logout', [App\Http\Controllers\Auth\LogoutController::class, 'store'])
    ->name('logout');

Route::prefix('dts')->name('dts.')->group(function () {
    Route::get('/login', [App\Http\Controllers\DtsController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [App\Http\Controllers\DtsController::class, 'login']);

    Route::middleware('auth')->group(function () {
        Route::get('/', [App\Http\Controllers\DtsController::class, 'index'])
            ->name('index');

        Route::get('/documents', [App\Http\Controllers\DtsController::class, 'documents'])
            ->name('documents');
        Route::get('/documents/create', [App\Http\Controllers\DtsController::class, 'create'])
            ->name('documents.create');
        Route::post('/documents', [App\Http\Controllers\DtsController::class, 'store'])
            ->name('documents.store');
        Route::get('/documents/{document}', [App\Http\Controllers\DtsController::class, 'show'])
            ->name('documents.show');
        Route::get('/documents/{document}/edit', [App\Http\Controllers\DtsController::class, 'edit'])
            ->name('documents.edit');
        Route::put('/documents/{document}', [App\Http\Controllers\DtsController::class, 'update'])
            ->name('documents.update');
        Route::delete('/documents/{document}', [App\Http\Controllers\DtsController::class, 'destroy'])
            ->name('documents.destroy');
        Route::post('/documents/{id}/restore', [App\Http\Controllers\DtsController::class, 'restore'])
            ->name('documents.restore');
        Route::get('/documents/{document}/print-routing-slip', [App\Http\Controllers\DtsController::class, 'printRoutingSlip'])
            ->name('documents.print-routing-slip');
        Route::post('/documents/{document}/forward', [App\Http\Controllers\DtsController::class, 'forward'])
            ->name('documents.forward');
        Route::post('/documents/{document}/receive', [App\Http\Controllers\DtsController::class, 'receive'])
            ->name('documents.receive');
        Route::post('/documents/{document}/process', [App\Http\Controllers\DtsController::class, 'process'])
            ->name('documents.process');
        Route::post('/documents/{id}/force-delete', [App\Http\Controllers\DtsController::class, 'forceDelete'])
            ->name('documents.force-delete');
        Route::post('/documents/bulk-action', [App\Http\Controllers\DtsController::class, 'bulkAction'])
            ->name('documents.bulk-action');

        Route::get('/chat/threads', [App\Http\Controllers\ChatController::class, 'threads'])
            ->name('chat.threads');
        Route::get('/chat/search-users', [App\Http\Controllers\ChatController::class, 'searchUsers'])
            ->name('chat.search-users');
        Route::post('/chat/threads', [App\Http\Controllers\ChatController::class, 'storeThread'])
            ->name('chat.threads.store');
        Route::get('/chat/threads/{thread}/messages', [App\Http\Controllers\ChatController::class, 'messages'])
            ->name('chat.messages');
        Route::post('/chat/threads/{thread}/messages', [App\Http\Controllers\ChatController::class, 'sendMessage'])
            ->name('chat.messages.store');
        Route::post('/chat/threads/{thread}/close', [App\Http\Controllers\ChatController::class, 'closeThread'])
            ->name('chat.threads.close');
        Route::post('/chat/threads/{thread}/open', [App\Http\Controllers\ChatController::class, 'openThread'])
            ->name('chat.threads.open');

        Route::get('/analytics', [App\Http\Controllers\DtsController::class, 'analytics'])
            ->name('analytics');

        Route::get('/notifications', [App\Http\Controllers\DtsController::class, 'notifications'])
            ->name('notifications');
        Route::get('/notifications/{id}/read', [App\Http\Controllers\DtsController::class, 'markNotificationRead'])
            ->name('notifications.read');
        Route::post('/notifications/mark-all-read', [App\Http\Controllers\DtsController::class, 'markAllNotificationsRead'])
            ->name('notifications.mark-all-read');

        Route::get('/sections-by-office/{office}', [App\Http\Controllers\DtsController::class, 'sectionsByOffice'])
            ->name('sections-by-office');
        Route::get('/users-by-office/{office}', [App\Http\Controllers\DtsController::class, 'usersByOffice'])
            ->name('users-by-office');
        Route::get('/users-by-section/{section}', [App\Http\Controllers\DtsController::class, 'usersBySection'])
            ->name('users-by-section');
    });
});
```

- [ ] **Step 4: Empty routes/api.php to framework default biometric-free version**

Replace entire content of `C:\xampp\htdocs\dts-laravel-dev\routes\api.php` with:

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
```

- [ ] **Step 5: Clean app/Console/Kernel.php**

In `C:\xampp\htdocs\dts-laravel-dev\app\Console\Kernel.php`:

a) Replace the `$commands` array so it is empty:

```php
    protected $commands = [
        //
    ];
```

b) Delete these two lines from the `schedule(Schedule $schedule)` method body:

```php
            $schedule->command('backup:run')->dailyAt('23:00');
```

```php
            $schedule->command('dtr:sync-punches')->everyMinute()
                ->withoutOverlapping();
            $schedule->command('dtr:sync-employees --create-users')->everySixHours()
                ->withoutOverlapping();
```

(The exact chaining may differ slightly — remove every `$schedule->command(...)` statement so the method body contains no scheduled commands.)

- [ ] **Step 6: Simplify auth redirect middleware**

Replace the `redirectTo` method in `C:\xampp\htdocs\dts-laravel-dev\app\Http\Middleware\Authenticate.php` with:

```php
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('dts.login');
        }
    }
```

- [ ] **Step 7: Point HOME at DTS**

In `C:\xampp\htdocs\dts-laravel-dev\app\Providers\RouteServiceProvider.php` change:

```php
    public const HOME = '/dts';
```

- [ ] **Step 8: Add cross-app URLs to config/app.php**

In `C:\xampp\htdocs\dts-laravel-dev\config\app.php`, inside the `return [...]` array (e.g., right after the `'timezone'` entry), add:

```php
    'dtr_url' => env('DTR_APP_URL', 'http://localhost/dtr-laravel-dev/public'),
    'portal_url' => env('PORTAL_URL', 'http://localhost/dtr-laravel-dev/public'),
```

Also change the existing `'name'` value to `'DTS'`.

- [ ] **Step 9: Trim DTR relations out of kept models**

In `C:\xampp\htdocs\dts-laravel-dev\app\Models\User.php` delete the whole method:

```php
    public function dtrUser()
    {
        return $this->belongsTo(DtrUser::class, 'emp_code', 'emp_code');
    }
```

Keep `office()`, `section()`, `dtsDocuments()`.

In `C:\xampp\htdocs\dts-laravel-dev\app\Models\Office.php` delete these five methods (keep `sections()`):

```php
    public function dtrUsers()
    {
        return $this->hasMany(DtrUser::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(DtrUser::class, 'supervisor_id');
    }

    public function seniorManager()
    {
        return $this->belongsTo(DtrUser::class, 'senior_manager_id');
    }

    public function oic()
    {
        return $this->belongsTo(DtrUser::class, 'oic_id');
    }

    public function seniorManagerOic()
    {
        return $this->belongsTo(DtrUser::class, 'senior_manager_oic_id');
    }
```

In `C:\xampp\htdocs\dts-laravel-dev\app\Models\Section.php` delete `dtrUsers()`, `supervisor()`, and `oic()` methods (they reference `DtrUser`; keep `office()`).

If any of the trimmed model files import `DtrUser` via a `use App\Models\DtrUser;` statement, remove that import line too.

- [ ] **Step 10: Repoint cross-app links in the DTS layout**

In `C:\xampp\htdocs\dts-laravel-dev\resources\views\dts\layouts\app.blade.php`:

a) Replace ALL occurrences of:

```blade
{{ route('dtr.dashboard') }}
```

with:

```blade
{{ config('app.dtr_url') }}/dtr/dashboard
```

b) Replace ALL occurrences of:

```blade
{{ route('portal') }}
```

with:

```blade
{{ config('app.portal_url') }}
```

Verify nothing remains:

```powershell
Select-String -Path resources\views\dts\layouts\app.blade.php -Pattern "route\('portal'\)|route\('dtr\.dashboard'\)"
```

Expected: no output.

Check the other kept DTS views too:

```powershell
Get-ChildItem resources\views -Recurse -Filter *.blade.php | Select-String -Pattern "route\('portal'\)|route\('dtr\.|route\('login'\)|route\('register'\)|dtr\.dashboard"
```

Expected: no output. If hits appear in `dts/login.blade.php` or others, replace those references the same way (portal → `config('app.portal_url')`, DTR pages → `config('app.dtr_url')` + path).

- [ ] **Step 11: Grep-verify no dangling class references**

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
git add -A
git grep -n -E "DtrController|DtrEditRequestController|SupervisorController|AdminController|DtrUser|DtrSetting|GlobalHoliday|IclockTransaction|Memo::|PasswordResetRequest|SyncZktecoEmployees|SyncBiometricPunches|BackupDatabase" -- "*.php" "*.blade.php"
```

Expected: NO output. (The string `Memo::` catches static calls; plain word "Memo" appears in comments sometimes — if a comment hit shows up, that's acceptable, but code hits must be fixed.) Fix anything found by removing the reference, then re-run until clean.

- [ ] **Step 12: Boot-verify the DTS app**

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
php artisan route:list
php artisan migrate:status
```

Expected route:list: only `/` redirect, `logout`, and `dts.*` routes. Expected migrate:status: the 11 migration files shown as **Ran** (they exist in the shared `migrations` table), nothing pending. If any show as Pending, STOP — do not run migrate; instead verify the filename exactly matches the `migrations` table rows (query: `SELECT migration FROM migrations WHERE migration LIKE '%dts%' OR migration LIKE '%chat%'`).

- [ ] **Step 13: Commit**

```powershell
Set-Location C:\xampp\htdocs\dts-laravel-dev
git add -A
git commit -m "Strip DTR system: standalone DTS app on centralized database"
```

---

### Task 3: Strip all DTS code from the DTR app

**Files:**
- Delete: `app\Http\Controllers\{DtsController,ChatController}.php`
- Delete: models `app\Models\{DtsDocument,DtsDocumentLog,DtsNotification,ChatThread,ChatMessage,ChatThreadClosure}.php`
- Delete: the 11 DTS/chat migrations (same list as Global Constraints)
- Delete: `resources\views\dts\` (whole folder)
- Modify: `routes\web.php` (remove dts group), `resources\views\portal.blade.php`, `config\app.php`, `.env.example`, `app\Models\User.php` (remove `dtsDocuments()`)

**Interfaces:**
- Consumes: nothing from the DTS app.
- Produces: `config('app.dts_url')` used by portal; DTR app boots with zero references to DTS classes/routes.

- [ ] **Step 1: Delete DTS-side classes, views, migrations**

```powershell
Set-Location C:\xampp\htdocs\dtr-laravel-dev
Remove-Item -LiteralPath app\Http\Controllers\DtsController.php, app\Http\Controllers\ChatController.php
Remove-Item -LiteralPath app\Models\DtsDocument.php, app\Models\DtsDocumentLog.php, app\Models\DtsNotification.php, app\Models\ChatThread.php, app\Models\ChatMessage.php, app\Models\ChatThreadClosure.php
Remove-Item -Recurse resources\views\dts
$dtsMigrations = @(
  '2026_06_30_100000_create_dts_documents_table.php',
  '2026_06_30_100001_create_dts_document_logs_table.php',
  '2026_06_30_110000_add_category_to_dts_documents_table.php',
  '2026_06_30_110001_create_dts_notifications_table.php',
  '2026_07_01_000001_add_action_requested_to_dts_documents_table.php',
  '2026_07_01_100000_add_communication_type_to_dts_documents_table.php',
  '2026_07_01_110000_add_action_requested_to_dts_document_logs_table.php',
  '2026_07_13_000001_create_chat_threads_table.php',
  '2026_07_13_000002_create_chat_messages_table.php',
  '2026_07_13_000003_create_chat_thread_closures_table.php',
  '2026_07_13_000004_add_recipient_id_to_dts_document_logs_table.php'
)
Get-ChildItem database\migrations -Filter *.php | Where-Object { $dtsMigrations -contains $_.Name } | Remove-Item
(Get-ChildItem database\migrations -Filter *dts*).Count + (Get-ChildItem database\migrations -Filter *chat*).Count
```

Expected: `0`

- [ ] **Step 2: Remove the dts route group**

In `C:\xampp\htdocs\dtr-laravel-dev\routes\web.php` delete the entire block from:

```php
Route::prefix('dts')->name('dts.')->group(function () {
```

through its matching closing:

```php
});
```

(lines 31–101 in the current file — the group ending right before `Route::middleware('auth')->group(function () {` which opens the DTR routes). Leave every other route untouched.

- [ ] **Step 3: Remove User::dtsDocuments() relation**

In `C:\xampp\htdocs\dtr-laravel-dev\app\Models\User.php` delete the whole method:

```php
    public function dtsDocuments()
    {
        ...
    }
```

(read the current body first; delete the complete method). Also remove any now-unused `use` imports pointing at deleted DTS models in `User.php`.

- [ ] **Step 4: Portal card links to the DTS app URL**

In `C:\xampp\htdocs\dtr-laravel-dev\resources\views\portal.blade.php` replace:

```blade
<a href="{{ route('dts.login') }}" class="portal-card">
```

with:

```blade
<a href="{{ config('app.dts_url') }}/dts/login" class="portal-card">
```

Then check for other DTS references in remaining views:

```powershell
Get-ChildItem resources\views -Recurse -Filter *.blade.php | Select-String -Pattern "dts\.|route\('dts" | Select-Object Filename, LineNumber, Line
```

Expected: no output (the whole `resources/views/dts` folder is gone; nothing else should mention `dts.` routes).

- [ ] **Step 5: Add dts_url to config/app.php and .env.example**

In `C:\xampp\htdocs\dtr-laravel-dev\config\app.php`, after the `'timezone'` entry, add:

```php
    'dts_url' => env('DTS_APP_URL', 'http://localhost/dts-laravel-dev/public'),
```

Append to `C:\xampp\htdocs\dtr-laravel-dev\.env.example`:

```
DTS_APP_URL=http://localhost/dts-laravel-dev/public
```

And add the real value to `C:\xampp\htdocs\dtr-laravel-dev\.env` (same line) — needed for the smoke test in Task 4.

- [ ] **Step 6: Grep-verify no dangling references**

```powershell
Set-Location C:\xampp\htdocs\dtr-laravel-dev
git grep -n -E "DtsController|ChatController|DtsDocument|DtsDocumentLog|DtsNotification|ChatThread|ChatMessage|ChatThreadClosure|route\('dts" -- "*.php" "*.blade.php"
```

Expected: NO output. Fix any hit and re-run until clean.

- [ ] **Step 7: Boot-verify the DTR app**

```powershell
Set-Location C:\xampp\htdocs\dtr-laravel-dev
php artisan route:list
php artisan migrate:status
```

Expected route:list: no `dts.*` routes; `dtr.*`, `admin.*`, auth, portal routes present. Expected migrate:status: all listed migrations Ran except your known locally-modified one if it was ever un-run; NOTHING referencing dts/chat tables listed as pending (their rows remain in the shared `migrations` table but Laravel ignores records without files).

- [ ] **Step 8: Commit (only the intended files)**

```powershell
Set-Location C:\xampp\htdocs\dtr-laravel-dev
git status --short
```

Stage ONLY the split-related changes (leave the pre-existing modified migration `database/migrations/2026_06_29_000000_rename_emp_code_to_bio_id.php` and docker/untracked files alone):

```powershell
git add app routes resources/views/portal.blade.php config/app.php .env.example database/migrations docs/superpowers/plans/2026-08-25-dtr-dts-separation.md
git commit -m "Strip DTS system into standalone dts-laravel-dev app"
```

Expected: commit succeeds; `git status` afterwards still shows the unrelated local changes untouched.

---

### Task 4: Cross-app integration verification

**Files:**
- None created/modified (verification task). Optionally fix whatever this task uncovers.

**Interfaces:**
- Consumes: both apps configured by Tasks 1–3, Apache/XAMPP running.

- [ ] **Step 1: Serve both apps through XAMPP and smoke-test DTR**

Start Apache + MySQL (XAMPP control panel), then with a browser (or `Invoke-WebRequest`):

```powershell
$r = Invoke-WebRequest -Uri "http://localhost/dtr-laravel-dev/public/" -UseBasicParsing
$r.StatusCode
```

Expected: `200` (portal renders). Follow the DTS card href — confirm it points to `http://localhost/dts-laravel-dev/public/dts/login`.

- [ ] **Step 2: Smoke-test DTS**

```powershell
$r = Invoke-WebRequest -Uri "http://localhost/dts-laravel-dev/public/dts/login" -UseBasicParsing
$r.StatusCode
```

Expected: `200` and the page HTML contains the DTS login form (search for `name="username"` or similar credential field present in `resources/views/dts/login.blade.php` of the DTS app).

Also confirm `/` on the DTS app redirects:

```powershell
(Invoke-WebRequest -Uri "http://localhost/dts-laravel-dev/public/" -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue).StatusCode
```

Expected: `302` (redirect to `/dts`). A `200` here means the redirect route is missing.

- [ ] **Step 3: Login-flow verification (manual, browser)**

1. Log into DTR (`/login`) with a valid employee account → dashboard loads.
2. In a second tab, log into DTS (`/dts/login`) with the SAME account → DTS index loads.
3. Back in tab 1, reload the DTR dashboard → still logged in (sessions did NOT clobber each other thanks to distinct `SESSION_COOKIE`).
4. In DTS: create a document, forward it, receive it as the recipient (or verify forward state), open chat, send one message.
5. Log out of DTS → DTR tab still authenticated.
6. Change password via DTR profile page → log into DTS with the NEW password → success (shared users table proven).
7. Check `dts_notifications` arrived for the forwarded document and `user_logs` gained rows from DTS actions (both written by the DTS app into shared tables).

If step 3 fails (one logout kills the other): confirm `SESSION_COOKIE=dts_session` is actually present in `C:\xampp\htdocs\dts-laravel-dev\.env` and run `php artisan config:clear` in BOTH apps, then retry in a fresh incognito window.

- [ ] **Step 4: Final artifact check**

```powershell
Set-Location C:\xampp\htdocs\dtr-laravel-dev; git status --short
Set-Location C:\xampp\htdocs\dts-laravel-dev; git status --short; git log --oneline
```

Expected: DTR repo clean apart from pre-existing unrelated changes; DTS repo clean with ≥3 commits (baseline, config, strip).

Report results to the user; no commit required for this task unless fixes were needed (then commit fixes in the respective repo with a message describing the fix).
