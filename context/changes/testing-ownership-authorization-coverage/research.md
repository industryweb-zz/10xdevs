---
date: 2026-07-29T20:48:50+02:00
researcher: Claude
git_commit: 4e7b3ed960fa2dd91aa132f81b8c2e97196ef5f5
branch: master
repository: 10xdevs
topic: "Rollout Phase 1 — Ownership & authorization coverage (Risk #1, IDOR)"
tags: [research, codebase, authorization, idor, flashcard-sets, study-session]
status: complete
last_updated: 2026-07-29
last_updated_by: Claude
---

# Research: Rollout Phase 1 — Ownership & authorization coverage (Risk #1, IDOR)

**Date**: 2026-07-29T20:48:50+02:00
**Researcher**: Claude
**Git Commit**: 4e7b3ed960fa2dd91aa132f81b8c2e97196ef5f5
**Branch**: master
**Repository**: 10xdevs

## Research Question

Ground rollout Phase 1 of `context/foundation/test-plan.md`: Risk #1 — "A logged-in user reaches another user's flashcard set, study session, or results by manipulating a URL/ID (IDOR)." For each route touching a flashcard set, verify or correct the plan's response guidance ("prove rejection by ID; must challenge that ownership already holds everywhere since checks are hand-rolled per action, not centralized via a policy"), locate existing tests, identify the cheapest useful test layer, and flag speculative or misleading evidence.

## Summary

**Risk #1 is already substantially covered by existing tests, not a live gap.** All three routes that bind `{flashcardSet}` (`show`, `study`, `results`) perform an explicit `abort_unless($flashcardSet->user_id === auth()->id(), 403)` check, and each one already has a dedicated cross-user 403 test:

- `flashcard-sets.show` → `tests/Feature/FlashcardGenerationTest.php:81` `test_viewing_another_users_flashcard_set_returns_403`
- `flashcard-sets.study` → `tests/Feature/StudySessionTest.php:44` `test_cross_user_access_to_study_returns_403`
- `flashcard-sets.results` → `tests/Feature/StudySessionTest.php:55` `test_cross_user_access_to_results_returns_403`

`flashcard-sets.index` is safe by construction (query scoped via `$request->user()->flashcardSets()`, `FlashcardSetController.php:21`) and has a *partial* test: `FlashcardSetIndexTest.php:14` asserts another user's set title is not visible via `assertDontSee`, but no test asserts a direct-URL 403/404 attempt against the index (not applicable — index takes no ID) nor exercises the scoping mechanism itself failing.

**The test plan's "must challenge" framing is correct and remains the real risk**: the guard is 100% hand-rolled, duplicated identically three times, with **no Policy class, no Gate, and no scoped route-model binding** anywhere in the app (`app/Policies/` does not exist; no `authorize()`/`Gate::` calls outside the unrelated `LoginRequest`). Nothing in the framework stops a future action (edit/update/destroy/export/API endpoint) from omitting the check — the existing tests protect the three routes that exist today, not the pattern itself. This reframes what Phase 1 should actually add: **not** three more "does 403 fire" tests (they exist), but a regression guard against the pattern's fragility and its one confirmed real gap.

**Confirmed gap**: no test walks the full set of `{flashcardSet}`-bound routes as a sweep with a single assertion helper — each route's protection is proven independently, so adding a new bound route with a forgotten `abort_unless` would pass existing tests silently. This matches the test plan's "must challenge" line almost exactly and should be the anchor for Phase 1's actual new test(s).

## Detailed Findings

### Routes and controller ownership mechanism

All routes live under `Route::middleware('auth')->group()` (`routes/web.php:16-27`):

| Route name | Method + path | Controller@action | Ownership mechanism | file:line |
|---|---|---|---|---|
| `flashcard-sets.store` | POST `/flashcard-sets` | `FlashcardSetController@store` | relation-scoped create, no existing-ID surface | `app/Http/Controllers/FlashcardSetController.php:35` |
| `flashcard-sets.index` | GET `/flashcard-sets` | `FlashcardSetController@index` | relation-scoped query | `app/Http/Controllers/FlashcardSetController.php:21` |
| `flashcard-sets.show` | GET `/flashcard-sets/{flashcardSet}` | `FlashcardSetController@show` | unscoped implicit binding + `abort_unless` | `app/Http/Controllers/FlashcardSetController.php:47,49` |
| `flashcard-sets.study` | GET `/flashcard-sets/{flashcardSet}/study` | `StudySessionController@study` | unscoped implicit binding + `abort_unless` | `app/Http/Controllers/StudySessionController.php:11,13` |
| `flashcard-sets.results` | POST `/flashcard-sets/{flashcardSet}/results` | `StudySessionController@results` | unscoped implicit binding + `abort_unless` | `app/Http/Controllers/StudySessionController.php:20,22` |

No `routes/api.php` exists. No other route in the app binds `{flashcardSet}` or a raw `{flashcard}` ID. `Flashcard` (individual card) has no direct `user()` relation and is never bound directly in a route — it is only reached nested under an already-checked `FlashcardSet` (`app/Models/Flashcard.php:19`), so it is not a live IDOR surface today, but it would have zero ownership-check plumbing of its own if a future route bound `{flashcard}` directly.

No Policy/Gate infrastructure exists anywhere (`app/Policies/` absent; grep for `Policy`, `Gate::`, `authorize(` across `app/` found nothing relevant). Ownership is three independent, identically-worded `abort_unless($flashcardSet->user_id === auth()->id(), 403)` lines — one per controller action.

### Existing test coverage

- `tests/Feature/FlashcardGenerationTest.php:81-90` — `test_viewing_another_users_flashcard_set_returns_403`: creates `$owner`/`$otherUser`, a set owned by `$owner`, `$otherUser` GETs `flashcard-sets.show`, asserts `assertForbidden()`.
- `tests/Feature/StudySessionTest.php:44-53` — `test_cross_user_access_to_study_returns_403`: same pattern against `flashcard-sets.study`.
- `tests/Feature/StudySessionTest.php:55-68` — `test_cross_user_access_to_results_returns_403`: same pattern against `flashcard-sets.results` (POST).
- `tests/Feature/FlashcardSetIndexTest.php:14-28` — `test_user_sees_only_their_own_sets_newest_first`: asserts ordering + `assertDontSee($otherUsersSet->title)` — a content-leak check on the index view, not a route-level 403/404 assertion (index has no ID param to attack).

All cross-user tests assert **403 only** (`assertForbidden()`); none exercise a 404 path, and none exist for a delete/update-type route because no such route exists yet.

`database/factories/FlashcardSetFactory.php:19-25` defaults `user_id => User::factory()` and supports assigning to an arbitrary/specific user via `->for($owner)` or `create(['user_id' => $id])` — this is the established pattern all three existing cross-user tests already use, and the pattern any new test should reuse.

`tests/TestCase.php:7-10` is a bare pass-through (no shared "act as second user" helper) — each test file re-implements the two-user setup independently. This is a candidate location for a small shared helper if Phase 1 wants to add a route sweep (see below), though that is a `/10x-plan` decision, not asserted here as necessary.

### Historical design intent (archive)

- `context/archive/2026-07-26-generate-and-study-flashcards/plan.md:163,226` and its `reviews/impl-review.md:62-69` (finding F4) confirm the `abort_unless($flashcardSet->user_id === auth()->id(), 403)` pattern was the deliberate design for `show`/`study`/`results`, and that it was "exercised by tests" at review time — matching what's found in the live code today. No Policy class was ever discussed as an alternative; the hand-rolled pattern is convention, not a documented trade-off.
- `context/archive/2026-07-26-saved-sets-list/plan.md:17` explicitly distinguishes two ownership mechanisms side by side: per-record `abort_unless` (single-resource routes) vs. relation-scoped query (`index`) — confirming these are two independent mechanisms that could regress independently. The same plan's "Open Risks & Assumptions" (lines 47-49) states *"None — this is a straightforward read path... already correctly scoped per-user"* for the index route — an explicit confidence claim with no accompanying automated cross-user route test (only the `assertDontSee` content check), which is a reasonable oracle to test against.
- No `research.md` exists in any of the three archive folders — the archived slices did not run `/10x-research` as part of their chain, so there is no prior grounding artifact to cross-check beyond `plan.md`/review files.

## Verdict on the test plan's Risk Response Guidance (§2, Risk #1)

| Guidance element | Verified? | Correction |
|---|---|---|
| "A user requesting another user's flashcard-set/study/results resource by ID is rejected (403/404)... " | **Mostly true today** — but response is 403 only across all three existing tests/routes; nothing currently returns 404 for this case (all three use `abort_unless(..., 403)`, not `findOrFail` scoped to the user, which would 404). Plan wording ("403/404") should be read as "403 today" — do not write a test expecting 404. | Adjust expectation to 403 specifically. |
| "'The show/study/results routes already 403 today, so this always holds' — must challenge because ownership logic is hand-rolled per action, not centralized" | **Confirmed exactly as written.** This is the real, live risk — not a speculative one. Three duplicated `abort_unless` lines, zero structural guard, zero Policy. | Keep as-is; this is the anchor for Phase 1. |
| "Which routes are covered vs. rely only on route-model binding" | **Answered**: none rely on binding alone — all three bound routes have the manual check. The risk is about *future* bound routes, not a currently-uncovered one. | Reframe Phase 1's new-test target: a regression/sweep guard for future routes, not a fix for a live gap. |
| Likely cheapest layer: integration (Laravel HTTP feature test as a second user) | **Confirmed correct** and already the pattern in use (`->for($owner)`, `actingAs($otherUser)`, `assertForbidden()`). | No change. |

**Speculative-risk flag**: none of Risk #1 is speculative — the failure mode (a future controller action forgetting the check) is real given the demonstrated pattern of three independent hand-rolled copies and zero structural guard. However, the *specific* three routes named in the original risk are not currently unprotected; `/10x-plan` should target the **regression-proofing angle** (route sweep / shared helper / documenting the pattern in §6 for future actions to follow) rather than duplicating the three tests that already exist.

## Code References

- `routes/web.php:16-27` — auth-gated route group for all flashcard-set/study/results routes
- `app/Http/Controllers/FlashcardSetController.php:21` — index, relation-scoped query
- `app/Http/Controllers/FlashcardSetController.php:35` — store, relation-scoped create
- `app/Http/Controllers/FlashcardSetController.php:47,49` — show, unscoped binding + `abort_unless`
- `app/Http/Controllers/StudySessionController.php:11,13` — study, unscoped binding + `abort_unless`
- `app/Http/Controllers/StudySessionController.php:20,22,27-30` — results, unscoped binding + `abort_unless`; separate `422` integrity check (Risk #4, not this phase)
- `app/Models/User.php:36` — `flashcardSets()` hasMany
- `app/Models/FlashcardSet.php:20` — `user()` belongsTo
- `app/Models/Flashcard.php:19` — `flashcardSet()` belongsTo (no direct User relation)
- `tests/Feature/FlashcardGenerationTest.php:81-90` — existing cross-user 403 test for `show`
- `tests/Feature/StudySessionTest.php:44-53,55-68` — existing cross-user 403 tests for `study`, `results`
- `tests/Feature/FlashcardSetIndexTest.php:14-28` — index content-leak check (not a route-level 403 test)
- `database/factories/FlashcardSetFactory.php:19-25` — factory supporting `->for($user)` assignment

## Architecture Insights

- Ownership enforcement is a **convention, not a mechanism**: three copies of the same one-liner, no Policy/Gate, no scoped binding. This is consistent with a 3-week MVP timeline but is exactly the kind of thing that regresses silently as routes are added.
- The project already has an established "second user + `assertForbidden()`" test idiom across two files; any new Phase 1 test should follow it rather than introduce a new pattern.
- `FlashcardSetFactory` and `TestCase` provide enough building blocks for a lightweight shared "second user" helper if `/10x-plan` decides a route-sweep test is worth the abstraction — not asserted as necessary here, just noted as available.

## Historical Context (from prior changes)

- `context/archive/2026-07-26-generate-and-study-flashcards/plan.md` — original design contract for `show`/`study`/`results` ownership checks.
- `context/archive/2026-07-26-generate-and-study-flashcards/reviews/impl-review.md` (F4) — confirms the pattern shipped and was test-exercised.
- `context/archive/2026-07-26-saved-sets-list/plan.md` — distinguishes the two ownership mechanisms (per-record check vs. relation-scoped query) and records an explicit "no risk" claim for the index route's scoping.

## Related Research

None — this is the first `/10x-research` run for this change; no other `research.md` exists in the archive for prior slices.

## Open Questions

- Should Phase 1's new test be a **route sweep** (single test iterating all `{flashcardSet}`-bound routes with one assertion helper) to catch a future missed check, or should it instead assert the **index scoping mechanism** directly (currently only indirectly verified via `assertDontSee`)? This is a `/10x-plan` cost×signal decision, not a research question — both are cheap integration tests.
- Is a shared "second user" test helper (e.g., in `tests/TestCase.php` or a trait) worth introducing given the pattern is now duplicated three times, or is that premature abstraction for a 3-week MVP? Leaving to `/10x-plan`.
