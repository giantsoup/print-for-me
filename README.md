# Taylor's Print Services

Passwordless magic‑link authentication, private 3D print file uploads, and a strict request lifecycle built with Laravel 13 + Inertia/Vue 3. Notifications are queued, access is policy‑based, and retention is handled by artisan commands.

This README is developer‑focused. It explains what the project does, how it is structured, and how to get it running locally with accurate, copy‑pasteable commands.

---

## Table of contents
- Project goals and features
- Tech stack and architecture
- Quick start (local setup)
- Running the app
- Testing
- Seeding demo data
- Domain rules (files, storage, permissions, lifecycle)
- Authentication (magic link + absolute session)
- Queues, notifications, and mail
- Useful artisan commands
- Frontend toolchain and quality gates
- Troubleshooting
- Contributing
- License

---

## Project goals and features
- Passwordless magic‑link authentication (invite‑first). Tokens are hashed; links expire in 10 minutes.
- Absolute session window: users must re‑authenticate after a configured lifetime (default 30 days) regardless of activity.
- Print requests with private file uploads and strict validation (types/size/count/aggregate limit).
- Policy‑based authorization for view/update/delete/forceDelete/download.
- Admin‑only status transitions with audit timestamps and a controlled lifecycle.
- Queued notifications to admin and requesters; local development logs mail to file.
- Retention commands to purge old data and warn users before purges.

## Tech stack and architecture
- Backend: Laravel 13 (PHP 8.4)
- Frontend: Inertia.js + Vue 3, built with Vite
- DB: SQLite for local/test by default
- Testing: Pest
- Queues: database queue locally

Key modules and concepts
- Magic link auth tokens are stored as SHA‑256 hashes. The raw token only appears in the email link.
- Files are stored on a private disk under prints/{Y}/{m}/{uuid.ext}; downloads are authorized via controller/policies (never served publicly).
- Status constants live in App\Enums\PrintRequestStatus to avoid string typos.
- Access rules are centralized in App\Policies\PrintRequestPolicy.

---

## Quick start (local setup)
Prerequisites
- PHP 8.4 with required extensions
- Node.js (18+ recommended) and npm

Steps
1. Install dependencies
   ```bash
   composer install
   npm install
   ```
   Or use the combined bootstrap script:
   ```bash
   composer run setup
   ```
2. Create and configure .env for local
   ```dotenv
   DB_CONNECTION=sqlite
   # Create the SQLite DB file:
   #   touch database/database.sqlite

   MAIL_MAILER=log
   QUEUE_CONNECTION=database

   APP_NAME="Taylor's Print Services"
   APP_URL=https://print-for-me.test

   # Absolute session window uses this value in minutes. 43200 minutes = 30 days.
   SESSION_LIFETIME=43200

   # Private local storage for uploaded files
   FILESYSTEM_DISK=local
   ```
   Create the empty database file:
   ```bash
   touch database/database.sqlite
   ```
3. Bootstrap the app
   ```bash
   php artisan key:generate
   php artisan queue:table
   php artisan migrate
   ```

---

## Running the app
- One command with concurrent services (PHP server, queue worker, Laravel Pail logs, and Vite):
  ```bash
  composer run dev
  ```
- Alternatively, run them separately:
  ```bash
  php artisan serve
  php artisan queue:listen --tries=1
  php artisan pail --timeout=0
  npm run dev
  ```
- Visit http://127.0.0.1:8000 (or your APP_URL if using Valet).

---

## Testing
The project uses Pest. Test environment is preconfigured for in‑memory SQLite, sync queue, array session/mail.

- Run the full suite
  ```bash
  composer test
  # or
  php artisan test
  ```
- Run a specific test or filter
  ```bash
  php artisan test tests/Feature/SomeTest.php
  php artisan test --filter="pattern"
  ```
- Absolute‑session testing knob
  - By default, EnforceAbsoluteSession middleware is skipped in tests.
  - To enforce it within a specific test:
    ```php
    // Inside your test
    config(['session.enforce_absolute_in_tests' => true]);
    // or send the header to force immediate expiry check
    $response = $this->withHeader('X-Force-Absolute', '1')->get('/some/route');
    ```

---

## Seeding demo data
Seeders provide an admin user, a couple of demo users, and sample print requests across statuses.

- Create from scratch and seed
  ```bash
  php artisan migrate:fresh --seed
  ```
What gets seeded (summary)
- Admin user: admin@example.com (is_admin=true, whitelisted)
- Demo users with whitelisted_at
- Sample PrintRequests in pending/accepted/printing/complete
- A few stub files in private storage under prints/{Y}/{m}/...

---

## Domain rules
### File uploads and validation
- At least one source is required: a source_url or ≥ 1 uploaded file
- Allowed extensions: stl, 3mf, obj, f3d, f3z, step, stp, iges, igs
- Per‑file size: ≤ 50 MB
- Max files per request: 10
- Aggregate size across all files: ≤ 50 MB

### Storage model
- Files are stored on the private local disk: prints/{Y}/{m}/{uuid.ext}
- On attach, SHA‑256 is computed; duplicates for the same request are skipped
- Secure downloads are served via controller; direct public access is not used

### Permissions (policy‑based)
- view: owner or admin
- update: owner if status = pending and not soft‑deleted; admin always
- delete (soft delete): owner if pending
- forceDelete: owner on their soft‑deleted pending request or admin
- download: owner or admin

### Status lifecycle (admin endpoints)
- pending -> accepted (records accepted_at)
- accepted -> printing
- printing -> complete (records completed_at)
- revert accepted/printing -> pending (records reverted_at)

---

## Authentication: magic link + absolute session
- Invite flow
  - Admin invites a user, which creates/whitelists the user and emits a one‑time magic link (10‑minute expiry)
  - Token hashes are stored; raw token is only sent in the link
- Login flow
  - Verifies the email+token, marks token used_at, and updates last_login_at
- Absolute session window
  - Middleware checks last_login_at against the absolute lifetime in minutes (session.lifetime)
  - When expired, the user is logged out and redirected to the magic link request page
- Rate limiting
  - Magic link requests are limited to 5 per hour per email and IP address. If you exceed the limit, the server responds with HTTP 429 and the UI shows a friendly, non‑enumerating message.
- Session version invalidation ("Log out of all devices")
  - On successful login we record a per‑user session version in the session. Using the profile page action to log out of all devices increments the session version and forces all existing sessions (including the current one) to be logged out on the next request.

Routes used in this flow include the “request magic link” page and the signed login handler (e.g., magic.request and magic.login).

---

## Queues, notifications, and mail
- Local mail is written to storage/logs/laravel.log (MAIL_MAILER=log)
- Subject prefix: [Taylor’s Print Services] (config/prints.php)
- Notifications are queued; keep the queue worker running locally (composer run dev starts queue:listen)

Who gets notified
- Admin: new print request; pending request canceled by user (owner only)
- Requester: when a request is accepted; reverted to pending; completed

---

## Useful artisan commands
- Invite a user and send a 10‑minute magic link
  ```bash
  php artisan auth:invite user@example.com
  ```
- Purge stored files for completed requests older than 90 days
  ```bash
  php artisan prints:purge-completed-files
  ```
- Permanently delete soft‑deleted requests older than 90 days (and their files)
  ```bash
  php artisan prints:purge-soft-deleted
  ```
- Send 7‑day purge warnings for soft‑deleted requests on the 83rd day
  ```bash
  php artisan prints:warn-soft-deleted
  ```
- Cleanup expired or used magic login tokens
  ```bash
  php artisan auth:cleanup-magic-tokens
  ```

---

## Frontend toolchain and quality gates
- Dev server: `npm run dev` (Vite HMR)
- Builds: `npm run build` or `npm run build:ssr`
- Lint/format: `npm run lint`, `npm run format:check`, `npm run format`
- Type checking: `npm run type-check` (vue-tsc)
- PHP style: `composer run lint:php` (Pint in test mode) and `composer run lint:php:fix`

---

## Troubleshooting
- “Queue tables do not exist”
  - Run `php artisan queue:table && php artisan migrate`
- “Emails are not sending”
  - In local, mail is logged to storage/logs/laravel.log; set MAIL_MAILER appropriately
- “Files not found / downloads fail”
  - Ensure FILESYSTEM_DISK=local and that `storage/app/prints` is writable; downloads are authorized and not public
- “Absolute session logout during tests”
  - By default it’s skipped; either set `config(['session.enforce_absolute_in_tests' => true])` or send `X-Force-Absolute` header to simulate expiry

---

## Contributing
- Follow the existing coding style (Laravel Pint). Prefer policy checks over inline authorization logic.
- Reuse status constants from `App\Enums\PrintRequestStatus` instead of string literals.
- For new notifications, use `config('prints.subject_prefix')` and prefer queueing.
- Frontend: keep client‑side validations in sync with server‑side rules.

---

## License
MIT (see composer.json)
