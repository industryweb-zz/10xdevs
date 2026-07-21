# Auth (register, log in, log out) Implementation Plan

## Overview

Install Laravel Breeze's classic Blade + Alpine stack to deliver roadmap slice **S-01**: a user can register with email + password, log in, reach a gated placeholder page, and log out. This is the access-control gate every later slice (S-02, S-03) depends on.

## Current State Analysis

The repo is an unmodified Laravel 13 skeleton:

- `composer.json:8-9` — only `laravel/framework: ^13.8` and `laravel/tinker`; no `laravel/breeze`, `laravel/sanctum`, or `laravel/socialite`.
- `routes/web.php` — a single welcome route, nothing else.
- `app/Models/User.php` — default Laravel model, unmodified.
- `app/Http/Controllers/Controller.php` — empty base class; no other controllers.
- `database/migrations/` — only the default `users`, `cache`, `jobs` tables; the `users` table already has the columns Breeze needs (`name`, `email`, `password`, `remember_token`, `email_verified_at`), so no new migration is required.
- `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php` — default skeleton tests only.
- `resources/css/app.css` uses Tailwind v4's CSS-based config (`@import 'tailwindcss'`, no `tailwind.config.js`); `resources/js/app.js` is empty; `vite.config.js` wires `@tailwindcss/vite` + `laravel-vite-plugin`.
- `.env.example` sets `MAIL_MAILER=log` and `SESSION_DRIVER=database` — no real mail transport is configured.

## Desired End State

A visitor can register (email + password), is logged in immediately, and lands on an authenticated placeholder page. They can log out and are returned to a guest-accessible state. Attempting to reach the placeholder page while logged out redirects to the login form. Email verification, password reset, and social login are explicitly not part of this slice.

Verification: `php artisan test` passes, and the manual register → dashboard → logout → blocked-access loop works in a browser.

### Key Discoveries:

- Breeze's Blade installer appends `require __DIR__.'/auth.php';` to `routes/web.php` and adds a closure-based `/dashboard` route with `auth` (and by default `verified`) middleware directly in `web.php` — no controller backs it, so swapping its view is a one-line middleware edit + a view content replacement.
- Breeze 2.4.x's Blade/Tailwind stubs already target Tailwind v4 (CSS-based `@import`, no separate `tailwind.config.js`), matching this repo's existing setup — low risk of asset-pipeline conflicts, but must be checked against what actually lands after install since stub content can vary by patch version.
- Breeze scaffolds password-reset and email-verification controllers, views, and feature tests by default; since this slice explicitly excludes both, those surfaces must be removed rather than left dangling and untested.

**Addendum (post-Phase-1):** the assumption above was wrong — installed Breeze v2.4.2 actually shipped Tailwind **v3** stubs (`tailwind.config.js`, `postcss.config.js`, `@tailwind` directives), downgrading the repo's original v4 setup and dropping the custom Bunny Fonts (`Instrument Sans`) loader from `vite.config.js`. This was surfaced during Phase 1 implementation and deliberately accepted (kept Breeze's default v3 stack rather than manually restoring v4) — not a bug, a decided tradeoff. See `reviews/impl-review-phase-1.md` (F1).

## What We're NOT Doing

- Social login / external identity provider (FR-002's "or linked external identity" branch) — deferred to a later change.
- Email verification flow — no real mail transport is configured (`MAIL_MAILER=log`); deferred.
- Password reset / forgot-password flow — same mail-transport dependency; deferred.
- Custom account lockout beyond Breeze's default `throttle` middleware on the login route.
- Any product UI beyond the auth loop itself — the post-login placeholder page is intentionally minimal and will be replaced when S-02 lands.

## Implementation Approach

Install Breeze's full default Blade scaffold first (fastest path to a working, well-tested baseline), then subtract the two mail-dependent features and swap the dashboard content — rather than hand-rolling auth or trying to install a partial/customized Breeze from the start. This keeps each phase independently verifiable: Phase 1 proves the vendor scaffold works untouched, Phase 2 proves the scope trim didn't break what remains, Phase 3 proves the end-to-end loop.

## Phase 1: Install Breeze scaffold

### Overview

Bring in Laravel Breeze's Blade + Alpine stack as-is and confirm it boots, with assets compiling under the existing Tailwind v4 / Vite setup.

### Changes Required:

#### 1. Breeze package + scaffold

**File**: `composer.json`, `package.json`

**Intent**: Add `laravel/breeze` as a dev dependency and run its installer to scaffold auth controllers, views, routes, and Alpine/Tailwind assets.

**Contract**: `composer require laravel/breeze --dev` then `php artisan breeze:install blade`, followed by `npm install` and `npm run build`. This is expected to modify `routes/web.php` (append `require __DIR__.'/auth.php';` and add the `/dashboard` route), create `routes/auth.php`, `app/Http/Controllers/Auth/*`, `app/Http/Controllers/ProfileController.php`, `app/Http/Requests/Auth/LoginRequest.php`, `resources/views/auth/*.blade.php`, `resources/views/profile/*.blade.php`, `resources/views/layouts/{app,guest,navigation}.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/components/*`, `tests/Feature/Auth/*.php`, `tests/Feature/ProfileTest.php`, and add `alpinejs` to `package.json` devDependencies plus `Alpine.start()` wiring in `resources/js/app.js`.

### Success Criteria:

#### Automated Verification:

- `composer show laravel/breeze` confirms the package is installed
- `php artisan route:list` shows `login`, `register`, `logout`, `dashboard` routes
- `npm run build` completes without error
- `php artisan test` passes (Breeze's own generated tests, untouched)

#### Manual Verification:

- Visiting `/register` and `/login` renders styled Breeze pages (Tailwind + Alpine assets load correctly, no unstyled/broken layout)
- Registering a new user logs them in and redirects to `/dashboard`, which shows Breeze's default "You're logged in!" content at this point (not yet swapped)

---

## Phase 2: Trim to MVP scope

### Overview

Remove the password-reset and email-verification surfaces Breeze added, drop the `verified` middleware requirement, and replace the dashboard view with a minimal placeholder.

### Changes Required:

#### 1. Remove password-reset flow

**Files**: `routes/auth.php`, `app/Http/Controllers/Auth/PasswordResetLinkController.php`, `app/Http/Controllers/Auth/NewPasswordController.php`, `resources/views/auth/forgot-password.blade.php`, `resources/views/auth/reset-password.blade.php`, `tests/Feature/Auth/PasswordResetTest.php`

**Intent**: These routes/views depend on real mail delivery, which isn't configured; remove them entirely rather than leave a broken "forgot password" link in the UI.

**Contract**: Delete the listed controller and view files; remove their route definitions from `routes/auth.php`; delete `PasswordResetTest.php`; remove any "Forgot your password?" link markup in `resources/views/auth/login.blade.php`.

#### 2. Remove email-verification flow

**Files**: `routes/auth.php`, `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`, `app/Http/Controllers/Auth/EmailVerificationPromptController.php`, `app/Http/Controllers/Auth/VerifyEmailController.php`, `resources/views/auth/verify-email.blade.php`, `tests/Feature/Auth/EmailVerificationTest.php`

**Intent**: No real mail transport exists to deliver verification emails; users should be usable immediately after registration without a verified-email gate.

**Contract**: Delete the listed controller and view files; remove their route definitions from `routes/auth.php`; delete `EmailVerificationTest.php`.

#### 3. Drop `verified` middleware from the dashboard route

**File**: `routes/web.php`

**Intent**: Without email verification, the `verified` middleware would permanently block access; the dashboard route should only require `auth`.

**Contract**: Change the dashboard route's middleware array from `['auth', 'verified']` to `['auth']`.

#### 4. Placeholder dashboard content

**File**: `resources/views/dashboard.blade.php`

**Intent**: Replace Breeze's default dashboard card with a minimal authenticated placeholder proving the access gate works — this view will be replaced by S-02's paste-text screen later.

**Contract**: Keep the existing `x-app-layout` wrapper and slot structure; replace the inner content with a short placeholder message (e.g. confirming the logged-in state) and the existing logout control from the navigation layout stays intact.

#### 5. Adjust `AuthenticationTest` for the removed `verified` gate

**File**: `tests/Feature/Auth/AuthenticationTest.php`

**Intent**: Breeze's generated authentication test may assert against `verified` middleware behavior or reference deleted routes; align it with the trimmed scope.

**Contract**: Remove or adjust any test assertions that depend on `EmailVerificationPromptController`, the `verified` middleware, or deleted password-reset routes; keep the register/login/logout/throttle assertions intact.

### Success Criteria:

#### Automated Verification:

- `php artisan route:list` no longer shows `password.request`, `password.email`, `password.reset`, `password.store`, `verification.notice`, `verification.verify`, `verification.send`
- `php artisan test` passes with the trimmed test suite (no leftover references to deleted controllers/routes)
- `npm run build` still completes without error

#### Manual Verification:

- `/login` no longer shows a "Forgot your password?" link
- Registering a new user lands directly on `/dashboard` showing the placeholder content, with no email-verification prompt
- Visiting `/dashboard` while logged out redirects to `/login`

---

## Phase 3: Verify the FR-001/002/003 loop

### Overview

Confirm the full register → login → gated access → logout loop works end-to-end, both via the automated suite and a manual pass, closing out S-01.

### Changes Required:

No further code changes expected; this phase is verification-only. If manual testing surfaces a defect, fix it in place within this phase before sign-off.

### Success Criteria:

#### Automated Verification:

- `php artisan test` passes in full (including `tests/Feature/Auth/AuthenticationTest.php`, `RegistrationTest.php`, `PasswordConfirmationTest.php`, `PasswordUpdateTest.php`, `ProfileTest.php`, and the original `ExampleTest.php` files)
- `vendor/bin/pint` reports no style violations on changed files

#### Manual Verification:

- Register a new account with email + password → immediately authenticated, lands on `/dashboard` placeholder
- Log out → returned to a guest-accessible page, session ends
- Attempt to visit `/dashboard` while logged out → redirected to `/login`
- Log back in with the same credentials → reaches `/dashboard` again
- Submitting the login form with a wrong password repeatedly triggers Breeze's default throttle response (HTTP 429 / rate-limit message) rather than an unbounded retry

## Testing Strategy

### Unit Tests:

- None beyond what Breeze scaffolds — no new domain logic exists yet to unit test.

### Integration Tests:

- Breeze's generated `tests/Feature/Auth/*` suite (trimmed per Phase 2), `ProfileTest.php`, plus the original `ExampleTest.php` files, run via `php artisan test`.

### Manual Testing Steps:

1. Register a new account, confirm immediate login and redirect to `/dashboard`.
2. Log out, confirm session ends and `/dashboard` becomes unreachable.
3. Log back in with the same credentials.
4. Trigger the login throttle by submitting several wrong passwords in a row.
5. Confirm `/register` and `/login` render with Tailwind/Alpine styling intact.

## Performance Considerations

None — this is standard session-based auth at small scale (per `tech-stack.md`'s target_scale: small users, low QPS); no caching or async work involved.

## Migration Notes

No new migrations — the default `users` table already has every column Breeze's Blade stack needs.

## References

- Roadmap slice: `context/foundation/roadmap.md` (S-01)
- PRD requirements: `context/foundation/prd.md` (FR-001, FR-002, FR-003, Access Control section)
- Tech stack rationale: `context/foundation/tech-stack.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Install Breeze scaffold

#### Automated

- [x] 1.1 Breeze package installed (`composer show laravel/breeze`) — c6d5b6d
- [x] 1.2 Auth routes present (`php artisan route:list` shows login/register/logout/dashboard) — c6d5b6d
- [x] 1.3 `npm run build` completes without error — c6d5b6d
- [x] 1.4 `php artisan test` passes (Breeze's generated tests, untouched) — c6d5b6d

#### Manual

- [x] 1.5 `/register` and `/login` render styled correctly — c6d5b6d
- [x] 1.6 Registering a new user logs in and redirects to `/dashboard` — c6d5b6d

### Phase 2: Trim to MVP scope

#### Automated

- [x] 2.1 Password-reset and email-verification routes no longer listed — d7c5c27
- [x] 2.2 `php artisan test` passes with trimmed suite — d7c5c27
- [x] 2.3 `npm run build` still completes without error — d7c5c27

#### Manual

- [x] 2.4 `/login` shows no "Forgot your password?" link — d7c5c27
- [x] 2.5 New registration lands directly on `/dashboard` placeholder, no verification prompt — d7c5c27
- [x] 2.6 Logged-out access to `/dashboard` redirects to `/login` — d7c5c27

### Phase 3: Verify the FR-001/002/003 loop

#### Automated

- [x] 3.1 `php artisan test` passes in full
- [x] 3.2 `vendor/bin/pint` reports no style violations on changed files

#### Manual

- [ ] 3.3 Register → immediate auth → `/dashboard` placeholder
- [ ] 3.4 Logout → guest state, `/dashboard` unreachable
- [ ] 3.5 Log back in with same credentials
- [ ] 3.6 Repeated wrong-password submissions trigger throttle response
