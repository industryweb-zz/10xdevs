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

## 10xDevs AI Toolkit - Module 2, Lesson 3

Review AI-generated code before merge with the **implementation review chain**:

```
/10x-implement -> /10x-impl-review -> triage -> (/10x-lesson | fix | skip | disagree)
```

`/10x-impl-review` is the lesson focus. Review is a quality gate, not an instruction to fix every finding.

### Task Router - Where to start

| Skill | Use it when |
| --- | --- |
| **Code review (lesson focus)** | |
| `/10x-impl-review <change-id>` | You have implemented code and want a structured review before merge. The skill checks plan adherence, scope discipline, safety and quality, architecture, pattern consistency, and success criteria, then presents findings for triage. |
| **Recurring lesson outcome** | |
| `/10x-lesson` | A finding reveals a recurring project rule or agent failure pattern. Record it in `context/foundation/lessons.md` instead of treating it as a one-off note. |

### Triage discipline

- Severity says how bad the finding is. Impact says how much the decision matters now.
- Valid outcomes: fix now, fix differently, skip, accept as risk, record as recurring rule (`/10x-lesson`), disagree.
- Fix critical findings. Do not burn hours on low-impact observations just because the agent found them.
- Conscious skipping of low-impact findings is a valid review outcome, not negligence.
- If you disagree with a finding, record why. Wrong agent reasoning is also signal.

### Review boundaries

- This lesson reviews implemented code. It does not create the plan, execute new phases, or teach CI review.
- Testing strategy and quality gates are introduced in Module 3.
- Do not use `/10x-contract` as a triage outcome in this lesson.

### Paths used by this lesson

- `context/changes/<change-id>/plan.md` - expected implementation contract
- `context/changes/<change-id>/reviews/` - review output
- `context/foundation/lessons.md` - recurring lessons

Skills must not write to `context/archive/`. Archived changes are immutable; if a resolved target path starts with `context/archive/`, abort with: "This change is archived. Open a new change with `/10x-new` instead."

<!-- END @przeprogramowani/10x-cli -->
