# Improvement Tasks Checklist

A logically ordered, actionable checklist to enhance architecture, security, performance, maintainability, DX, and UX across the application. Check items off as completed.

See also: Auth-focused plan in docs/tasks-auth.md for a 2–3 day implementation scope.

1. [ ] Repository hygiene and CI/CD
   - [ ] Add a PHP + Node CI workflow (GitHub Actions) to run: composer install, npm ci, php artisan test, vendor/bin/pint --test, npm run type-check, npm run lint. Ensure database is SQLite for tests. (files: .github/workflows/ci.yml)
   - [ ] Add dependabot or Renovate for composer and npm ecosystem updates. (files: .github/dependabot.yml or renovate.json)
   - [ ] Enforce PR status checks (tests, lint, type-check) in repository settings.

2. [ ] Environment and configuration hardening
   - [ ] Validate required env vars at boot using config assertions (e.g., admin email default is fine locally but should be overridden in production). Consider a HealthServiceProvider or bootstrap check. (files: bootstrap/providers.php or new provider)
   - [ ] Ensure MAIL_FROM_ADDRESS and MAIL_FROM_NAME are set and surfaced in config/mail.php for consistent sender identity.
   - [ ] Add APP_ENV-specific config guidance to README for production (queue, cache, mail, logs, filesystems).

3. [ ] Authentication and session security
   - [ ] Add rate limiting for magic link requests store() using Laravel’s RateLimiter keyed by email and ip (e.g., 5 per hour per tuple). (files: app/Http/Controllers/Auth/MagicLinkController.php, bootstrap/app.php rate limit definitions)
   - [ ] Add device/session logging (last_login_ip, last_login_user_agent) to User and set on successful login; show recent device on profile page. (files: database migration + app/Http/Controllers/Auth/MagicLinkController.php, resources/js/pages/settings/Profile.vue)
   - [ ] Add ability to invalidate all sessions server-side by rotating a session version on User and adding custom auth guard check. (middleware or auth provider extension)
   - [ ] Add signed route protection already used; additionally validate signature expiry clock skew tolerance and clear messages. (files: routes and MagicLinkController@login)

4. [ ] Magic token lifecycle and resilience
   - [ ] Add unique database index on (email, token_hash) to prevent duplicates and speed lookups. (files: magic_login_tokens migration)
   - [ ] Add pruning scheduler or command to purge tokens older than 24h beyond expiry as a safeguard (in addition to auth:cleanup-magic-tokens). (files: routes/console.php or new command)
   - [ ] Add soft-circuit on multiple concurrent valid tokens: when issuing a new token for an email, mark older un-used tokens expired. (files: MagicLinkController@store)

5. [ ] Authorization consistency and controller cleanup
   - [ ] Remove dead private helpers authorizeOwnerOrAdmin() and ensureEditableByUser() from PrintRequestController in favor of policy methods already in use, or refactor call sites to use them consistently. (files: app/Http/Controllers/PrintRequestController.php)
   - [ ] Replace manual status string checks in controllers with request classes or dedicated service methods for transitions to centralize validation. (files: Admin/PrintRequestStatusController.php, new Form Requests or domain service)
   - [ ] Add explicit authorization checks for admin-only transition endpoints via route middleware or gates (e.g., can:admin). (files: routes/web.php, app/Policies or middleware)

6. [ ] Validation robustness for file uploads
   - [ ] Add explicit max:2048 rule to instructions field to reduce payload size if needed; confirm business cap (currently 5000). (files: StorePrintRequestRequest.php and UpdatePrintRequestRequest.php)
   - [ ] Ensure UpdatePrintRequestRequest enforces the same files rules and aggregate size as StorePrintRequestRequest. (files: app/Http/Requests/UpdatePrintRequestRequest.php)
   - [ ] Enforce disallowing empty update when both removing all files and clearing source_url, unless admin. (files: UpdatePrintRequestRequest + controller)
   - [ ] Add server-side mime-type sniffing or validation using Laravel’s file validation with mimetypes if required for stricter security; log mismatches. (files: Store/Update requests)

7. [ ] File storage and download security
   - [ ] Add a dedicated download controller action that streams files via Storage::disk()->response() with correct content disposition, after policy check; ensure no path traversal. (files: PrintRequestFileController.php)
   - [ ] Add checksum verification on download (optional header) or after upload to assert file integrity; store size/sha256 already exists – use them for integrity checks. (files: PrintRequestController@attachFiles, file model)
   - [ ] Ensure private disk path prefixes are configurable via config/prints.php and not hard-coded. (files: config/prints.php, controller attach)
   - [ ] Add virus/malware scanning hook (pluggable job) integration point for uploads; make it queue-based and mark file as quarantined until cleared. (files: new job + flag on PrintRequestFile)

8. [ ] Domain model enhancements
   - [ ] Add database constraints: foreign keys between print_request_files and print_requests; on delete cascade. (files: migrations)
   - [ ] Add composite unique on (print_request_id, sha256) to enforce duplicate-skip invariant at DB level. (files: print_request_files migration)
   - [ ] Add enumerated constraint for status column or use PHP backed enum + cast for stronger typing. (files: PrintRequest model/migration)
   - [ ] Add scope methods on PrintRequest (e.g., scopeOwnedBy, scopeStatus) to simplify query logic in controllers. (files: app/Models/PrintRequest.php)

9. [ ] Notifications and queue resilience
   - [ ] Wrap queued notification dispatches in try/catch with logging and fallback to sync in emergencies via config toggle (e.g., prints.notifications_sync_fallback). (files: controllers; config/prints.php)
   - [ ] Add notification rate limit per request state change to avoid duplicate sends (idempotency key in cache). (files: Admin/PrintRequestStatusController.php)
   - [ ] Ensure queue:failed handling documentation and add a retry/backoff strategy to notification classes. (files: app/Notifications/*.php)

10. [ ] Retention commands hardening and observability
   - [ ] Add verbose logging with progress (per-chunk) and summary metrics emitted to logs with context (request ids, counts). (files: routes/console.php)
   - [ ] Emit events for purge actions to allow future observability hooks. (files: events + dispatch from commands)
   - [ ] Add dry-run option to retention commands (prints:purge-*) for safer operations in production. (files: routes/console.php or migrate to Command classes)
   - [ ] Add integration test coverage for missing-file paths and error counts (extend existing RetentionCommandsTest). (files: tests/Feature/RetentionCommandsTest.php)

11. [ ] Performance and scalability
   - [ ] Add indexes: status, user_id on print_requests; (print_request_id) on print_request_files if missing. (files: migrations)
   - [ ] Convert N+1-prone views to eager-load precise columns and counts (e.g., withCount('files') for index list). (files: PrintRequestController@index, Vue table)
   - [ ] Paginate with simplePagination for large datasets where count() is expensive (admin list). (files: PrintRequestController@index)
   - [ ] Consider offloading large file uploads to chunked uploader and direct-to-storage approach if uploads grow. (future enhancement; doc only)

12. [ ] Error handling and UX
   - [ ] Standardize API error shapes and map to front-end toasts/messages; ensure ValidationException messages are user-friendly. (files: exceptions handler + Vue pages)
   - [ ] Provide clear feedback on file validation failures (aggregate too large, invalid types) on Create/Show pages with client-side mirrors of server constraints. (files: resources/js/pages/prints/*.vue)
   - [ ] Add global error boundary/flash component in Inertia layout for consistent messaging. (files: resources/js/components)

13. [ ] Frontend quality and consistency
   - [ ] Add shared TS types for PrintRequest and PrintRequestFile in a central types file rather than redefining local interfaces. (files: resources/js/types/domain.ts)
   - [ ] Extract status chip rendering into a small component to avoid duplication across pages. (files: resources/js/components/StatusChip.vue)
   - [ ] Ensure tailwind v4 classes are consistently used; remove any deprecated utilities; add dark mode parity across pages. (files: Vue pages)
   - [ ] Add lazy-loading/deferred props for heavy lists (Inertia v2 feature) and skeleton loading states. (files: prints/Index.vue)
   - [ ] Add prefetch on links for common navigations (Inertia v2). (files: Vue links)

14. [ ] Testing improvements (Pest)
   - [ ] Add feature tests for PrintRequest lifecycle transitions authorization (admin vs user) covering forbidden paths. (files: tests/Feature/AdminStatusTransitionsTest.php)
   - [ ] Add file validation tests for aggregate size, per-file size, and allowed extensions, including weird edge cases (empty array, nulls). (files: tests/Feature/PrintRequestsTest.php)
   - [ ] Add tests for magic link anti-bot heuristics (honeypot, min fill-time) ensuring they are no-ops in tests unless provided. (files: tests/Feature/MagicLinkTest.php)
   - [ ] Add tests for absolute session middleware enforcement toggles: default off in tests, config on, header forced. (files: tests/Feature/AbsoluteSessionMiddlewareTest.php)
   - [ ] Add tests asserting duplicate file hash within same request is skipped and DB unique constraint enforces it. (files: new test)

15. [ ] Developer experience and tooling
   - [ ] Add artisan make:stubs or dedicated command aliases for common tasks (e.g., inviting users). (files: routes/console.php or custom commands)
   - [ ] Add IDE helper generation (barryvdh/laravel-ide-helper) if team prefers, behind dev-only require. (composer.json dev)
   - [ ] Add Pint pre-commit hook via Husky or a simple git hook script; optionally add lint-staged for Vue formatting. (files: package.json, .husky/)

16. [ ] Documentation
   - [ ] Expand README with production deploy notes (queues, supervisor/systemd, APP_URL/SSL, storage:link not used for private files). (files: README.md)
   - [ ] Document status transition API endpoints and example requests/responses for admin usage. (files: README or docs/admin-status-api.md)
   - [ ] Add troubleshooting for common upload issues (max upload size php.ini/post_max_size), queue worker tips, and mail transport.

17. [ ] Observability and logging
   - [ ] Standardize log channels and contexts for auth, uploads, transitions, and retention commands; add structured context (request id, user id). (files: logging.php config + usage)
   - [ ] Add basic health checks endpoint and route (e.g., /healthz) verifying DB connectivity and queue status (read-only). (files: routes/api.php + controller)
   - [ ] Optionally add Laravel Telescope in local/dev for deeper visibility; ensure disabled in production. (composer dev + providers)

18. [ ] Security posture review
   - [ ] Ensure all file downloads are always authorized through policy; add audit log for each download event with user id and IP. (files: PrintRequestFileController)
   - [ ] Verify CSRF protection on all state-changing routes; confirm Inertia uses POST/PATCH/DELETE with tokens. (routes + middleware)
   - [ ] Add Content Security Policy headers via middleware for safer frontend (script-src 'self' with Vite dev exceptions). (files: new middleware + bootstrap/app.php)

19. [ ] Data lifecycle and GDPR considerations
   - [ ] Provide user-facing data export of their print requests and metadata (JSON export). (files: new controller + route + policy check)
   - [ ] Provide user-controlled purge request for their soft-deleted items immediately, within policy constraints. (existing forceDelete path wiring in UI)

20. [ ] Housekeeping
   - [ ] Remove any compiled SSR artifacts from bootstrap/ssr/assets from version control or ensure they are build outputs ignored via .gitignore. (files: .gitignore, repo cleanup)
   - [ ] Ensure storage/logs and other runtime directories are in .gitignore and not committed.
