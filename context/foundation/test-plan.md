# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-07-26

## 1. Strategy

Tests follow three non-negotiable principles for this project:

1. **Cost × signal.** The cheapest test that gives a real signal for the
   risk wins. Do not promote to e2e because e2e "feels safer." Do not put a
   vision model on top of a deterministic visual diff that already catches
   the regression.
2. **User concerns are first-class evidence.** Risks anchored in "the team
   is worried about X, and the failure would surface somewhere in area Y"
   carry the same weight as PRD lines or hot-spot data.
3. **Risks are scenarios, not code locations.** This plan documents *what
   could fail* and *why we believe it's likely* — drawn from documents,
   interview, and codebase *signal* (churn, structure, test base). It does
   NOT claim to know which line owns the failure. That knowledge is
   produced by `/10x-research` during each rollout phase. If the plan and
   research disagree about where the failure lives, research is the
   ground truth.

Hot-spot scope used for likelihood weighting: whole repo (`app/`, `resources/`,
`routes/`, `database/`, `config/`, `tests/`) — the project is 3 weeks old, so
full git history is the full churn window; no scoping override was needed.

## 2. Risk Map

The top failure scenarios this project must protect against, ordered by
risk = impact × likelihood. Risks are failure scenarios in user / business
terms, not test names. The Source column cites the *evidence that surfaced
this risk* — never a specific file as "where the failure lives" (that is
research's job, see §1 principle #3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | A logged-in user reaches another user's flashcard set, study session, or results by manipulating a URL/ID (IDOR) | High | Medium | interview Q1, Q4; PRD Access Control ("brak współdzielenia zestawów... każdy pracuje wyłącznie na swoich fiszkach") |
| 2 | AI generation throws or returns malformed/empty output and the failure isn't handled cleanly (crash, or partial/garbage data persists) | High | Medium | interview Q3; archive/generate-and-study-flashcards/plan.md (single-exception-type design intent) |
| 3 | A future change reintroduces persistence of raw pasted source text (session flash, logs, DB) despite the existing guardrail | High | Medium | PRD guardrail ("tekst wklejony... nie może trafić do nieautoryzowanych osób"); archive/generate-and-study-flashcards/plan.md (explicit critical-decision note against `withInput()`) |
| 4 | A tampered client posts study-session known/unknown counts that don't reflect a real session, and the score is accepted anyway | Medium | Medium | archive/generate-and-study-flashcards/plan.md (integrity-check design intent) |
| 5 | An English string or non-Polish AI output leaks into the UI as new views/prompts are added | Medium | Medium | context/foundation/lessons.md (Polish-only hard rule); hot-spot dir `resources/views/components` (13 commits), `resources/views/auth` (10 commits) |
| 6 | An auth/session customization breaks registration/login/logout for existing users | High | Low | PRD must-have FR-001–003; hot-spot dir `app/Http/Controllers/Auth` (14 commits), `tests/Feature/Auth` (8 commits) |

**Impact × Likelihood rubric**

| Rating | Impact | Likelihood |
|--------|--------|------------|
| High   | user loses access, data, or money; failure is publicly visible | area changes weekly, or we have already been burned here |
| Medium | feature degrades, a workaround exists, only some users affected | touched occasionally, has been a source of bugs |
| Low    | cosmetic, easily reverted, no data effect | stable code, rarely touched |

**Abuse / security lens.** The product has auth and accepts free-text user
input, so authorization and PII-leakage classes apply and are represented:
Risk #1 (authorization/access — IDOR) and Risk #3 (secret/PII-adjacent
leakage — pasted educational text persisting where it shouldn't). A
resource-abuse candidate (unbounded-cost repeated AI-generation requests)
was considered and dropped — see the challenger note below.

**Challenger findings:** A 7th candidate risk — "a user triggers unbounded
AI-generation cost via rapid repeated requests" — was dropped. No
rate-limiting safeguard exists today, so testing "the limit holds" would
require building the limiter first, which is implementation work, not a
test gap (the risk was describing a missing feature, not a regression in
an existing one). It is recorded in §7 as an acknowledged gap for a future
`--refresh`, not silently discarded.

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | A future controller action added on `FlashcardSet`/study/results routes rejects cross-user access by ID (403) the same way the three existing routes already do — a new action cannot silently ship without the check | "The show/study/results routes already 403 today, so this always holds" — **confirmed true for the 3 routes that exist today** (`FlashcardGenerationTest.php:81`, `StudySessionTest.php:44`,`:55`); the live risk is that ownership logic is hand-rolled and duplicated per controller action, not centralized via a policy, so a *future* action can silently omit it | How each controller currently establishes ownership (relation-scoped query for `index`/`store` vs. explicit `abort_unless` for `show`/`study`/`results` — research confirmed all 3 ID-bound routes already have the check); no Policy/Gate exists anywhere in the app | integration (Laravel HTTP feature test as a second user, `assertForbidden()` — 403 only, not 404) | Duplicating the 3 tests that already exist; asserting the expected result from the controller's current check (oracle problem) instead of from the PRD's access-control rule; asserting 404 where the app returns 403 |
| #2 | When the AI service throws or returns malformed/empty output, the user sees a clean error, no half-created `FlashcardSet`/`Flashcard` rows persist, and there is no 500 | "The wrapped exception type means all failure modes are handled identically and safely" — verify a degenerate *successful* response (e.g. 0 flashcards) doesn't slip through as a valid set | Exact conditions under which the service throws vs. returns a degenerate value; whether set-creation happens inside one transaction | integration test with a fake generator returning edge-shaped output | Testing only the happy path with a well-formed fake; asserting against whatever the code currently does with malformed input rather than the PRD's implicit expectation |
| #3 | After a failed or successful generation request, the raw pasted text is not retrievable from session, DB, or logs — only the derived title/flashcards persist | "We already decided not to call `withInput()`, so this is permanently safe" — a future UX improvement (repopulating the form) is exactly the kind of change that reintroduces it | What the failure-path controller action currently does on error (redirect method, flash keys used); whether logging config captures request bodies | integration test asserting session/flash state after a failed attempt | Asserting only "no error thrown" without inspecting session contents |
| #4 | Posting known/unknown counts that don't sum to the set's flashcard count is rejected (422); results only reflect counts that pass the integrity check | "Any two non-negative integers are fine" — must also reject counts where one field alone exceeds the actual flashcard count | Current validation shape (sum check only vs. also per-field bound checks) | integration/feature test posting mismatched and out-of-range counts | Copying the validation rule straight from the controller as the test oracle instead of asserting from the PRD's expectation of a real session |
| #5 | New/changed user-facing strings and the AI-generation prompt remain Polish; an English string introduced by a future change is caught before merge | "Translations are added at write-time so this can never regress" — nothing today guards a missed `lang/pl.json` entry or an English fallback leaking through | How `__()` fallback behaves when a key is missing from `lang/pl.json` | lightweight static check or a smoke test asserting key views don't fall back to English | A brittle full-page snapshot test that breaks on unrelated copy changes and gets deleted over time |
| #6 | Registration, login, logout continue to work for legitimate users; a protected route redirects a logged-out user to login rather than erroring or leaking content | "Breeze's own tests cover this forever" — existing coverage is a snapshot; a future customization (middleware change, added flow) could break it silently | Whether any prior slice modified Breeze's default auth flow/middleware since bootstrap | existing feature tests (already present) — extend only where customized | Re-testing framework-provided behavior Laravel/Breeze already guarantees instead of wiring it into a required gate |

## 3. Phased Rollout

Each row is a discrete rollout phase that will open its own change folder
via `/10x-new`. Status moves left-to-right through the values below; the
orchestrator updates Status as artifacts appear on disk.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Ownership & authorization coverage | Prove cross-user access is rejected consistently across every flashcard-set/study/results route | #1 | integration | complete | context/changes/testing-ownership-authorization-coverage/ |
| 2 | AI generation resilience & score integrity | Prove generation failures and tampered score submissions never produce bad data or false results | #2, #4 | integration | not started | — |
| 3 | Privacy & Polish-only regression guards | Lock the pasted-text non-persistence guarantee and the Polish-only UI rule in as regression tests | #3, #5 | integration + lightweight translation check | not started | — |
| 4 | Quality-gates wiring | Wire lint + full suite (including existing Auth coverage) into a required CI gate | #6 | gates | not started | — |

**Status vocabulary** (fixed — parser literals): `not started` →
`change opened` → `researched` → `planned` → `implementing` → `complete`.

## 4. Stack

| Layer | Tool | Version | Notes |
|---|---|---|---|
| unit + integration | PHPUnit (via `php artisan test`) | per `composer.json` | `Tests\TestCase` + `RefreshDatabase`, not Pest — 11 test files today (Auth, FlashcardGeneration, FlashcardSetIndex/Show, StudySession, Profile) |
| API mocking | Laravel container binding (fake `FlashcardGenerator`) | n/a | Interface-based swap already established in Phase 1 of the generate-and-study slice; no HTTP-mocking library needed since the SDK call is behind an interface |
| e2e | none yet — see Phase 4 | n/a | No Playwright/Cypress present; not justified yet at this scale (small user base, low QPS per tech-stack.md) |
| lint | Pint (`vendor/bin/pint`) | per `composer.json` | Not yet confirmed wired as a required CI gate — see §5 |
| (optional) AI-native | none | n/a | Not included — flashcard *content quality* review wasn't raised as a top risk; PRD treats imperfect AI quality as a post-MVP iteration item |

**Stack grounding tools (current session):**
- Docs: none available (no Context7/framework-docs MCP exposed this session); checked: 2026-07-26
- Search: generic WebSearch tool available — not queried, no external tool/version claim needed for this plan; checked: 2026-07-26
- Runtime/browser: none available; checked: 2026-07-26
- Provider/platform: none available as MCP (GitHub reachable only via `gh` CLI, not tool-integrated); checked: 2026-07-26

## 5. Quality Gates

| Gate | Where | Required? | Catches |
|---|---|---|---|
| lint (Pint) | local + CI | required after §3 Phase 4 | formatting/style drift |
| unit + integration (`php artisan test`) | local + CI | required after §3 Phase 4 | logic regressions, including Risks #1–#4, #6 |
| e2e on critical flows | CI on PR | not planned this rollout | broken critical user paths — deferred, see §7 |
| post-edit hook | local (agent loop) | not configured (out of lesson scope) | regressions at edit time |
| translation-completeness check | local + CI | required after §3 Phase 3 | English strings/AI output leaking past the Polish-only rule (Risk #5) |
| pre-prod smoke | between merge + prod | optional | Railway-environment-specific failures |

## 6. Cookbook Patterns

How to add new tests in this project. Each sub-section is filled in once
the relevant rollout phase ships; before that, the sub-section reads
"TBD — see §3 Phase N."

### 6.1 Adding a unit test

- TBD — see §3 Phase 2 (AI generation resilience — DTO/service-shape logic is the first candidate for a true unit test in this project).

### 6.2 Adding an integration (feature) test

This project has two ownership-enforcement mechanisms, and each needs its own kind of test:

- **Per-record binding** (`{flashcardSet}`-bound routes — `show`, `study`, `results`): the
  controller runs an explicit `abort_unless($flashcardSet->user_id === auth()->id(), 403)`. Any
  route that binds a model directly by ID needs a cross-user test proving a non-owner gets a
  403. Use the shared `Tests\Concerns\AssertsOwnershipEnforced` trait
  (`tests/Concerns/AssertsOwnershipEnforced.php`) — its
  `assertRouteForbiddenToOtherUser($otherUser, $flashcardSet, $method, $routeName, $data = [])`
  helper builds the request and asserts `assertForbidden()`. Add the route to the data provider
  in `tests/Feature/FlashcardSetOwnershipTest.php` (reference test) rather than writing a new
  test class.
- **Relation-scoped query** (`index`, `store`): ownership is enforced by scoping the query/create
  to `$request->user()->...`, not by checking an ID. Prove this with a direct assertion on the
  response's underlying data (e.g. `assertViewHas('flashcardSets', fn ($sets) =>
  $sets->doesntContain('id', $otherUsersSet->id))`), not just a rendered-HTML `assertDontSee` —
  see `tests/Feature/FlashcardSetIndexTest.php::test_index_response_never_includes_another_users_flashcard_set_id`.

### 6.3 Adding an e2e test

- Not planned this rollout — see §7 negative space.

### 6.4 Adding a test for a new API endpoint

- Any new route that binds `{flashcardSet}` — or a future route that binds `{flashcard}`
  directly, which has no ownership-check plumbing of its own today since `Flashcard` has no
  direct `User` relation — must add a case to the `boundRoutesProvider()` data provider (or a
  dedicated test method, for routes needing extra setup like `results` does) in
  `tests/Feature/FlashcardSetOwnershipTest.php`. This is what turns a forgotten `abort_unless`
  into a failing test instead of a silent gap.

### 6.5 Adding a translation-completeness check

- TBD — see §3 Phase 3 for the Polish-only regression guard pattern.

### 6.6 Per-rollout-phase notes

(Appended by `/10x-implement`'s final sub-phase as each phase lands.)

## 7. What We Deliberately Don't Test

Exclusions agreed during the rollout (Phase 2 interview, Q5). Future
contributors should respect these unless the underlying assumption changes.

- **Exhaustive AI prompt-wording variants** — test the generation service's handling of success/failure *shapes* (Risk #2), not every phrasing of the prompt itself. Re-evaluate if flashcard content *quality* (not just error handling) becomes a stated top risk. (Source: Phase 2 interview Q5.)
- **Rate-limiting / resource abuse on AI generation requests** — no limiter exists today; this is a missing-feature gap, not a regression to guard against. Re-evaluate at `--refresh` if abuse is observed or a limiter is added. (Source: §2 challenger findings.)
- **Saved-sets-list pagination/sorting** — the list is intentionally flat and unpaginated at this scale (small target scale per PRD); no future-proofing tests for scale the PRD doesn't target.

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-07-26
- Stack versions last verified: 2026-07-26
- AI-native tool references last verified: n/a (none in use)

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
