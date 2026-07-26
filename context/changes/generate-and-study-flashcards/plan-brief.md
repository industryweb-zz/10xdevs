# Generate & Study Flashcards — Plan Brief

> Full plan: `context/changes/generate-and-study-flashcards/plan.md`

## What & Why

Users can paste source text, get an AI-generated flashcard set (question/answer pairs), and immediately study it with a flip + self-grade session that ends in a score. This is the PRD's single user story (US-01) and its Primary Success Criterion — validating whether AI can turn arbitrary pasted text into flashcards good enough to actually learn from.

## Starting Point

Unmodified Laravel 13 + Breeze skeleton (auth done in S-01). Blade + Alpine + Tailwind, PHPUnit tests, no service layer yet, no AI integration yet. The dashboard is a placeholder that literally says "Flashcard generation is coming soon," and `ANTHROPIC_API_KEY` is already stubbed in `.env.example` for this feature.

## Desired End State

A logged-in user pastes text on the dashboard, sees a flashcard preview within a few seconds, starts a study session, flips and rates every card, and sees "X of Y known" at the end. Pasted text is never stored — only the derived title and flashcards.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| AI model | Claude Haiku 4.5 | Fastest/cheapest tier, comfortably clears the 10s NFR, chosen over the higher-quality Sonnet 5 recommendation for cost on a solo MVP |
| SDK | Official Anthropic PHP SDK | Typed exceptions for error handling; matches AI toolkit guidance |
| Generation | Synchronous, in-request | Matches PRD's "within seconds" framing; no queue/polling UI needed for MVP scale |
| Output format | Structured outputs (JSON schema) | Eliminates most malformed-output failures by construction |
| Data model | `flashcard_sets` + `flashcards` tables | Ready for S-03's saved-sets list without rework |
| Study session state | Client-side Alpine, one POST at the end | Instant flip/rate interaction; no per-card round trip |
| Card count | AI-decided (5–15) | Matches PRD's "identifies key concepts" framing — variable by text density |
| Input limit | Max ~8,000 chars, no minimum | Bounds latency/cost without blocking short notes |
| AI failure handling | One error path (flash + retry) for timeouts, malformed output, and empty results | Simpler UX and code; remedy is identical either way |
| Privacy enforcement | Never persist raw text; no `withInput()` on error; no logging of request/response bodies | Satisfies the PRD guardrail by construction — nowhere for the text to leak from |
| Testing | Fake `FlashcardGenerator` in feature tests | Fast, deterministic, no API cost in CI, matches existing PHPUnit + RefreshDatabase pattern |
| Score persistence | Not stored — only shown once at session end | PRD doesn't require session history; avoids unrequested scope |

## Scope

**In scope:** paste-text form, synchronous AI generation, flashcard preview, flip/rate study session, end-of-session score.

**Out of scope:** spaced repetition, manual flashcard creation/editing, file import, sharing, quiz mode, session-history persistence, saved-sets list (S-03).

## Architecture / Approach

New `app/Services` layer: a `FlashcardGenerator` interface with an `AnthropicFlashcardGenerator` implementation (Claude Haiku 4.5 + structured outputs), bound in the container so tests can swap in a fake. Two new tables (`flashcard_sets`, `flashcards`) hold only AI-derived output, never the source text. The study session is pure client-side Alpine state with a single results POST that's validated but not persisted.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Data model & AI generation service | Schema, SDK install, `FlashcardGenerator` service | Structured-output schema/parsing correctness |
| 2. Generation flow (paste → preview) | Dashboard paste form, generation controller, preview view | Privacy guardrail (no accidental text retention on error) |
| 3. Study session (flip → rate → score) | Alpine flip/rate UI, results endpoint, score view | None significant — pure client-side state |

**Prerequisites:** S-01 (auth) — done.
**Estimated effort:** ~2–3 sessions across 3 phases, consistent with the 3-week after-hours solo MVP budget.

## Open Risks & Assumptions

- Haiku 4.5's flashcard quality is the real unknown — if it's too weak to validate the north-star hypothesis, the fix is a model swap (`AnthropicFlashcardGenerator`'s model string), not a redesign.
- Structured outputs on Haiku 4.5 assumed supported per the Anthropic API docs — verify on first real call in Phase 1.

## Success Criteria (Summary)

- A user can go from pasted text to a completed, scored study session in one unbroken flow, in a browser, within seconds of submitting.
- No pasted source text is ever visible in the database, logs, or session store after the request completes.
- All PHPUnit feature tests pass with a faked AI client — no real API calls in CI.
