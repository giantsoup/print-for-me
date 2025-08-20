# Auth-Focused Implementation Plan (3 days)

A detailed, scoped checklist centered on authentication and session security. Derived from docs/tasks.md items 3 and 4, with concrete steps, file paths, acceptance criteria, and tests.

Timebox: 3 days of normal work. Each day builds on the last and includes targeted tests.

## Day 1 — Rate limiting and device/session logging

1. [ ] Magic link rate limiting (per email+IP)
   - [ ] Define a RateLimiter for magic-link requests: key by tuple `${email}|${ip}` with 5 requests/hour.
     - Files: `bootstrap/app.php`
     - Implementation notes: use `RateLimiter::for('magic.send', function (Request $request) {...})` returning `Limit::perHour(5)->by($key)`.
   - [ ] Enforce limiter in route/middleware for POST magic.send.
     - Files: `routes/web.php`
     - Use `->middleware('throttle:magic.send')` on the route definition.
   - [ ] Graceful error message when limited; return generic success UI text to avoid enumeration.
     - Files: `app/Http/Controllers/Auth/MagicLinkController.php`, `resources/js/pages/auth/RequestMagicLink.vue`
     - Acceptance: When exceeding 5/hour for same email and IP, server returns 429 and UI shows a friendly, non-enumerating message.
   - [ ] Tests (Pest): hitting endpoint 6 times with same email+IP returns 429 on the 6th.
     - Files: `tests/Feature/MagicLinkRateLimitTest.php`

2. [ ] Device/session logging on successful login
   - [ ] Database migration: add nullable `last_login_ip` (string 45) and `last_login_user_agent` (text) to users.
     - Files: `database/migrations/xxxx_xx_xx_xxxxxx_add_login_device_fields_to_users_table.php`
     - Acceptance: Schema has the two columns; rollback works.
   - [ ] Set fields on successful magic link login (without failing if nulls).
     - Files: `app/Http/Controllers/Auth/MagicLinkController.php` (login action)
     - Data: IP from `$request->ip()`, UA from `$request->userAgent()`.
   - [ ] Display most recent device info on profile page (optional masked UA).
     - Files: `resources/js/pages/settings/Profile.vue`, a small helper to format user agent (platform/browser if desired).
   - [ ] Tests (Pest): upon login, user columns are updated; profile Inertia response includes those props.
     - Files: `tests/Feature/DeviceLoggingTest.php`

## Day 2 — Session invalidation and signed-link ergonomics

3. [ ] Invalidate all sessions via per-user session_version
   - [ ] Database migration: add unsigned integer `session_version` default 1 to users.
     - Files: `database/migrations/xxxx_xx_xx_xxxxxx_add_session_version_to_users_table.php`
   - [ ] Middleware: compare `auth()->user()->session_version` with `session('sv')`; if mismatch, logout and redirect to magic.request with message. Skip by default in tests unless forced (align with EnforceAbsoluteSession patterns).
     - Files: `app/Http/Middleware/EnforceSessionVersion.php`, `bootstrap/app.php` (register middleware)
   - [ ] On login, write `session(['sv' => $user->session_version])` after `Auth::login()`.
     - Files: `app/Http/Controllers/Auth/MagicLinkController.php`
   - [ ] Add endpoint/button: “Log out of all devices” (increments `session_version`).
     - Files: `routes/web.php` (protected POST route), `app/Http/Controllers/Auth/SessionVersionController.php`, `resources/js/pages/settings/Profile.vue` (button calling POST)
     - Acceptance: After increment, current session is also logged out on next request.
   - [ ] Tests (Pest): authenticated user increments version -> subsequent request is redirected; login writes sv; middleware behavior mirrors absolute-session testing knobs.
     - Files: `tests/Feature/SessionVersionTest.php`

4. [ ] Signed route validation ergonomics
   - [ ] If signature/expiry fails, redirect to `magic.result` with a clear, non-enumerating error.
     - Files: `app/Http/Controllers/Auth/MagicLinkController.php` (login) – catch invalid signature/expired cases and standardize message
   - [ ] Small clock-skew tolerance (±30s) on expiry check using token `expires_at` vs `now()`; keep URL expiry at 10m.
     - Files: `app/Http/Controllers/Auth/MagicLinkController.php` (login), consider validating by DB expiry plus tolerance rather than solely URL signature time.
   - [ ] Tests (Pest): expired by URL but within tolerance and token not expired -> allowed; beyond tolerance -> rejected with error shown on result page.
     - Files: `tests/Feature/MagicLinkSignatureTest.php`

## Day 3 — Token lifecycle hardening and polish

5. [ ] Magic token lifecycle hardening
   - [ ] Unique DB index on `(email, token_hash)` for `magic_login_tokens`.
     - Files: new migration e.g., `database/migrations/xxxx_xx_xx_xxxxxx_add_unique_to_magic_login_tokens.php`
     - Acceptance: Duplicate inserts with same email+hash fail.
   - [ ] Soft-circuit old tokens on new issuance: when creating a new token, mark all prior unused, unexpired tokens for the same email as expired (set `expires_at = now()`).
     - Files: `app/Http/Controllers/Auth/MagicLinkController.php` (store)
     - Acceptance: After requesting a new link, only the newest token remains valid.
   - [ ] Prune stale tokens older than 24h beyond expiry as a safety net (in addition to existing `auth:cleanup-magic-tokens`).
     - Files: `routes/console.php` (extend or new command `auth:purge-stale-magic-tokens`)
   - [ ] Tests (Pest): issuing a token invalidates previous; unique index enforced; prune command deletes expected rows.
     - Files: `tests/Feature/MagicTokenLifecycleTest.php`

6. [ ] Documentation and UX polish
   - [ ] Update README blurb for rate limits and session-version feature.
     - Files: `README.md`
   - [ ] Add short copy on RequestMagicLink.vue about rate limiting (verify values align to 5/hour).
     - Files: `resources/js/pages/auth/RequestMagicLink.vue`

---

## Notes & Best Practices
- Keep error messages consistent and non-enumerating for auth flows.
- Favor policy/middleware over inline checks, mirroring EnforceAbsoluteSession patterns for test knobs.
- Queue behavior remains unchanged; mail uses subject prefix from `config('prints.subject_prefix')`.

## Out of Scope for This Sprint
- Malware scanning hooks, download controller, and CSP headers remain in the broader roadmap.

