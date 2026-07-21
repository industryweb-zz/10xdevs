# Auth (register, log in, log out) — Plan Brief

> Full plan: `context/changes/minimal-auth-for-generation/plan.md`

## What & Why

Install Laravel Breeze (classic Blade + Alpine stack) to deliver roadmap slice **S-01**: a user can register with email + password, log in, reach a gated placeholder page, and log out. This is the access-control gate every later slice (S-02 generate-and-study-flashcards, S-03 saved-sets-list) depends on — nothing downstream can be validated until this exists.

## Starting Point

The repo is an unmodified Laravel 13 skeleton: no `laravel/breeze`/`sanctum`/`socialite` installed, `routes/web.php` has only a welcome route, `User` model and `users`/`cache`/`jobs` migrations are default/untouched, no controllers beyond an empty base class, and only skeleton `ExampleTest` files exist. `.env.example` ships `MAIL_MAILER=log` — no real mail transport configured.

## Desired End State

A visitor registers with email + password, is logged in immediately, and lands on a minimal authenticated placeholder page. They can log out and are returned to guest state. Visiting the placeholder page while logged out redirects to `/login`. No email verification, password reset, or social login exist in this slice.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Social login (FR-002) | Defer to v2 | 3-week solo/after-hours budget doesn't justify OAuth setup for zero validated demand | Plan |
| Email verification | Skip for MVP | No real mail transport configured (`MAIL_MAILER=log`) | Plan |
| Password reset | Skip for MVP | Same mail-transport blocker as verification | Plan |
| Breeze frontend stack | Blade + Alpine | Least delta from current bare Blade+Vite skeleton, simplest install | Plan |
| Post-login landing | Minimal placeholder `/dashboard` | Proves the access gate works; will be replaced by S-02's real screen | Plan |
| Failed-login handling | Breeze's default throttle only | Already wired by scaffold, sufficient for small-scale MVP threat model | Plan |
| Testing depth | Breeze's generated feature tests, trimmed | Battle-tested coverage for exactly this scope, no added surface | Plan |
| Non-negotiable priority | Full register→login→gated-access→logout loop | Matches roadmap S-01's stated Outcome, unblocks S-02 | Plan |

## Scope

**In scope:** Registration (email+password), login, logout, session-gated placeholder page, Breeze's default login throttle, trimmed automated test coverage.

**Out of scope:** Social/external-identity login, email verification, password reset, custom account lockout, any real product UI beyond the placeholder.

## Architecture / Approach

Install Breeze's full default Blade scaffold first (proven, well-tested baseline), then subtract the two mail-dependent features (password reset, email verification) and swap the dashboard content — rather than hand-rolling auth or a partial Breeze install from scratch. Each phase is independently verifiable.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Install Breeze scaffold | Full default Breeze Blade auth working, assets compiling | Tailwind v4 stub mismatch with existing Vite/Tailwind setup |
| 2. Trim to MVP scope | Password-reset/email-verification surfaces removed, placeholder dashboard, `verified` middleware dropped | Leftover test/route references to deleted controllers |
| 3. Verify the FR-001/002/003 loop | End-to-end register/login/logout/throttle confirmed, full test suite green | None significant — verification-only phase |

**Prerequisites:** None — this is the first slice, no other change depends on.
**Estimated effort:** ~1 session across 3 phases (small, well-trodden Breeze install).

## Open Risks & Assumptions

- Assumes the installed Breeze version's Tailwind stubs are v4-compatible (CSS `@import`, no `tailwind.config.js`) — must be confirmed against the actual installed version at Phase 1, not just the researched 2.4.x baseline.
- Assumes removing password-reset/email-verification routes/views/tests cleanly, with no leftover references in `AuthenticationTest.php` or navigation views — checked explicitly in Phase 2's automated criteria.

## Success Criteria (Summary)

- A new user can register, land on an authenticated page, log out, and be blocked from that page while logged out.
- `php artisan test` passes in full with no references to removed password-reset/email-verification surfaces.
- No "Forgot password" or email-verification prompts appear anywhere in the UI.
