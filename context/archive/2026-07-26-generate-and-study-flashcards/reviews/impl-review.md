<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Generate & Study Flashcards Implementation Plan

- **Plan**: context/changes/generate-and-study-flashcards/plan.md
- **Scope**: Phase 1-3 of 3 (full plan)
- **Date**: 2026-07-26
- **Verdict**: REJECTED
- **Findings**: 1 critical, 0 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Pasted source text leaks into the session on validation failure

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/GenerateFlashcardsRequest.php, resources/views/dashboard.blade.php:24
- **Detail**: The plan's own "Critical Implementation Details" section calls out that pasted source text must never be written to the session store, and the controller correctly avoids `->withInput()` on the `FlashcardGenerationException` path. But that guard only covers the service-failure path. The *validation*-failure path (e.g. text over 8000 chars) never goes through the controller at all — Laravel's `FormRequest` throws `ValidationException` before `store()` runs, and the default exception handler (`vendor/laravel/framework/.../Foundation/Exceptions/Handler.php:892`) calls `->withInput(Arr::except($request->input(), $this->dontFlash))`. The app's `dontFlash` list only excludes password fields, so the full pasted `text` (up to 8000 chars) gets written into `session()['_old_input']['text']`. Confirmed directly: posting 8001 chars and dumping `session()->all()` shows the full string present in `_old_input`. `dashboard.blade.php:24` then echoes it back via `{{ old('text') }}`. `FlashcardGenerationTest` only asserts `assertSessionMissing('_old_input')` for the *service-exception* test — the validation-failure test (`test_text_exceeding_max_length_fails_validation`) has no equivalent assertion, so this gap was never caught.
- **Fix A ⭐ Recommended**: Add `text` to the global `dontFlash` list in `bootstrap/app.php` via `->withExceptions(fn ($exceptions) => $exceptions->dontFlash(['text']))`.
  - Strength: Mirrors the exact mechanism Laravel already uses for `password`/`password_confirmation` — it's the framework's own idiom for "never flash this field," a one-line change, and automatically covers any future form field also named `text`.
  - Tradeoff: Global by field name — if another unrelated form ever uses a `text` field, it also won't flash on failure. Low risk in this codebase today.
  - Confidence: HIGH — verified against `Handler.php:892` and Laravel 13's `bootstrap/app.php` exception-configuration API.
  - Blind spot: None significant.
- **Fix B**: Override `failedValidation()` in `GenerateFlashcardsRequest` to throw an `HttpResponseException` wrapping a manually-built redirect (`redirect()->route('dashboard')->withErrors($validator)`), bypassing `ValidationException`'s automatic `withInput()` entirely.
  - Strength: Scoped to this one form; no global exception-handling config touched.
  - Tradeoff: More code, and it's an unusual override most future maintainers won't expect — deviates from the standard FormRequest failure path.
  - Confidence: MEDIUM — works, but bypassing `ValidationException` this way is a less common Laravel pattern.
  - Blind spot: Haven't verified interaction with any global JSON-request handling if this endpoint is ever called with an `Accept: application/json` header.
- **Decision**: FIXED via Fix A — added `$exceptions->dontFlash(['text'])` to `bootstrap/app.php`. Verified: posting 8001 chars no longer puts `text` into `session()->getOldInput('text')` (confirmed `null`). Strengthened `FlashcardGenerationTest::test_text_exceeding_max_length_fails_validation` with this assertion (the original `assertSessionMissing('_old_input')` attempt was too strict — the key itself still exists as an empty array since other fields could still flash; fixed to assert the `text` value specifically is absent). Full suite (31 tests) and Pint pass.

### F2 — `store()` failure redirects to `route('dashboard')` instead of `back()`

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Http/Controllers/FlashcardSetController.php
- **Detail**: The plan's Phase 2 text says "redirect back with a flash error"; the implementation redirects explicitly to `route('dashboard')`. Functionally identical today since `store()`'s only caller is the dashboard form, but textually a deviation from the plan's wording.
- **Fix**: No action needed — noted for completeness only.
- **Decision**: SKIPPED — observation only, no fix required.

### F3 — `@json()` single-quote workaround in study.blade.php confirmed correct

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/views/flashcard-sets/study.blade.php
- **Detail**: Verified `@json($cards)` / `@json($cardLabel)` are single-variable references (no top-level commas in the `@json()` expression itself), so Blade's `compileJson()` applies the full default `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT` flag set. `JSON_HEX_APOS` is confirmed in play, so an apostrophe in flashcard content (e.g. "Git's purpose") is encoded as `'` and cannot break out of the single-quoted `x-data` attribute. No other Blade file in the diff repeats the buggy double-quote + inline-multi-key-`@json()` pattern that caused the original bug.
- **Fix**: No action needed.
- **Decision**: SKIPPED — observation only, no fix required.

### F4 — Authorization and external-boundary error handling confirmed solid

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Http/Controllers/FlashcardSetController.php, app/Http/Controllers/StudySessionController.php, app/Services/AnthropicFlashcardGenerator.php
- **Detail**: Every `FlashcardSet`-scoped action checks `$flashcardSet->user_id === auth()->id()` via `abort_unless(..., 403)`, exercised by tests. `AnthropicFlashcardGenerator` wraps every external-boundary failure mode (API exception, missing text block, JSON decode failure, malformed payload) into one `FlashcardGenerationException`, handled uniformly with a generic message that leaks no API internals.
- **Fix**: No action needed.
- **Decision**: SKIPPED — observation only, no fix required.
