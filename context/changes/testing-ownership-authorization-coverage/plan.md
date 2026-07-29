# Ownership & Authorization Coverage Implementation Plan

## Overview

Test-plan Rollout Phase 1 (`context/foundation/test-plan.md` §3, Risk #1 — IDOR). Research
(`context/changes/testing-ownership-authorization-coverage/research.md`) confirmed the three
existing `{flashcardSet}`-bound routes (`show`, `study`, `results`) are already protected by a
per-action `abort_unless($flashcardSet->user_id === auth()->id(), 403)` check, each with its own
passing cross-user 403 test. The live risk is not a current gap — it's that the check is
duplicated three times by hand with no Policy/Gate, so a future bound route can silently ship
without it. This plan adds a regression guard against that fragility, plus closes the one
confirmed partial gap: `flashcard-sets.index`'s relation-scoped query is only checked indirectly
today (`assertDontSee`), not via a route-level assertion of the scoping mechanism itself.

## Current State Analysis

- All three bound routes (`FlashcardSetController::show`, `StudySessionController::study`,
  `StudySessionController::results`) already have working ownership checks and dedicated
  cross-user 403 tests (see research.md "Existing test coverage").
- `flashcard-sets.index` and `flashcard-sets.store` use a relation-scoped query/create
  (`$request->user()->flashcardSets()`), not a route-model-bound ID check — different mechanism,
  same risk category (a scoping omission would leak/create against the wrong user).
- No Policy/Gate infrastructure exists anywhere in the app.
- `tests/TestCase.php` is a bare pass-through — no shared multi-user test helper exists yet; each
  of the three existing cross-user tests (`FlashcardGenerationTest.php:81`,
  `StudySessionTest.php:44`, `StudySessionTest.php:55`) re-implements owner/other-user/set setup
  inline.
- `StudySessionController::results` runs its `SubmitStudyResultsRequest` FormRequest validation
  *before* the `abort_unless` ownership check executes (FormRequest resolution happens ahead of
  the controller body). The existing cross-user test for `results` already accounts for this by
  posting counts that pass validation (`known_count: 1, unknown_count: 0` against a 1-flashcard
  set) so the response reaches the ownership check and returns 403, not 422. Any new test hitting
  this route must do the same.

### Key Discoveries:

- `routes/web.php:16-27` — the full auth-gated route group; three routes bind `{flashcardSet}`
  (`show` GET, `study` GET, `results` POST), two do not (`index` GET, `store` POST).
- `database/factories/FlashcardSetFactory.php:19-25` — supports `->for($owner)` to assign a set
  to a specific user; this is the pattern all new tests should reuse.
- `app/Http/Controllers/StudySessionController.php:20-30` — confirms validation-before-ownership
  ordering on `results`.

## Desired End State

A new feature test file sweeps every `{flashcardSet}`-bound route via a small shared assertion
helper, so adding a new bound route without an ownership check in the future causes an
immediately-failing test rather than silent passage. A second test directly proves
`flashcard-sets.index`'s scoped query never returns another user's set data. `test-plan.md` §6.2
and §6.4 are filled in with the concrete pattern for future contributors.

Verification: `php artisan test --filter=FlashcardSetOwnershipTest` and the full suite both pass;
`vendor/bin/pint` reports no changes needed on new files.

## What We're NOT Doing

- Not refactoring the three existing passing cross-user tests
  (`FlashcardGenerationTest.php:81`, `StudySessionTest.php:44`, `StudySessionTest.php:55`) to use
  the new shared helper. They already pass and cover their routes; touching them adds review
  surface with no risk-coverage gain. The new sweep test is additive.
- Not introducing a Policy/Gate class. Research confirmed this is convention, not a documented
  trade-off requiring correction — restructuring authorization mechanism is out of scope for a
  test-coverage phase.
- Not testing the guest (unauthenticated) case for these routes — that's test-plan Risk #6
  (auth/session), a separate rollout phase.
- Not adding any check or test for a hypothetical future `{flashcard}`-direct route — no such
  route exists; speculative coverage for code that doesn't exist yet is out of scope.
- Not testing a 404 response anywhere — all three bound routes return 403 via `abort_unless`,
  never a scoped `findOrFail` that would 404.

## Implementation Approach

Two phases. Phase 1 adds the shared assertion trait and the route-sweep test (the core new
regression guard for the "future route forgets the check" risk). Phase 2 adds the index scoping
test and updates the test-plan cookbook (§6.2, §6.4) with the pattern, closing out this rollout
row.

## Phase 1: Shared ownership-assertion helper + route-sweep test

### Overview

Add a small reusable trait for "act as a second user against an owned resource, assert
forbidden," then use it in a new sweep test that iterates all three `{flashcardSet}`-bound routes.

### Changes Required:

#### 1. Ownership-assertion trait

**File**: `tests/Concerns/AssertsOwnershipEnforced.php` (new)

**Intent**: Provide one reusable helper that creates an owner + a distinct other user + an owned
`FlashcardSet`, issues a request as the other user against a given route/method/payload, and
asserts the response is forbidden. This is the building block the sweep test calls once per
route; it exists because the sweep needs it immediately (not a speculative abstraction).

**Contract**: A trait with a single public method, e.g.
`assertRouteForbiddenToOtherUser(string $method, string $routeName, array $routeParams = [], array $data = []): void`,
that: creates `$owner = User::factory()->create()`, `$otherUser = User::factory()->create()`,
builds the `FlashcardSet` via `FlashcardSet::factory()->for($owner)->create()` (with any
`Flashcard` rows the caller's route needs created via a passed-in callback or pre-built set —
keep the signature simple: accept an already-built `FlashcardSet $flashcardSet` and `User
$otherUser` as parameters rather than constructing them internally, so callers control flashcard
counts for routes like `results` that need a matching count). Internally calls
`$this->actingAs($otherUser)->{$method}(route($routeName, $flashcardSet), $data)` and asserts
`->assertForbidden()`.

#### 2. Route-sweep test

**File**: `tests/Feature/FlashcardSetOwnershipTest.php` (new)

**Intent**: One test per bound route (or a single data-provider-driven test) that, for each of
`flashcard-sets.show` (GET), `flashcard-sets.study` (GET), and `flashcard-sets.results` (POST),
uses the new trait to prove a non-owner is forbidden. Because `results` validates before checking
ownership, its case must post a `known_count`/`unknown_count` pair that sums to the created
set's flashcard count so the request reaches the ownership check.

**Contract**: Use PHPUnit's `#[DataProvider]` (or three explicit test methods, whichever keeps
route-specific setup — e.g. `results` needing a `Flashcard` row and valid counts — readable) so
that adding a fourth `{flashcardSet}`-bound route later means adding one entry, not writing a
new test class. `use RefreshDatabase;` per the existing convention in this test suite.

### Success Criteria:

#### Automated Verification:

- [ ] New test file passes: `php artisan test --filter=FlashcardSetOwnershipTest`
- [ ] Full suite still passes: `php artisan test`
- [ ] Pint reports no formatting issues on new files: `vendor/bin/pint --test`

#### Manual Verification:

- [ ] Temporarily remove the `abort_unless` line from one of the three controller actions and
      confirm the sweep test fails (proves the guard actually catches a missing check), then
      restore the line.

---

## Phase 2: Index scoping test + cookbook update

### Overview

Add a direct test proving the `flashcard-sets.index` scoped query never surfaces another user's
set data at the route level, then fill in `test-plan.md` §6.2 and §6.4 with the pattern this
phase established.

### Changes Required:

#### 1. Index scoping test

**File**: `tests/Feature/FlashcardSetIndexTest.php` (existing — add a test method)

**Intent**: Extend the existing index test file (same file, same class, matching its established
convention) with a test that directly asserts the response's underlying data — not just rendered
HTML via `assertDontSee` — excludes another user's set. This closes the gap research flagged:
today's only index cross-user check is a content-leak assertion on the view, not a check of the
scoping mechanism itself.

**Contract**: A new test method, e.g. `test_index_response_never_includes_another_users_flashcard_set_id`,
that creates a set for `$user` and a set for `$otherUser`, requests `flashcard-sets.index` as
`$user`, and asserts on the view/response data structure (e.g.
`$response->assertViewHas('flashcardSets', fn ($sets) => $sets->doesntContain('id', $otherUsersSet->id))`)
rather than string-matching rendered HTML — a stronger oracle against the PRD's per-user scoping
rule, independent of any future template wording change.

#### 2. Cookbook update

**File**: `context/foundation/test-plan.md`

**Intent**: Replace the `TBD — see §3 Phase 1` placeholders in §6.2 and §6.4 with the concrete
pattern this phase established, per the 10x-test-plan lesson's requirement that each rollout
phase's plan ends with a cookbook update.

**Contract**: §6.2 ("Adding an integration (feature) test") gains a short paragraph naming the
two ownership mechanisms (per-record `abort_unless` vs. relation-scoped query), the
`AssertsOwnershipEnforced` trait location, and the reference test file
(`tests/Feature/FlashcardSetOwnershipTest.php`). §6.4 ("Adding a test for a new API endpoint")
gains one line: any new route binding `{flashcardSet}` (or a future `{flashcard}`-direct route)
should be added to the sweep's data provider. Also update `context/foundation/test-plan.md` §3
row 1 `Status` to `complete` and `context/changes/testing-ownership-authorization-coverage/change.md`
front matter `status: implemented`.

### Success Criteria:

#### Automated Verification:

- [ ] New index test passes: `php artisan test --filter=FlashcardSetIndexTest`
- [ ] Full suite still passes: `php artisan test`
- [ ] Pint reports no formatting issues: `vendor/bin/pint --test`

#### Manual Verification:

- [ ] Read the updated §6.2/§6.4 in `test-plan.md` and confirm a future contributor could follow
      it to add a new ownership-checked route to the sweep without re-deriving the pattern.

---

## Testing Strategy

### Unit Tests:

- None — this phase is entirely integration-level (HTTP feature tests), per test-plan §4 stack
  table; no unit-testable logic is being added.

### Integration Tests:

- Route-sweep test across `show`/`study`/`results` (Phase 1).
- Index scoped-query test (Phase 2).

### Manual Testing Steps:

1. Remove one `abort_unless` line, confirm the sweep test fails, restore it (Phase 1 manual
   verification).
2. Read the cookbook update for clarity (Phase 2 manual verification).

## Performance Considerations

None — test-only change, `RefreshDatabase` per-test as already established in this suite.

## Migration Notes

Not applicable — no schema or data changes.

## References

- Research: `context/changes/testing-ownership-authorization-coverage/research.md`
- Test-plan risk and guidance: `context/foundation/test-plan.md` §2 Risk #1, §3 row 1
- Existing pattern to follow: `tests/Feature/StudySessionTest.php:44-53`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared ownership-assertion helper + route-sweep test

#### Automated

- [x] 1.1 New test file passes: `php artisan test --filter=FlashcardSetOwnershipTest` — dc3ce50
- [x] 1.2 Full suite still passes: `php artisan test` — dc3ce50
- [x] 1.3 Pint reports no formatting issues on new files: `vendor/bin/pint --test` — dc3ce50

#### Manual

- [x] 1.4 Temporarily remove an `abort_unless` line and confirm the sweep test fails, then restore it — dc3ce50

### Phase 2: Index scoping test + cookbook update

#### Automated

- [x] 2.1 New index test passes: `php artisan test --filter=FlashcardSetIndexTest` — dcff80d
- [x] 2.2 Full suite still passes: `php artisan test` — dcff80d
- [x] 2.3 Pint reports no formatting issues: `vendor/bin/pint --test` — dcff80d

#### Manual

- [x] 2.4 Read the updated §6.2/§6.4 in `test-plan.md` and confirm it's followable — dcff80d
