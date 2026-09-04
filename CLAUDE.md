# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**KnowledgeTest** — paste source text, get AI-generated flashcards, study them in one flow. Greenfield, solo, 3-week MVP. See `@context/foundation/prd.md` for the full product spec, `@context/foundation/tech-stack.md` for the stack rationale, and `@context/foundation/infrastructure.md` for the deployment platform decision (Railway, researched and bias-checked — not self-host, despite two early commits building a DirectAdmin/Apache deploy pipeline that was later superseded and removed).

The repo is currently an **unmodified Laravel 13 skeleton** — no auth, no controllers, no models beyond the default `User`, no AI integration yet, despite the tech-stack calling for Laravel Breeze auth and LLM-backed flashcard generation. Don't assume any of that exists; check before referencing it. The `deploy/` directory is empty (an earlier self-hosted release script was removed when the deployment target moved to Railway) — don't assume a deploy pipeline exists on disk.

## Commands

```bash
composer run dev    # serve + queue:listen + pail (logs) + vite, all concurrently
php artisan test    # run the full suite (clears config cache first)
php artisan test --filter=testName   # run a single test
vendor/bin/pint     # format PHP (Laravel's opinionated formatter)
npm run dev         # vite only, if not using composer run dev
npm run build        # production asset build
```

No linter/static analysis tool is configured beyond Pint — don't assume PHPStan/Larastan is present.

## Architecture

Standard Laravel structure (`app/Http/Controllers`, `app/Models`, `routes/web.php`, `database/migrations`) — nothing project-specific has been layered on yet. When adding the flashcard-generation feature, follow the PRD in `@context/foundation/prd.md` rather than inventing scope.

<!-- BEGIN @przeprogramowani/10x-cli -->

## 10xDevs AI Toolkit - Module 3, Lesson 4 (E2E Tests)

**For E2E tests, use the `/10x-e2e` skill.** It is the single source of truth
for the workflow — risk → seed test + rules → generate → review against the five
anti-patterns → re-prompt → verify. The skill's `references/` carry the full
rules, anti-patterns, seed pattern, and prompt-template.

A few hard rules that hold even before you invoke the skill:

- **Locators:** `getByRole` / `getByLabel` / `getByText` first; `getByTestId`
  only when accessibility attributes are ambiguous. Never CSS selectors, XPath,
  or DOM structure.
- **Never `page.waitForTimeout()`.** Wait for state: `toBeVisible()`,
  `waitForURL()`, `waitForResponse()`.
- **Test independence + cleanup.** Each test runs standalone — its own setup,
  action, assertion, and cleanup; unique ids (timestamp suffix) so parallel runs
  and re-runs don't collide.

Two boundaries to keep straight:

- **DOM (snapshot) is the default.** Vision (`--caps=vision`) is a supplement for
  visual-only risks (layout, z-index, animation); for pixel regression prefer
  deterministic tools (`toMatchSnapshot`, Argos, Lost Pixel). VLM model
  selection/cost is a debugging topic (Lesson 5), not testing.
- **Healer helps on selectors, harms on logic.** A changed selector → healer
  re-finds it (route through PR review). A changed business behavior → healer
  masks the bug; that failing-test-to-fix case is Lesson 5.

<!-- END @przeprogramowani/10x-cli -->
