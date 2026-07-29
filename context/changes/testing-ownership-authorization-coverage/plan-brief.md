# Ownership & Authorization Coverage — Plan Brief

> Full plan: `context/changes/testing-ownership-authorization-coverage/plan.md`
> Research: `context/changes/testing-ownership-authorization-coverage/research.md`

## What & Why

Test-plan Rollout Phase 1 for Risk #1 (IDOR). Research found the three existing
`{flashcardSet}`-bound routes are already protected and tested — the real risk is that ownership
enforcement is three hand-duplicated `abort_unless` lines with no Policy/Gate, so a future bound
route could silently ship unprotected. This adds a regression guard against that, plus closes a
partial gap on the index route's scoping.

## Starting Point

`show`, `study`, `results` each already 403 a cross-user request via their own passing test.
`index`/`store` use a relation-scoped query instead of an ID check; `index`'s cross-user coverage
today is only an `assertDontSee` content check, not a route-level assertion of the scoping itself.
No shared multi-user test helper exists — each test file re-implements owner/other-user setup.

## Desired End State

A `tests/Feature/FlashcardSetOwnershipTest.php` sweep, backed by a small reusable
`AssertsOwnershipEnforced` trait, iterates every bound route and fails loudly if a future route
omits its ownership check. `flashcard-sets.index` gets a direct scoped-data assertion. The
test-plan cookbook (§6.2/§6.4) documents the pattern for future contributors.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| Core test shape | Route-sweep test over all bound routes | Directly guards the confirmed live risk: a future route silently missing the check | Research + Plan |
| Test helper | Add a small shared trait | The sweep needs the pattern N times immediately, so the abstraction isn't speculative | Plan |
| Guest/unauthenticated case | Out of scope | That's test-plan Risk #6 (auth), a separate risk and phase | Plan |
| `{flashcard}`-direct future route | No code/test now | No such route exists; speculative coverage for hypothetical code is out of scope | Plan |
| `results` route test data | Must pass valid known/unknown counts | FormRequest validation runs before the ownership check, so invalid data would 422 before reaching 403 | Research |
| Existing 3 passing tests | Not refactored to use new trait | They already pass and cover their routes; refactoring adds review surface with no coverage gain | Plan |

## Scope

**In scope:**
- New `AssertsOwnershipEnforced` trait
- New `FlashcardSetOwnershipTest` sweeping `show`/`study`/`results`
- New index scoping test in existing `FlashcardSetIndexTest`
- `test-plan.md` §6.2/§6.4 cookbook update; §3 Phase 1 status → `complete`

**Out of scope:**
- Refactoring the 3 existing passing cross-user tests
- Introducing a Policy/Gate class
- Guest/unauthenticated route testing (Risk #6)
- Any `{flashcard}`-direct route handling (no such route exists)

## Architecture / Approach

Pure test-layer addition — no application code changes. A trait provides the "act as a second
user, assert forbidden" building block; a data-provider-style sweep test calls it once per bound
route so a future 4th route is a one-line addition, not a new test class.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Shared helper + route-sweep test | `AssertsOwnershipEnforced` trait + sweep test proving all 3 bound routes 403 for a non-owner | Missing the `results` route's validation-before-ownership ordering, causing a false 422 instead of 403 |
| 2. Index scoping test + cookbook update | Direct scoped-data test on `index`; §6.2/§6.4 filled in; phase marked complete | None significant — additive, low-risk |

**Prerequisites:** None — existing test suite and factories already support this.
**Estimated effort:** ~1 session, 2 phases, test-only diff.

## Open Risks & Assumptions

- Assumes no route added between now and implementation changes the bound-route list; if one
  does, add it to the sweep's data provider as part of this same change.

## Success Criteria (Summary)

- A future route binding `{flashcardSet}` without an ownership check causes the sweep test to
  fail immediately (verified manually by temporarily removing a check).
- `flashcard-sets.index` has a route-level assertion that another user's set data is excluded,
  not just a content-visibility check.
- `test-plan.md` §6.2/§6.4 give a future contributor (human or agent) a followable pattern.
