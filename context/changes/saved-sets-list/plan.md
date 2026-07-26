# Saved Flashcard Sets List Implementation Plan

## Overview

Add a read-only page where a logged-in user sees all of their previously generated flashcard sets (roadmap S-03, PRD FR-010), newest first, each linking to the existing set-detail page.

## Current State Analysis

`flashcard_sets` and `flashcards` tables, the `FlashcardSet`/`User` models (with `User::flashcardSets(): HasMany`), and `FlashcardSetController` (`store`, `show`) already exist from S-02 — the generate-and-study flow. There is no way today for a user to see sets other than the one just created; navigating away loses access to prior sets entirely except by guessing the URL. The top nav (`resources/views/layouts/navigation.blade.php`) currently has a single "Dashboard" link in both desktop and mobile menus.

## Desired End State

A logged-in user can click "Moje zestawy" in the nav and see a list of all their flashcard sets, newest first, each showing its title and creation date and linking to `flashcard-sets.show`. A user with no sets yet sees a friendly empty-state message with a link back to the dashboard's paste-text form. The list only ever shows the current user's own sets (never another user's).

### Key Discoveries:

- `app/Http/Controllers/FlashcardSetController.php:39-46` already has the ownership-check + `show` pattern (`abort_unless($flashcardSet->user_id === auth()->id(), 403)`) to mirror for consistency, though `index` scopes via the `auth()->user()->flashcardSets()` relation instead, so no per-row ownership check is needed there.
- `App\Models\User::flashcardSets()` (`app/Models/User.php:34-37`) is the query entry point — `auth()->user()->flashcardSets()->latest()->get()`.
- All user-facing strings go through `__()` and get a Polish translation added to `lang/pl.json` (see `context/foundation/lessons.md` — every new visible string needs a `lang/pl.json` entry; `APP_LOCALE=pl`, `APP_FALLBACK_LOCALE=en`).
- Nav pattern to follow: `resources/views/layouts/navigation.blade.php:15-17` (desktop `x-nav-link`) and `:70-73` (mobile `x-responsive-nav-link`), both keyed off `request()->routeIs(...)`.

## What We're NOT Doing

- No pagination (target scale is small; a straight `get()` is enough for MVP — can be added later without changing the route or view contract).
- No search, filtering, or sorting controls — fixed newest-first order only.
- No card-count-per-set display (deferred; title + date is enough to tell sets apart per this planning round).
- No per-row "Study" shortcut button — clicking a row goes to the existing detail page, which already has the "Start session" CTA.
- No changes to `store`/`show`/study session behavior — this is purely an additive read path.

## Implementation Approach

Add one `index` method to the existing `FlashcardSetController`, one `GET /flashcard-sets` route, one new Blade view, and one new nav link (desktop + mobile). No new models, migrations, or services — this is a straight read of data that S-02 already persists.

## Phase 1: Saved sets list page

### Overview

Adds the index route, controller action, view, and nav entry point; covers ownership scoping and the empty state with feature tests.

### Changes Required:

#### 1. Route

**File**: `routes/web.php`

**Intent**: Expose the list at `GET /flashcard-sets`, auth-protected like the other flashcard-sets routes.

**Contract**: Add `Route::get('/flashcard-sets', [FlashcardSetController::class, 'index'])->name('flashcard-sets.index');` inside the existing `auth` middleware group, placed before the `{flashcardSet}` show route (Laravel resolves static segments before route-model-bound ones, but ordering it first avoids any ambiguity).

#### 2. Controller

**File**: `app/Http/Controllers/FlashcardSetController.php`

**Intent**: Return the authenticated user's flashcard sets, newest first, to a new `flashcard-sets.index` view.

**Contract**: New public method `index(Request $request): View` returning `view('flashcard-sets.index', ['flashcardSets' => $request->user()->flashcardSets()->latest()->get()])`.

#### 3. View

**File**: `resources/views/flashcard-sets/index.blade.php`

**Intent**: List each flashcard set as a link to its detail page, showing title and creation date; show a friendly empty state with a link to the dashboard when the collection is empty.

**Contract**: Follows the existing `x-app-layout` + header-slot + `max-w-7xl` card pattern used in `dashboard.blade.php` and `flashcard-sets/show.blade.php`. Each row links via `route('flashcard-sets.show', $flashcardSet)`. Empty state renders when `$flashcardSets->isEmpty()`, with a link via `route('dashboard')`.

#### 4. Navigation

**File**: `resources/views/layouts/navigation.blade.php`

**Intent**: Make the list reachable from anywhere in the app.

**Contract**: Add an `x-nav-link` (desktop, alongside the existing Dashboard link at line 15-17) and an `x-responsive-nav-link` (mobile, alongside line 70-73) pointing to `route('flashcard-sets.index')`, active-state keyed on `request()->routeIs('flashcard-sets.index')`, labeled `__('My sets')`.

#### 5. Translations

**File**: `lang/pl.json`

**Intent**: Satisfy the Polish-only UI rule for every new string this phase introduces.

**Contract**: Add entries for `"My sets"`, and whatever exact English strings the view uses for the empty-state message and "back to dashboard" link (e.g. `"You don't have any flashcard sets yet."` / `"Generate your first set"`) — write the actual strings when authoring the view, then add matching Polish translations.

### Success Criteria:

#### Automated Verification:

- Feature tests pass: `php artisan test --filter=FlashcardSetIndexTest`
- Full suite passes: `php artisan test`
- Pint formatting passes: `vendor/bin/pint --test`

#### Manual Verification:

- Logged in as a user with 2+ flashcard sets, visiting `/flashcard-sets` shows them newest-first with correct titles/dates, and each links to the right detail page.
- Logged in as a user with zero sets, `/flashcard-sets` shows the empty-state message and a working link back to the dashboard.
- A user cannot see another user's sets by visiting `/flashcard-sets` (only their own `flashcardSets()` relation is queried).
- The "My sets" nav link is visible and highlighted as active on both desktop and mobile nav when on the list page.
- All visible text on the page renders in Polish.

---

## Testing Strategy

### Unit Tests:

- None needed beyond existing `FlashcardSetTest` model-relationship coverage (already covers `user()`/`flashcardSets()`).

### Integration Tests:

- New `tests/Feature/FlashcardSetIndexTest.php`:
  - A logged-in user sees only their own flashcard sets, newest first.
  - A logged-in user with no sets sees the empty-state content.
  - A guest is redirected to login when visiting `/flashcard-sets`.

### Manual Testing Steps:

1. Generate two flashcard sets as the same user (via the dashboard flow), then visit `/flashcard-sets` — confirm both appear, most recent on top.
2. Click a listed set — confirm it opens the correct `flashcard-sets.show` page.
3. Log in as a second user with no sets — confirm the empty state appears, and the link routes back to the dashboard.
4. Confirm the "My sets" link appears in both the desktop dropdown-adjacent nav and the mobile hamburger menu.

## Performance Considerations

None — a single indexed foreign-key query (`flashcard_sets.user_id`) against a small per-user row count.

## Migration Notes

Not applicable — no schema changes.

## References

- Prior implementation: `context/archive/2026-07-26-generate-and-study-flashcards/plan.md`
- Roadmap slice: `context/foundation/roadmap.md` (S-03)
- Lesson applied: `context/foundation/lessons.md` (Polish-only UI strings)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Saved sets list page

#### Automated

- [x] 1.1 Feature tests pass: `php artisan test --filter=FlashcardSetIndexTest`
- [x] 1.2 Full suite passes: `php artisan test`
- [x] 1.3 Pint formatting passes: `vendor/bin/pint --test`

#### Manual

- [x] 1.4 Logged in as a user with 2+ flashcard sets, visiting `/flashcard-sets` shows them newest-first with correct titles/dates, and each links to the right detail page.
- [x] 1.5 Logged in as a user with zero sets, `/flashcard-sets` shows the empty-state message and a working link back to the dashboard.
- [x] 1.6 A user cannot see another user's sets by visiting `/flashcard-sets` (only their own `flashcardSets()` relation is queried).
- [x] 1.7 The "My sets" nav link is visible and highlighted as active on both desktop and mobile nav when on the list page.
- [x] 1.8 All visible text on the page renders in Polish.
