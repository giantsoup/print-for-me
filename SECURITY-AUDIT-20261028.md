# Laravel security audit, 2026-10-28

## Scope and result

Repository: `giantsoup/print-for-me`, public GitHub repository. Base: `main` at `760be0040aa0456f6821a52710187fb3540b2bfb`, fetched on 2026-10-28. Audit branch: `codex/security-audit-20261028-a1`. The audit used an isolated managed worktree. The original checkout, its six modified files, its divergent local commits, and pre-existing worktrees were preserved.

Three confirmed authentication findings are fixed. One confirmed high-severity source-preview finding remains deferred. This is not a clean security result. Review the deferred finding before merge or production activation. Nothing was merged or deployed by this audit.

Discovered stack: PHP 8.5.12, Laravel 13.39.0, Inertia Laravel 2.0.24, Vue 3, Inertia Vue 2, Tailwind 4, Ziggy 2, Pest 4.7.8, PHPUnit 12.5.33, Node 26.13.0, npm 12.2.1. Authentication uses the session-backed Eloquent `User` provider and invite-only magic links, with admin and owner policies, absolute session expiry, and session-version invalidation. Persistence includes database-backed jobs, sessions, cache, private local uploads, public local storage, and an unused S3 example configuration. No API token provider, GraphQL endpoint, broadcast channel, webhook, payment integration, or search service was found.

## Fixed findings

### AUTH-01: magic-link send limit could be bypassed by email case changes

**Confirmed, medium severity.** An unauthenticated caller could exceed the intended five-per-hour account/IP issuance limit. The limiter keyed raw input while account lookup normalized it. This allowed excess mail and repeated replacement of login links for an invited account. It did not by itself disclose a login token or authenticate an attacker.

Changed `app/Providers/AppServiceProvider.php:27` to use the same lowercase and trim normalization as issuance. The intended rule comes from the existing limiter and `MagicLinkRateLimitTest`. The new test sends valid variants of one synthetic account, verifies the sixth request is denied, checks notification and token counts, and permits another account.

Regression: `SecurityAuditTest.php:31`. Original behavior returned 302 on the sixth request. Corrected behavior returns 429 and produces only five notifications and tokens.

### AUTH-02: explicit token invalidation did not override expiry grace

**Confirmed, medium severity.** Replaced links remained redeemable briefly. Access revocation followed by restoration, or an administrative email change away and back, could also revive an explicitly invalidated link during the grace window. CLI invitations did not invalidate prior links. Exposure requires possession of an earlier valid link. This is not a token-guessing or unrestricted account-takeover finding.

The existing lifecycle test establishes that issuing another link expires its predecessor. Administrative revocation, reinvitation, and email-change callers already explicitly invalidate tokens. `MagicLoginToken::invalidateUnusedForEmail`, at `app/Models/MagicLoginToken.php:31`, now expires all still-eligible unused links beyond the shared 30-second grace cutoff. All issuance and revocation callers use it, including the CLI invitation. Natural clock-skew tolerance remains in place. No schema or dependency change was needed.

Regressions cover replacement, revoke/restore, an already naturally expired link within grace, email changes, administrator invitations, and CLI invitations. Each includes a valid replacement-link control. See `SecurityAuditTest.php:48`, `:64`, `:127`, `:146`, and `:225`. Existing signature-tolerance tests also pass.

### AUTH-03: token consumption was vulnerable to an interleaving replay

**Confirmed, medium severity.** Selection of an unused token and marking it used were separate unchecked operations. Two requests that selected the same token could both authenticate, contrary to the one-sign-in promise in the magic-link email.

Changed `app/Http/Controllers/Auth/MagicLinkController.php:170` to claim the token with one conditional database update that still requires an unused, eligible token. Authentication proceeds only if exactly one row was updated.

Regression: `SecurityAuditTest.php:105` introduces a deterministic database interleaving after selection, without mocking authentication or authorization. The original code authenticated despite the intervening consumption. The corrected code rejects the second claimant and preserves the original consumption timestamp. This proves the application decision for that interleaving, not production database concurrency or locking behavior.

## Deferred finding

### NET-01: source-preview transport needs a complete network boundary

**Confirmed, high severity. Deferred.** An invited user's source-preview workflow can retrieve content beyond the intended public-network boundary. A synthetic HTTP integration test demonstrated out-of-policy retrieval through the actual submission, queue, fetch, and persistence path. Every response was faked and stray requests were blocked. No internal service, live application endpoint, or production data was contacted.

Relevant component: `app/Support/SourcePreviewFetcher.php:13` and its queued and administrative callers. Detailed synthetic evidence is retained outside Git in the local audit evidence directory. This public report deliberately excludes a payload, destination, or exploitation procedure.

A complete correction needs an agreed destination policy, public-address pinning through connection establishment, validation throughout permitted redirect handling, and streamed response limits. The current response truncation occurs after buffering, so it is not a transport-size bound. DNS changes during a request and oversized responses are source-review concerns, not separately demonstrated live attacks.

This is deferred because changing the external-fetch transport and redirect behavior affects supported preview workflows and needs broader validation. A partial transport change would leave related paths unresolved. Recommended acceptance checks are synthetic tests for out-of-policy destinations across the request lifetime, approved direct and redirected previews, DNS changes, redirect limits, unsupported schemes, timeouts, and bounded response bytes. Include all automatic, refetch, and administrative test callers. Review deployment egress restrictions separately. No dependency upgrade or production configuration change was made.

## Execution safety and checks

A fresh synthetic environment was created without copying the development environment or data. The primary database was SQLite `:memory:`. Cache, session, and mail used array stores. Logs used the null channel. Storage belonged to the audit checkout and upload tests used local fakes. No external storage credentials were configured or used. Queue work was synchronous with controlled HTTP responses, or explicitly faked in dispatch tests. Startup providers and commands were reviewed before boot. No cached configuration or SSR bundle was inherited. Inertia SSR is disabled in the test bootstrap and Laravel HTTP stray-request prevention is now enabled for every test application.

Commands below used the Herd PHP 8.5 binary for every PHP entry point. `php85` denotes `/Users/Taylor/Library/Application Support/Herd/bin/php85`; Composer was also invoked through that binary.

| Check | Result |
| --- | --- |
| Original application code with the 16 new audit scenarios | 8 failed, 8 passed, 151 assertions. Original code was temporarily substituted only in run-owned files and restored in `finally`. |
| `php85 artisan test --compact --no-ansi` after final application edits | 157 passed, 1,181 assertions, no failures or skips. Includes the 16 new audit scenarios. |
| Focused committed audit scenarios | 16 passed, 212 assertions. |
| Restricted synthetic NET-01 scenario against original transport | 1 passed, 3 assertions, confirming the finding. No live transport. |
| `php85 vendor/bin/pint --dirty --format agent` | Passed after formatting. |
| `npm run build` | Passed. |
| `npm run type-check` | Passed. |
| `npm run lint:check` | Passed. |
| `npm run format:check` | Passed. |
| Composer `audit --locked --format=json` | No advisories or abandoned packages reported, including development packages. |
| `npm audit --package-lock-only --json --ignore-scripts` | Zero reported advisories, including development packages. |
| `git diff --check` | Passed. |
| Secret review | Manual outgoing-diff review plus bounded redacted patterns over the tracked snapshot. No confirmed secret found. No dedicated secret scanner was installed. Full historical scanning was not performed. |

Initial wider tests failed because the fresh worktree lacked built Vite assets. After the build, one mail assertion exposed a synthetic application-name mismatch. The audit environment was corrected to the application's expected name, then the full suite passed. These were environment setup failures, not hidden skips or application fixes.

## Route and permission coverage

Runtime inventory used the installed `route:list --help`, JSON output, and verbose output. Resolving the HTTP kernel before gathering middleware supplied expanded middleware classes. Source review added conditional registrations. Testing and production-mode boot with debug disabled each register 55 definitions and 83 route-method rows. Local-mode boot adds one development logging route, for 56 definitions and 84 rows in the union. All domains are unrestricted in route definitions. These are isolated configuration runs, not observations of production's effective environment or cached routes.

All 84 route-method rows have an access disposition. The 16 committed audit scenarios exercise 34 rows. The restricted local NET-01 test exercises one additional row, for 35 runtime-exercised rows and 49 source-only rows. This count splits methods including HEAD and all eight methods on the settings redirect. Existing tests exercise additional behavior, but their exact route-method coverage was not measured and is not added to this count. Some pre-existing tests disable session checks or middleware. The new authorization scenarios retain real authentication, binding, policies, admin middleware, and both enforced session checks. Laravel's test environment still bypasses CSRF. Browser CSRF behavior is source-reviewed only.

All 25 administrative actions reject both guests and authenticated non-admins in the new tests, with database and notification checks for side effects. Permitted controls come from the existing admin tests and the new invitation, email-change, access-change, file, photo, and owner scenarios. A deny result alone is not treated as proof of every action's complete behavior.

The complete local matrix records domain, method, path, name, action, guard, expanded middleware, audience, record and field scope, sensitive data, expected allow/deny cases, rule source, and evidence state. The compact public matrix below references the following rules.

| Rule | Audience, scope, sensitive data and expected behavior | Source |
| --- | --- | --- |
| Public / Public health | Guests may see the landing page or health response. No private request collection. Home redirects signed-in members. | `routes/web.php`, `bootstrap/app.php` |
| Magic UI | Guest login, request and result pages. Only session feedback and current identity props. | `routes/auth.php`, auth controllers |
| Issue | Active invited email only, normalized account/IP throttle, token hash stored, link mailed. Invalid account denied. | `MagicLinkController::store`, `User::hasActiveAccess`, limiter and tests |
| Redeem | Correct signed, eligible, single-use token for an active account. Invalid, consumed, superseded or revoked links denied. | `MagicLinkController::login`, magic-link tests, mail one-sign-in promise |
| Self auth / Verify | Authenticated current identity only. Verification checks signed URL and matching ID/hash. Logout or session invalidation affects self. | `routes/auth.php`, verification Form Request, `SessionVersionController` |
| Self profile | Authenticated current identity only. Name and unique lowercase email allowed. Privileged fields excluded. Deletion requires current password. | `routes/settings.php`, `ProfileUpdateRequest`, profile controller and tests |
| Dashboard / List | Member's own records and counts. Admin sees all. Query filters cannot widen member scope. | `DashboardController`, `PrintRequestController::index`, tests |
| Create | Authenticated user creates under own identity with pending status. Input cannot assign owner/status or preview data. Count, aggregate size and file-type rules apply. | Store Form Request, controller and tests |
| View | Owner or admin, including soft-deleted requests. Other owners and guests denied. | `PrintRequestPolicy::view`, scoped routes |
| Edit | Owner only on live pending requests, or admin on live requests. Needed-by date remains pending-only. File removals stay inside the request relation. | Policy, Update Form Request, controller, tests |
| Delete / Restore / Purge | Soft delete: live pending owner or admin. Restore: admin on deleted request. Purge: admin, or owner of a deleted pending request. Private assets follow the authorized request. | `PrintRequestPolicy`, delete service, controller and tests |
| File / Photo | Owner or admin plus matching asset parent. Cross-owner and mismatched-parent requests denied. Reads use private storage. | File/photo controllers, policy and tests |
| Admin | Session-authenticated administrator, all-record scope. User writes use policy/Form Requests, validated fields and lifecycle/self/last-admin checks. Request actions use allowed statuses. Preview tests restrict the chosen domain. | `routes/admin.php`, `EnsureAdmin`, User policy, admin controllers and tests |
| Capability | Relative path/verb signature required for private local file delivery or upload. No application capability issuer was found. Unsigned reads/writes denied; signed read control passed. | Installed filesystem provider, `ServeFile`, `ReceiveFile`, local disk config |
| Dev tool | Browser log ingestion only while Boost is enabled under local/debug conditions. No route in the reviewed production-mode/debug-disabled inventory. Production install excludes dev packages. | Installed Boost provider, deploy workflow |

T means directly exercised by the new audit tests, including the restricted local NET-01 test. S means source-reviewed without a claimed dedicated audit runtime scenario. Guard and expanded middleware details are in the local matrix. Every application web route uses the web middleware group. Protected print/settings/admin routes additionally use auth, absolute-session and session-version middleware. Admin routes add admin enforcement. Verification routes use auth and signed/throttle checks where specified. Capability and health routes have no web middleware.

| Path | Method disposition | Route / action | Access rule |
| --- | --- | --- | --- |
| `up` | GET S, HEAD S | `up` / `Illuminate\Foundation\Http\HealthCheck` | Public health |
| `/` | GET S, HEAD S | `home` / `Closure` | Public |
| `dashboard` | GET S, HEAD S | `dashboard` / `DashboardController` | Dashboard |
| `settings` | GET S, HEAD S, POST S, PUT S, PATCH S, DELETE S, OPTIONS S, QUERY S | `settings` / `\Illuminate\Routing\RedirectController` | Self profile |
| `settings/profile` | GET S, HEAD S | `profile.edit` / `Settings\ProfileController@edit` | Self profile |
| `settings/profile` | PATCH S | `profile.update` / `Settings\ProfileController@update` | Self profile |
| `settings/profile` | DELETE S | `profile.destroy` / `Settings\ProfileController@destroy` | Self profile |
| `settings/sessions/invalidate` | POST S | `sessions.invalidate` / `Auth\SessionVersionController@store` | Self auth |
| `settings/appearance` | GET S, HEAD S | `appearance` / `Closure` | Self profile |
| `login` | GET S, HEAD S | `login` / `Auth\AuthenticatedSessionController@create` | Magic UI |
| `magic-login/request` | GET S, HEAD S | `magic.request` / `Auth\MagicLinkController@create` | Magic UI |
| `magic-login/send` | POST T | `magic.send` / `Auth\MagicLinkController@store` | Issue |
| `magic-login` | GET T, HEAD S | `magic.login` / `Auth\MagicLinkController@login` | Redeem |
| `magic-login/result` | GET S, HEAD S | `magic.result` / `Auth\MagicLinkController@result` | Magic UI |
| `verify-email` | GET S, HEAD S | `verification.notice` / `Auth\EmailVerificationPromptController` | Self auth |
| `verify-email/{id}/{hash}` | GET S, HEAD S | `verification.verify` / `Auth\VerifyEmailController` | Verify |
| `email/verification-notification` | POST S | `verification.send` / `Auth\EmailVerificationNotificationController@store` | Self auth |
| `logout` | POST S | `logout` / `Auth\AuthenticatedSessionController@destroy` | Self auth |
| `print-requests` | GET T, HEAD S | `print-requests.index` / `PrintRequestController@index` | List |
| `print-requests/create` | GET S, HEAD S | `print-requests.create` / `PrintRequestController@create` | Create |
| `print-requests` | POST T | `print-requests.store` / `PrintRequestController@store` | Create |
| `print-requests/{print_request}` | GET T, HEAD S | `print-requests.show` / `PrintRequestController@show` | View |
| `print-requests/{print_request}` | PATCH T | `print-requests.update` / `PrintRequestController@update` | Edit |
| `print-requests/{print_request}` | DELETE S | `print-requests.destroy` / `PrintRequestController@destroy` | Delete |
| `print-requests/{print_request}/restore` | PATCH S | `print-requests.restore` / `PrintRequestController@restore` | Restore |
| `print-requests/{id}/force` | DELETE S | `print-requests.force-destroy` / `PrintRequestController@forceDestroy` | Purge |
| `print-requests/{print_request}/files/{file}/download` | GET T, HEAD S | `print-requests.files.download` / `PrintRequestFileController@download` | File |
| `print-requests/{print_request}/completion-photos/{photo}` | GET T, HEAD S | `print-requests.completion-photos.show` / `PrintRequestCompletionPhotoController@show` | Photo |
| `admin/invite` | GET T, HEAD S | `admin.invite.create` / `Admin\InviteController@create` | Admin |
| `admin/invite` | POST T | `admin.invite.store` / `Admin\InviteController@store` | Admin |
| `admin/users` | GET T, HEAD S | `admin.users.index` / `Admin\UserController@index` | Admin |
| `admin/users/{user}` | GET T, HEAD S | `admin.users.show` / `Admin\UserController@show` | Admin |
| `admin/users/{user}` | PATCH T | `admin.users.update` / `Admin\UserController@update` | Admin |
| `admin/users/{user}/invite` | POST T | `admin.users.invite` / `Admin\UserController@invite` | Admin |
| `admin/users/{user}/access/revoke` | POST T | `admin.users.access.revoke` / `Admin\UserController@revokeAccess` | Admin |
| `admin/users/{user}/access/restore` | POST T | `admin.users.access.restore` / `Admin\UserController@restoreAccess` | Admin |
| `admin/users/{user}/sessions/invalidate` | POST T | `admin.users.sessions.invalidate` / `Admin\UserController@invalidateSessions` | Admin |
| `admin/users/{user}/role/promote` | POST T | `admin.users.role.promote` / `Admin\UserController@promote` | Admin |
| `admin/users/{user}/role/demote` | POST T | `admin.users.role.demote` / `Admin\UserController@demote` | Admin |
| `admin/users/{user}` | DELETE T | `admin.users.destroy` / `Admin\UserController@destroy` | Admin |
| `admin/users/{user}/restore` | POST T | `admin.users.restore` / `Admin\UserController@restore` | Admin |
| `admin/users/{user}/purge` | DELETE T | `admin.users.purge` / `Admin\UserController@purge` | Admin |
| `admin/print-requests/{print_request}/accept` | PATCH T | `admin.print-requests.accept` / `Admin\PrintRequestStatusController@accept` | Admin |
| `admin/print-requests/{print_request}/printing` | PATCH T | `admin.print-requests.printing` / `Admin\PrintRequestStatusController@printing` | Admin |
| `admin/print-requests/{print_request}/complete` | PATCH T | `admin.print-requests.complete` / `Admin\PrintRequestStatusController@complete` | Admin |
| `admin/print-requests/{print_request}/revert` | PATCH T | `admin.print-requests.revert` / `Admin\PrintRequestStatusController@revert` | Admin |
| `admin/print-requests/{print_request}/notifications/completed/resend` | POST T | `admin.print-requests.notifications.completed.resend` / `Admin\PrintRequestStatusController@resendCompletedNotification` | Admin |
| `admin/print-requests/{print_request}/notifications/completed/send-test` | POST T | `admin.print-requests.notifications.completed.send-test` / `Admin\PrintRequestStatusController@sendCompletedNotificationPreview` | Admin |
| `admin/print-requests/{print_request}/source-preview/refetch` | POST T | `admin.print-requests.source-preview.refetch` / `Admin\PrintRequestSourcePreviewController` | Admin |
| `admin/source-preview-domains` | GET T, HEAD S | `admin.source-preview-domains.index` / `Admin\SourcePreviewDomainController@index` | Admin |
| `admin/source-preview-domains/{source_preview_domain}` | PATCH T | `admin.source-preview-domains.update` / `Admin\SourcePreviewDomainController@update` | Admin |
| `admin/source-preview-domains/{source_preview_domain}/attempt` | POST T | `admin.source-preview-domains.attempt` / `Admin\SourcePreviewDomainController@attempt` | Admin |
| `admin/source-preview-domains/{source_preview_domain}/attempt-url` | POST T | `admin.source-preview-domains.attempt-url` / `Admin\SourcePreviewDomainController@attemptUrl` | Admin |
| `storage/{path}` | GET T, HEAD S | `storage.local` / `Closure` | Capability |
| `storage/{path}` | PUT T | `storage.local.upload` / `Closure` | Capability |
| `_boost/browser-logs` | POST S | `boost.browser-logs` / `Closure` | Dev tool |

## Non-route assessment and remaining gaps

| Component | Disposition and evidence |
| --- | --- |
| Database models, migrations, query filters and serialization | Source-reviewed. HTTP owner/list, mixed-file-removal and protected-field patterns assessed. Raw ordering uses fixed SQL and bound domain values. SQLite integration tests passed. Alternative database engines and engine-specific concurrency were not tested. |
| Static/public files and aliases | Source-reviewed tracked public assets, front controller, Apache rules and deployment script. Private uploads are outside the public root; the configured link targets public storage only. Live web-server aliases and permissions remain unverified. |
| File uploads and completion-photo processing | Source-reviewed count/size/type checks, generated storage names, private visibility, re-encoding and cleanup. Existing upload/photo tests and new download/parent-scope tests passed. Local fakes do not establish live filesystem permissions or object-store IAM. |
| Vue, Inertia, Blade, SSR and browser storage | Source-reviewed output sinks and URL attributes. Raw Vue HTML is limited to server pagination labels. Mail fields use escaped templates. Browser storage holds a motion preference, not credentials. Shared user serialization hides password and remember token. Build/type/lint/format checks passed. Browser DOM, browser caches, CSRF and live SSR behavior were not exercised. |
| Jobs, notification recipients, mail attachments and scheduled retention | Source-reviewed queue payloads, stale-source checks, mail recipients and local asset access, retention limits and cache keys. Existing notification, mail-rendering and retention tests passed. External delivery and real worker retry behavior were not tested. |
| External source previews | Synthetic HTTP boundary reproduction confirmed NET-01. Network isolation correction is deferred. Existing parser/metadata tests passed, but do not establish transport safety. |
| Session, cache and optional stores | Source-reviewed web session provider, cookie defaults, namespacing and version invalidation. Existing session tests passed. Redis, cloud disk, alternate queue/cache stores and provider policies were not connected or exercised. |
| Dependency and deployment tooling | Lockfile audits and primary advisory/documentation review completed. CI has read-only content permissions and ordinary pull-request testing. Production deployment triggers only on main pushes or manual dispatch, not this audit branch. No cloud configuration or live deployment settings were available for verification. |
| API tokens, GraphQL, broadcasts, webhooks, payments and search | Not applicable to the discovered repository entry points. No registrations or custom callers found. |

No production endpoint probing, production data access, cloud mutation, credential rotation, merge, auto-merge or deployment occurred. Remote CI was not awaited. Missing live, browser, provider and concurrency evidence remains a coverage gap. Review NET-01 and obtain the required external-fetch design decisions before future activation.

## Primary references consulted

- [Laravel 13 authorization](https://laravel.com/docs/13.x/authorization), [signed URLs](https://laravel.com/docs/13.x/urls), and [HTTP client testing](https://laravel.com/docs/13.x/http-client).
- [Laravel framework security advisories](https://github.com/laravel/framework/security/advisories), refreshed alongside lockfile audit results.
- [Guzzle request options](https://docs.guzzlephp.org/en/stable/request-options.html) and [OWASP SSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html).

Laravel Boost documentation search was attempted, but its tool was unavailable after the managed worktree handoff. Version-matched official documentation and installed source were used instead. No real database schema or application logs were requested through MCP.
