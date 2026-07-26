# Generate & Study Flashcards Implementation Plan

## Overview

Replace the Breeze dashboard placeholder with the north-star flow from the PRD: a logged-in user pastes source text, Claude (Haiku 4.5) generates a flashcard set (title + question/answer pairs) synchronously within the request, the user previews the set, starts a study session, flips each card, self-grades known/unknown, and sees a final score.

## Current State Analysis

The repo is an unmodified Laravel 13 + Breeze skeleton (S-01, done): Blade + Alpine.js + Tailwind views, PHPUnit (not Pest) tests with `RefreshDatabase`, no service-layer directory yet, and only the default `users`/`cache`/`jobs` migrations. `routes/web.php` has a closure-based `/dashboard` route whose view (`resources/views/dashboard.blade.php`) already says "Flashcard generation is coming soon" — the natural replacement point. `.env.example` already stubs `ANTHROPIC_API_KEY` with a comment tying it to this feature, but no Anthropic SDK is installed and no AI/flashcard code exists anywhere in the codebase or prior plans.

## Desired End State

A logged-in user can paste text on the dashboard, submit it, and within the same request see a preview of AI-generated flashcards (title + Q/A pairs) persisted to their account. From the preview they start a study session, flip through each card revealing the answer, mark it known/unknown, and on the last card see a final score (X known / Y total). Pasted source text is never written to the database or logs — only the derived title and flashcards persist.

Verify by: registering/logging in, pasting a paragraph of educational text, confirming a flashcard set appears within a few seconds, running a full study session, and seeing the correct X/Y score.

### Key Discoveries:

- `resources/views/dashboard.blade.php:1-17` and `routes/web.php:10-12` — the placeholder to replace; keep the `dashboard` route name so the nav link (`layouts/navigation.blade.php`) keeps working.
- `.env.example` — `ANTHROPIC_API_KEY` already present; `config/services.php` follows a flat `'service' => ['key' => env(...)]` pattern (see `resend` entry) to extend.
- `app/Models/User.php` — Laravel 13 attribute-based model conventions (`#[Fillable]`, `#[Hidden]`, `casts()` method) to follow for the new models.
- No `app/Services/` directory exists — this plan introduces it for the first time.
- Testing convention is PHPUnit classes extending `Tests\TestCase` with `RefreshDatabase`, not Pest (`tests/Feature/Auth/RegistrationTest.php` is the reference pattern).

## What We're NOT Doing

- No spaced repetition / scheduling (PRD Non-Goal).
- No manual flashcard creation or editing (PRD Non-Goal) — sets are AI-only and final.
- No file upload / PDF / DOCX import — plain pasted text only.
- No sharing of flashcard sets between users.
- No quiz/multiple-choice mode — flip + self-grade only.
- No persistence of study-session results (score history) — PRD only requires showing the score at the end of the session that just ran, not storing it for later.
- No background/queued generation — synchronous, in-request only (see Phase 1 decision).
- No list-of-saved-sets UI — that's S-03, out of scope here (this plan's schema is shaped to support it without rework).

## Implementation Approach

Introduce a small `app/Services` layer with a `FlashcardGenerator` interface so the Anthropic-backed implementation can be swapped for a fake in tests. Generation is synchronous and uses the Messages API's structured-outputs feature (`output_config.format`) so the response is guaranteed to parse into `{title, flashcards: [{question, answer}]}` — this collapses "malformed JSON" and "AI error" into one failure class, handled identically. The study session itself is pure client-side Alpine state (card index, per-card known/unknown) with a single POST at the end that recomputes and renders the score server-side from the posted answer counts — no new schema needed for it.

## Critical Implementation Details

- **Privacy guardrail vs. Laravel's `withInput()` convenience.** On a generation failure, do **not** call `->withInput()` (or otherwise flash the submitted text to the session) to repopulate the textarea — even though that's the idiomatic Laravel pattern for failed-validation forms. Session flash data is written to the session store, which is exactly the kind of at-rest persistence the PRD guardrail rules out. Show a generic error and let the user re-paste from scratch.
- **Haiku 4.5 does not accept `thinking` or `effort`.** Per the Anthropic API reference, `effort` errors on Haiku 4.5 and there's no adaptive-thinking support at that tier — omit both parameters entirely from the `messages.create()` call rather than setting them to a default value.

## Phase 1: Data model & AI generation service

### Overview

Establish the `flashcard_sets`/`flashcards` schema, install the Anthropic PHP SDK, and build a `FlashcardGenerator` service behind an interface so the controller (Phase 2) and tests never talk to the SDK directly.

### Changes Required:

#### 1. Migrations

**File**: `database/migrations/<timestamp>_create_flashcard_sets_table.php`

**Intent**: One set belongs to one user; `title` is short and AI-derived (never the raw pasted text), giving S-03's future list view something to display.

**Contract**: `flashcard_sets(id, user_id FK->users cascade-on-delete, title string, timestamps)`.

**File**: `database/migrations/<timestamp>_create_flashcards_table.php`

**Intent**: Each generated Q/A pair belongs to one set.

**Contract**: `flashcards(id, flashcard_set_id FK->flashcard_sets cascade-on-delete, question text, answer text, timestamps)`.

#### 2. Models

**File**: `app/Models/FlashcardSet.php`

**Intent**: Owns the `belongsTo(User)` / `hasMany(Flashcard)` relationships.

**Contract**: Follow `app/Models/User.php`'s Laravel 13 conventions — `#[Fillable(['title'])]` attribute, `HasFactory` trait.

**File**: `app/Models/Flashcard.php`

**Intent**: Belongs to a `FlashcardSet`.

**Contract**: `#[Fillable(['question', 'answer'])]`, `belongsTo(FlashcardSet::class)`.

#### 3. Anthropic service configuration

**File**: `config/services.php`

**Intent**: Add the Anthropic API key entry following the existing flat pattern.

**Contract**: `'anthropic' => ['key' => env('ANTHROPIC_API_KEY')]`.

**File**: `composer.json`

**Intent**: Add the official SDK dependency.

**Contract**: `composer require anthropic-ai/sdk`.

#### 4. Flashcard generation service

**File**: `app/Services/FlashcardGenerator.php`

**Intent**: Interface so the controller and tests depend on an abstraction, not the SDK.

**Contract**: `interface FlashcardGenerator { public function generate(string $sourceText): GeneratedFlashcardSet; }` — throws `App\Exceptions\FlashcardGenerationException` on any failure.

**File**: `app/Services/GeneratedFlashcardSet.php`

**Intent**: Simple DTO carrying the parsed result across the service boundary.

**Contract**: Readonly value object with `string $title` and `array $flashcards` (each `['question' => string, 'answer' => string]`).

**File**: `app/Services/AnthropicFlashcardGenerator.php`

**Intent**: Calls Claude Haiku 4.5 with a structured-output JSON schema (`{title: string, flashcards: [{question, answer}]}`, 5–15 items) built from `$sourceText`, and maps the parsed response to `GeneratedFlashcardSet`. Wraps SDK exceptions (rate limit, auth, connection, bad request) and empty/malformed results into one `FlashcardGenerationException`.

**Contract**: `model: 'claude-haiku-4-5'`, no `thinking`/`effort` params (see Critical Implementation Details), `output_config.format` set to a `json_schema` matching the DTO shape, non-streaming (well under the 16K `max_tokens` streaming threshold).

**File**: `app/Exceptions/FlashcardGenerationException.php`

**Intent**: Single exception type the controller catches, regardless of underlying cause (timeout, API error, unparseable/empty output).

**Contract**: Extends `\RuntimeException`, no special fields needed.

#### 5. Service binding

**File**: `app/Providers/AppServiceProvider.php`

**Intent**: Bind the interface to the concrete Anthropic implementation in the container so it can be swapped for a fake in tests via `$this->app->bind(...)`.

**Contract**: `$this->app->bind(FlashcardGenerator::class, AnthropicFlashcardGenerator::class);` in `register()`.

### Success Criteria:

#### Automated Verification:

- Migrations apply cleanly: `php artisan migrate`
- Model relationship/factory tests pass: `php artisan test --filter=FlashcardSet`
- Full suite still passes: `php artisan test`
- Linting passes: `vendor/bin/pint --test`

#### Manual Verification:

- `composer.json` shows `anthropic-ai/sdk` installed and `composer install` succeeds
- `php artisan tinker` can resolve `FlashcardGenerator::class` from the container without error

---

## Phase 2: Generation flow (paste → preview)

### Overview

Replace the dashboard placeholder with the paste form; wire submission through validation and the generation service to a preview screen.

### Changes Required:

#### 1. Route & controller

**File**: `routes/web.php`

**Intent**: Add `POST /flashcard-sets` (generate) and `GET /flashcard-sets/{flashcardSet}` (preview) inside the existing `auth` middleware group; the `dashboard` route keeps its name but now returns the paste-form view.

**Contract**: Resource-style routes scoped under `auth`, e.g. `Route::post('/flashcard-sets', [FlashcardSetController::class, 'store'])->name('flashcard-sets.store')` and `Route::get('/flashcard-sets/{flashcardSet}', [FlashcardSetController::class, 'show'])->name('flashcard-sets.show')`.

**File**: `app/Http/Controllers/FlashcardSetController.php`

**Intent**: `store()` validates the pasted text, calls `FlashcardGenerator::generate()` inside a DB transaction that creates the `FlashcardSet` + its `Flashcard` rows, then redirects to `show`. On `FlashcardGenerationException`, redirect back with a flash error only (no `withInput()` — see Critical Implementation Details). `show()` authorizes that the set belongs to the current user (403 otherwise) and renders the preview.

**Contract**: Constructor-injects `FlashcardGenerator`; `store(GenerateFlashcardsRequest $request)`, `show(FlashcardSet $flashcardSet)`.

#### 2. Form request

**File**: `app/Http/Requests/GenerateFlashcardsRequest.php`

**Intent**: Validates the pasted text.

**Contract**: `rules(): array` returns `['text' => ['required', 'string', 'max:8000']]`.

#### 3. Views

**File**: `resources/views/dashboard.blade.php`

**Intent**: Replace the "coming soon" placeholder with a `<textarea>` form posting to `flashcard-sets.store`, using the existing `x-app-layout` and Breeze form components (`x-input-label`, `x-input-error`, `x-primary-button`).

**Contract**: Standard Breeze-style form markup; displays the flash error (if any) from a failed generation attempt.

**File**: `resources/views/flashcard-sets/show.blade.php`

**Intent**: Lists the generated title and each question/answer pair, with a "Start session" link to the Phase 3 study route.

**Contract**: `x-app-layout` page iterating `$flashcardSet->flashcards`; link to `route('flashcard-sets.study', $flashcardSet)`.

### Success Criteria:

#### Automated Verification:

- Feature test: valid paste → faked generator → redirect to preview + DB rows created: `php artisan test --filter=FlashcardGeneration`
- Feature test: generator failure → redirected back with flash error, no `FlashcardSet` row created
- Feature test: text exceeding 8000 chars fails validation
- Feature test: viewing another user's set returns 403
- Full suite passes: `php artisan test`
- Linting passes: `vendor/bin/pint --test`

#### Manual Verification:

- Paste a real paragraph of text on `/dashboard`, submit, and see a flashcard preview appear within a few seconds
- Trigger a generation failure (e.g. temporarily invalid API key) and confirm the error message shows with no lingering pasted text in the form
- Confirm the "Start session" link is present and points at the study route

---

## Phase 3: Study session (flip → rate → score)

### Overview

Client-side flip/rate interaction over the already-generated flashcards, ending in a score screen.

### Changes Required:

#### 1. Route & controller

**File**: `routes/web.php`

**Intent**: Add the study and results routes, scoped under `auth`.

**Contract**: `GET /flashcard-sets/{flashcardSet}/study` (name `flashcard-sets.study`) and `POST /flashcard-sets/{flashcardSet}/results` (name `flashcard-sets.results`).

**File**: `app/Http/Controllers/StudySessionController.php`

**Intent**: `study()` authorizes ownership and renders the session view with the set's flashcards serialized for Alpine. `results()` authorizes ownership, validates the posted known/unknown counts sum to the set's flashcard count (integrity check against a tampered client), and returns the results view directly (no redirect, no persistence — see "What We're NOT Doing").

**Contract**: `study(FlashcardSet $flashcardSet)`, `results(SubmitStudyResultsRequest $request, FlashcardSet $flashcardSet)`.

#### 2. Form request

**File**: `app/Http/Requests/SubmitStudyResultsRequest.php`

**Intent**: Validates the posted score counts.

**Contract**: `['known_count' => ['required', 'integer', 'min:0'], 'unknown_count' => ['required', 'integer', 'min:0']]`; controller checks `known_count + unknown_count === $flashcardSet->flashcards()->count()`, aborting 422 on mismatch.

#### 3. Views

**File**: `resources/views/flashcard-sets/study.blade.php`

**Intent**: Alpine component holding `currentIndex`, `flipped`, `knownCount`, `unknownCount` over a JSON-embedded flashcards array; "known"/"unknown" buttons advance to the next card or, on the last card, submit the POST form with the final counts.

**Contract**: `x-data` initialized from `@json($flashcardSet->flashcards)`; no server round-trip per card (per the approved session-state decision).

**File**: `resources/views/flashcard-sets/results.blade.php`

**Intent**: Shows "X of Y known" using the counts passed in from the controller.

**Contract**: `x-app-layout` page rendering the score handed to it by `StudySessionController::results()`.

### Success Criteria:

#### Automated Verification:

- Feature test: posting valid counts to `results` renders the score correctly: `php artisan test --filter=StudySession`
- Feature test: counts not summing to the flashcard count return 422
- Feature test: accessing another user's study/results routes returns 403
- Full suite passes: `php artisan test`
- Linting passes: `vendor/bin/pint --test`

#### Manual Verification:

- Run a full study session in the browser: flip every card, mark known/unknown, confirm the final screen shows the correct X/Y
- Refresh mid-session and confirm progress resets (expected — no resume support per PRD)
- Confirm the whole loop (paste → preview → study → score) works end-to-end for a fresh account

---

## Testing Strategy

### Unit Tests:

- `GeneratedFlashcardSet` DTO shape / `AnthropicFlashcardGenerator`'s schema-building logic, if extracted into a testable pure function

### Integration Tests:

- Full paste → generate → preview → study → results flow, with `FlashcardGenerator` bound to a fake in the container (`$this->app->bind(FlashcardGenerator::class, fn () => new class implements FlashcardGenerator { ... })`)
- Ownership checks (403 on cross-user access) for both the preview and study/results routes

### Manual Testing Steps:

1. Register a new account, land on the dashboard, confirm the paste form (not the old placeholder) is shown
2. Paste a paragraph of real educational text and submit; confirm flashcards appear within a few seconds
3. Start a study session, flip and rate every card, confirm the score screen shows the correct count
4. Temporarily break the Anthropic API key and confirm a failed generation shows a clean error with no pasted text retained anywhere

## Performance Considerations

Generation is synchronous and must fit comfortably within the PRD's ~10s NFR for ~400–500 word inputs; Haiku 4.5 is the fastest Claude tier, and structured outputs avoid a slow client-side parsing/retry loop on malformed text.

## Migration Notes

Not applicable — no existing data to migrate; this is new schema on a greenfield feature.

## References

- PRD: `context/foundation/prd.md`
- Roadmap slice: `context/foundation/roadmap.md` (S-02)
- Prior conventions: `context/archive/2026-07-21-minimal-auth-for-generation/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Data model & AI generation service

#### Automated

- [x] 1.1 Migrations apply cleanly: `php artisan migrate` — 03a9d46
- [x] 1.2 Model relationship/factory tests pass: `php artisan test --filter=FlashcardSet` — 03a9d46
- [x] 1.3 Full suite still passes: `php artisan test` — 03a9d46
- [x] 1.4 Linting passes: `vendor/bin/pint --test` — 03a9d46

#### Manual

- [x] 1.5 `anthropic-ai/sdk` installed and `composer install` succeeds — 03a9d46
- [x] 1.6 `FlashcardGenerator::class` resolves from the container via `php artisan tinker` — 03a9d46

### Phase 2: Generation flow (paste → preview)

#### Automated

- [x] 2.1 Feature test: valid paste → redirect to preview + DB rows created — bf05fec
- [x] 2.2 Feature test: generator failure → flash error, no row created — bf05fec
- [x] 2.3 Feature test: text over 8000 chars fails validation — bf05fec
- [x] 2.4 Feature test: viewing another user's set returns 403 — bf05fec
- [x] 2.5 Full suite passes: `php artisan test` — bf05fec
- [x] 2.6 Linting passes: `vendor/bin/pint --test` — bf05fec

#### Manual

- [x] 2.7 Real paste on `/dashboard` produces a flashcard preview within a few seconds — bf05fec
- [x] 2.8 Generation failure shows a clean error with no pasted text retained — bf05fec
- [ ] 2.9 "Start session" link present and correct (deferred — link added in Phase 3 once the study route exists)

### Phase 3: Study session (flip → rate → score)

#### Automated

- [x] 3.1 Feature test: valid counts render correct score
- [x] 3.2 Feature test: mismatched counts return 422
- [x] 3.3 Feature test: cross-user access returns 403
- [x] 3.4 Full suite passes: `php artisan test`
- [x] 3.5 Linting passes: `vendor/bin/pint --test`

#### Manual

- [ ] 3.6 Full browser session: flip, rate, correct final score shown
- [ ] 3.7 Refresh mid-session resets progress (expected)
- [ ] 3.8 End-to-end loop works for a fresh account
