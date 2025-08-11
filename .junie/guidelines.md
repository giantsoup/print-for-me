Project development guidelines (Taylor's Print Services)

This document distills project-specific knowledge from the Project Implementation Guide and the codebase so future development is fast and consistent.

Overview
- Stack: Laravel 12 (PHP 8.4), Inertia/Vue 3 with Vite, Pest for testing, database queue locally.
- Core domain: passwordless magic-link auth; print requests with private file uploads; strict status lifecycle; policy-based access; queued notifications; retention commands.

Build and configuration (local)
- PHP and Node deps
  - composer install
  - npm install
- .env (local) essentials
  - DB_CONNECTION=sqlite
  - Create empty file database/database.sqlite
  - MAIL_MAILER=log (emails go to storage/logs/laravel.log locally)
  - QUEUE_CONNECTION=database (use database queue locally)
  - APP_NAME="Taylor's Print Services"
  - APP_URL=https://print-for-me.test
  - SESSION_LIFETIME=43200 (minutes; also used by absolute-session middleware)
  - FILESYSTEM_DISK=local (private local storage)
- App bootstrap
  - php artisan key:generate
  - php artisan queue:table && php artisan migrate
- Dev orchestration
  - composer run dev
    - Runs: php artisan serve, queue:listen --tries=1, pail for logs, and npm run dev via npx concurrently.
  - Alternative: php artisan serve and npm run dev separately.
- Frontend toolchain
  - npm run dev for Vite HMR
  - npm run build or npm run build:ssr when needed

Key configuration in repo
- Email/notifications
  - config/prints.php: admin_email (default admin@example.com), subject_prefix "[Taylor’s Print Services]".
- Test environment (phpunit.xml)
  - DB_CONNECTION=sqlite with DB_DATABASE=:memory:
  - QUEUE_CONNECTION=sync, SESSION_DRIVER=array, MAIL_MAILER=array.

Domain rules (validated server-side)
- File uploads (app/Http/Requests/StorePrintRequestRequest.php)
  - At least one source is required: either source_url or >= 1 uploaded file (enforced in withValidator).
  - Allowed extensions (mimes): stl, 3mf, obj, f3d, f3z, step, stp, iges, igs.
  - Per-file size: max 51200 KB (50 MB) via Laravel file rule.
  - Max files per request: 10 (files array max:10).
  - Aggregate size: total of all files <= 50 MB (manual check in withValidator).
- Storage
  - Files are stored on the private local disk under prints/{Y}/{m}/{uuid.ext}.
  - See PrintRequestController::attachFiles() for pathing and stored metadata (disk, path, original_name, mime_type, size_bytes, sha256). Duplicate files within the same request are skipped by sha256.
- Permissions (app/Policies/PrintRequestPolicy.php)
  - view: owner or admin
  - update: owner iff status=pending and not soft-deleted; admin always
  - delete (soft delete): owner iff pending
  - forceDelete: owner on their soft-deleted pending request or admin
  - download: owner or admin
- Status lifecycle (app/Http/Controllers/Admin/PrintRequestStatusController.php)
  - pending -> accepted (records accepted_at)
  - accepted -> printing
  - printing -> complete (records completed_at)
  - revert accepted/printing -> pending (records reverted_at)

Authentication: magic link
- Flow
  - Admin can invite a user and trigger a one-time magic link (10-minute expiry); token hashes stored (never plaintext).
  - Login marks token used_at, updates last_login_at.
- Absolute session window (app/Http/Middleware/EnforceAbsoluteSession.php)
  - Enforced using session.lifetime minutes (absolute, based on last_login_at). When expired, logs out and redirects to route("magic.request").
  - Testing knobs: middleware is skipped in testing unless config(['session.enforce_absolute_in_tests' => true]) or request header X-Force-Absolute is set to force an immediate expiry check.

Notifications and queues
- Local mail: MAIL_MAILER=log (view in storage/logs/laravel.log). Subject prefix is set via config('prints.subject_prefix').
- Queued notifications are expected; when running locally, ensure the queue worker is running (composer run dev includes queue:listen). For tests, queue is sync per phpunit.xml.
- Who is notified
  - Admin: new print request; pending request canceled by user (non-admin owners only).
  - Requester: accepted; reverted to pending; complete.

Artisan commands (routes/console.php)
- auth:invite {email}: create/whitelist user and emit a 10-minute magic link (prints the link in console and sends notification).
- prints:purge-completed-files: delete stored files for requests completed > 90 days; removes file DB rows.
- prints:purge-soft-deleted: permanently delete soft-deleted requests > 90 days and their files.
- prints:warn-soft-deleted: on the 83rd day after soft delete, send a 7-day purge warning (idempotent via cache key per request/day).
- auth:cleanup-magic-tokens: remove expired or used tokens.

Seeding and demo data (database/seeders/DatabaseSeeder.php)
- Seeds admin user (email admin@example.com, is_admin=true, whitelisted).
- Seeds demo users with whitelisted_at.
- Seeds sample PrintRequests across all statuses and stubs a few files to the private local disk.
- Usage: php artisan migrate:fresh --seed (or php artisan db:seed once migrated).

Testing (Pest)
- Run full suite
  - composer test
  - or php artisan test
- Run a single file or test
  - php artisan test tests/Feature/SomeTest.php
  - php artisan test --filter=pattern (uses PHPUnit filter under the hood)
- Environment
  - In-memory SQLite, queue=sync, session=array, mail=array are preconfigured in phpunit.xml.
  - Feature tests auto-use RefreshDatabase via tests/Pest.php.
- Absolute-session testing
  - By default skipped in tests. To enforce absolute session inside a test:
    - config(['session.enforce_absolute_in_tests' => true]);
    - or send header X-Force-Absolute to simulate immediate expiry and redirect.
- Example minimal Pest test (validated in this session)
  - Save as tests/Feature/JunieSmokeTest.php:
    - test('junie smoke passes', function () { expect(true)->toBeTrue(); });
  - Run just this test:
    - php artisan test tests/Feature/JunieSmokeTest.php
  - Remove the file when done to keep the tree clean.

Frontend quality gates
- Lint and format
  - npm run lint (eslint . --fix)
  - npm run format:check / npm run format
- Type checking
  - npm run type-check (vue-tsc)
- PHP style
  - composer run lint:php (Pint in --test mode)
  - composer run lint:php:fix

Dev tips and pitfalls
- Ensure database queue tables exist locally: php artisan queue:table && php artisan migrate. composer run dev will then start queue:listen automatically.
- When running retention commands locally, files are stored on the local disk; ensure paths exist and storage/app/prints is writable. Commands are chunked and resilient to missing files.
- For file uploads, UI should also enforce client-side limits (Inertia/Vue) but server-side rules in StorePrintRequestRequest are the source of truth.
- Status transitions are strictly validated; use the Admin endpoints for lifecycle changes; users cannot edit beyond pending unless admin.
- Secure download routes must consult PrintRequestPolicy::download; do not serve files directly from public disk.

Contributing notes
- Follow existing code style (Pint). Prefer small policy-based authorization checks over inline gate logic.
- Reuse PrintRequestStatus constants rather than string literals.
- When adding new notifications, use config('prints.subject_prefix') for consistent subjects and prefer queueing.
- For new tests, prefer Pest Feature tests and leverage RefreshDatabase from tests/Pest.php. Set specific config values in tests as needed (e.g., enforce absolute session) without altering global config files.
