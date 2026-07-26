# Saved Flashcard Sets List — Plan Brief

> Full plan: `context/changes/saved-sets-list/plan.md`

## What & Why

Let a logged-in user see all of their previously generated flashcard sets in one place (roadmap S-03, PRD FR-010). Today the only way to reach a set is the URL you land on right after generating it — navigate away and it's effectively gone from view.

## Starting Point

S-02 (generate-and-study-flashcards) already built `flashcard_sets`/`flashcards` tables, the `User::flashcardSets()` relation, and `FlashcardSetController` with `store` and `show`. Nothing lists a user's sets yet — the nav only has "Dashboard."

## Desired End State

A "My sets" nav link takes the user to a page listing all their flashcard sets, newest first, each showing its title and creation date and linking to the existing set-detail page. Users with no sets yet see a friendly message pointing them back to the dashboard's paste-text form.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Entry point | Dedicated `/flashcard-sets` page + nav link | Matches the existing Breeze nav-link pattern; keeps "generate" and "browse" concerns separate |
| Row content | Title + created date | Both fields already exist on `flashcard_sets`; zero schema change |
| Sort order | Newest first | The just-generated set is the one a user is most likely looking for |
| Empty state | Friendly message + link to dashboard | Turns a dead end into a next action, consistent with the single-flow product |
| Row click target | Existing `flashcard-sets.show` page | Zero new view work; reuses the S-02 preview + "Start session" flow |
| Pagination | None for MVP | Small target scale; can be added later without changing the route/view contract |

## Scope

**In scope:** index route, controller action, list view, nav link (desktop + mobile), Polish translations, feature tests.

**Out of scope:** pagination, search/filter/sort controls, per-set card count, per-row "Study" shortcut, any change to generation or study-session behavior.

## Architecture / Approach

One `index` method on the existing `FlashcardSetController`, scoped via `auth()->user()->flashcardSets()->latest()->get()`, rendering one new Blade view that follows the established `x-app-layout` card pattern. No new models, migrations, or services.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Saved sets list page | Route, controller, view, nav link, tests, translations | None significant — additive read-only feature on top of existing data |

**Prerequisites:** S-02 (generate-and-study-flashcards) — done.
**Estimated effort:** ~1 short session, single phase.

## Open Risks & Assumptions

- None — this is a straightforward read path on data that already exists and is already correctly scoped per-user.

## Success Criteria (Summary)

- A logged-in user sees exactly their own flashcard sets, newest first, and can navigate from the list to any set's detail page.
- A user with zero sets sees a clear, actionable empty state instead of a blank page.
- All visible text renders in Polish, consistent with the rest of the app.
