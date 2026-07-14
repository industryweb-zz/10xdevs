---
project: "KnowledgeTest"
researched_at: 2026-07-14
recommended_platform: "Railway"
runner_up: "Render"
context_type: mvp
tech_stack:
  language: php
  framework: laravel
  runtime: php-8.3-fpm
---

## Recommendation

**Deploy on Railway.**

Railway is the only platform in the researched pool that clears all five agent-friendly criteria at Pass/Partial with no outright fails: an official CLI, Nixpacks auto-detection for PHP/Laravel with no Dockerfile to hand-write, co-located Postgres/MySQL with a native backups feature, and an official (if still-maturing) MCP server. Cloudflare Workers/Pages, Vercel, and Netlify were dropped outright — none run PHP-FPM/Laravel as a supportable production runtime. Against Fly.io and Render, Railway wins on the interview's co-location preference (mature DB templates vs. Fly's newer, less-proven Managed Postgres) while staying acceptably cheap at this project's small scale and low QPS.

## Platform Comparison

| Platform | CLI-first | Managed/Serverless | Agent-readable docs | Stable deploy API | MCP / Integration | Total |
|---|---|---|---|---|---|---|
| Railway | Pass | Pass | Pass | Pass | Partial | 4.5 / 5 |
| Render | Pass | Partial | Partial | Pass | Pass | 4 / 5 |
| Fly.io | Pass | Partial | Pass | Pass | Fail | 3.5 / 5 |
| Cloudflare Workers/Pages | — | — | — | — | — | dropped: no PHP-FPM runtime |
| Vercel | — | — | — | — | — | dropped: no PHP-FPM runtime |
| Netlify | — | — | — | — | — | dropped: no PHP-FPM runtime |

Notes per platform:

- **Railway** — Nixpacks auto-detects PHP/Laravel and builds without a hand-written Dockerfile; Postgres/MySQL run as co-located project services (`${{ Postgres.DATABASE_URL }}`); an official CLI and a native "Backups" feature exist, though backups are opt-in, not automatic. The MCP server is official and GA but explicitly labeled "a work in progress" as of the 2026 docs, and was recently folded into the CLI rather than shipped standalone — the one Partial score. No persistent free tier remains in 2026 (one-time $5 trial credit, then Hobby $5/mo + usage), but paid services don't spin down, so the PRD's 10-second AI-generation latency budget is safe from cold starts.
- **Render** — PHP has no native buildpack; deploys go through Docker or a "native environment" with manually supplied build/start commands (Partial on managed/serverless and docs). The free tier's ~1-minute spin-down after 15 minutes idle would blow the 10-second latency budget outright, so the paid Starter tier ($7/mo) is a practical requirement, not optional. Its MCP server, however, is now GA (v0.3.0, since 2026-01-14) and covers service/DB management, logs, and metrics — ahead of Railway's still-maturing one.
- **Fly.io** — No native PHP buildpack; `fly launch` needs the `dockerfile-laravel` tooling to generate an nginx+php-fpm container, a self-maintained artifact going forward. Machines auto-stop/auto-start with a 1–3s cold start, survivable within the 10s budget but worth load-testing. The bigger flag: unmanaged Fly Postgres is deprecated and the Supabase-managed offering was sunset in April 2025 — the replacement Managed Postgres (MPG) product is newer and less proven. No official MCP server was found.
- **Cloudflare Workers/Pages** — Hard filter: native runtimes are JS/TS (plus Python/Rust via WASM); PHP only exists via experimental community transpilation, no real PHP-FPM process or Eloquent/MySQL socket support.
- **Vercel** — Hard filter: `vercel-php` is a third-party serverless-function wrapper, explicitly unsuited for full Laravel with queues, scheduled tasks, or persistent connections.
- **Netlify** — Hard filter: JAMstack/static-first with JS-oriented functions; no server-side PHP execution path for a standard Laravel app.

### Shortlisted Platforms

#### 1. Railway (Recommended)

Highest score among the survivors: Nixpacks removes the Dockerfile-authoring burden Fly.io and Render both impose on PHP, and its co-located Postgres/MySQL templates plus native backup feature best match the stated co-location preference. The usage-based pricing above the $5/mo Hobby floor is the real cost tradeoff for a cost-minimizing MVP.

#### 2. Render

Best MCP story (GA, structured tools for logs/metrics/DB) and a stronger managed-Postgres/backup posture than Railway on paid tiers, but PHP requires a Docker or native-env build script by hand, and the free tier is disqualified outright by the 10-second latency budget — so it costs a mandatory $7+/mo floor plus paid Postgres to be viable at all.

#### 3. Fly.io

Most cost-efficient at genuinely low, bursty traffic thanks to scale-to-zero machines, but two real gaps for this project: no native PHP support (Dockerfile required) and a managed-Postgres offering that only recently replaced a deprecated one — a data layer with less of a track record than Railway's.

## Anti-Bias Cross-Check: Railway

### Devil's Advocate — Weaknesses

1. No persistent free tier as of 2026 — new accounts get a one-time $5 credit, then Hobby ($5/mo + usage) is the floor; "minimize cost" still means a real recurring bill, unlike Fly.io's scale-to-zero option.
2. Nixpacks' PHP/Laravel auto-detection is less battle-tested than Fly's dedicated `dockerfile-laravel` tooling or Render's documented native-env path — a PHP version or missing-extension mismatch is a plausible first-deploy failure mode unless explicitly pinned.
3. Database backups are opt-in, not automatic — the native Backups feature and community S3/GCS templates must be explicitly configured; easy to ship without under a 3-week after-hours deadline.
4. The official MCP server is explicitly labeled "a work in progress" and was recently folded into the Railway CLI rather than kept as a stable standalone package — schema or behavior could shift under an agent relying on it.
5. Usage-based pricing on top of the Hobby base isn't fully predictable up front — a traffic spike from the AI flashcard-generation feature (FR-004) could push the bill past the cost-minimization comfort zone with no built-in spending cap.

### Pre-Mortem — How This Could Fail

Six months in, the KnowledgeTest bill has crept from $5/mo to $30+/mo without anyone noticing, because Railway's usage-based pricing scaled with a spike in flashcard-generation traffic during exam season — the "minimize cost" decision assumed flat Hobby-tier pricing, not the variable compute/network charges layered on top. Separately, nobody ever configured the optional database backup feature — it wasn't part of the default Nixpacks deploy flow, and the solo developer assumed "PaaS means backups are automatic," an assumption that didn't hold here. When a bad migration corrupted flashcard data, there was no snapshot to restore from. The final blow: the official MCP server had shifted from a standalone package to a CLI-bundled feature mid-project, and an agent-driven deploy script written against the old interface silently stopped working, surfacing as failed automated deploys that took hours to diagnose because the "work in progress" label had been easy to miss when the integration was first wired up.

### Unknown Unknowns

- Railway's usage-based billing means "Hobby $5/mo" is a floor, not a ceiling — actual cost depends on compute-seconds, network egress, and DB storage, none of which are obvious from the pricing page.
- Nixpacks' PHP build path may install a different PHP minor version or extension set than what Laravel 13 / `composer.json` expects unless pinned explicitly (e.g., via `nixpacks.toml`) — a common silent-mismatch source.
- Environment variables set in the dashboard vs. via `railway variables set` in the CLI can drift, since no single source of truth is enforced.
- Billing is aggregated per project across services, so a runaway or leaked background process silently inflates the bill without an obvious per-service breakdown unless the usage dashboard is checked directly.
- Rollback via CLI/dashboard reverts app code but does **not** automatically reverse a database migration that ran in the failed release — same caveat as most PaaS platforms.

## Operational Story

- **Preview deploys**: PR-based preview environments are available per Railway environment/service branching; each PR can get its own ephemeral environment with its own URL, torn down on merge/close — no extra protection layer is configured by default, so treat preview URLs as semi-public.
- **Secrets**: Environment variables live in Railway's project/service variable store (`railway variables set` via CLI, or the dashboard); GitHub Actions holds only the `RAILWAY_TOKEN` needed to trigger deploys, not the app's own secrets. Rotate the Railway API token from the dashboard; rotate app secrets via `railway variables set` before the next deploy.
- **Rollback**: `railway redeploy` against a previous deployment ID (or the dashboard's one-click "Redeploy") reverts app code within roughly a minute. Caveat: a rollback does **not** automatically reverse a database migration from the failed release — a pre-deploy backup or a verified down-migration is needed before trusting rollback to fully undo a bad release.
- **Approval**: A human must approve rotating the Railway API token, changing the Postgres/MySQL plan (data-loss risk on downgrade), or running a destructive migration. Routine deploys (`git push` to main → GitHub Actions → `railway up`) can run unattended per the auto-deploy-on-merge flow already recorded in `tech-stack.md`.
- **Logs**: `railway logs` streams build and runtime logs from the CLI; the dashboard's log viewer covers the same data with filtering. The official MCP server exposes structured log/metric queries too, but given its "work in progress" status, treat the CLI as the primary, reliable path and the MCP server as a secondary convenience until it stabilizes.

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Usage-based billing creeps past the cost-minimization budget during a traffic spike | Devil's advocate / Pre-mortem | M | M | Set a Railway usage alert/budget cap in the dashboard; review the usage breakdown monthly during the MVP period. |
| Database backups are never configured because they're opt-in, not default | Devil's advocate / Pre-mortem | H | H | Enable Railway's native Backups feature (or a scheduled `mysqldump`/`pg_dump` template) as part of initial provisioning, before the first production migration runs. |
| Nixpacks auto-detects a PHP version/extension set that doesn't match `composer.json` | Unknown unknowns | M | M | Pin the PHP version explicitly via `nixpacks.toml` (or an equivalent config Railway's Nixpacks build respects) and verify the build log on first deploy. |
| Official MCP server is unstable ("work in progress") and could shift interface mid-project | Devil's advocate / Research finding | M | L | Treat the Railway CLI as the primary automation surface; use the MCP server only for read-only convenience queries until it reaches a stated-stable status. |
| Rollback does not reverse a database migration from a failed release | Unknown unknowns | M | H | Take a Postgres/MySQL snapshot immediately before every `artisan migrate --force` in the deploy pipeline; document the manual down-migration path for schema changes that aren't safely reversible. |
| Environment variable drift between dashboard edits and CLI `variables set` | Unknown unknowns | L | M | Standardize on one method (CLI, scripted) for all secret/env changes going forward; treat the dashboard as read-only for variables. |

## Getting Started

1. Install the Railway CLI (`npm i -g @railway/cli` or the platform-native installer) and authenticate with `railway login`.
2. From the Laravel 13 project root, run `railway init` to create a new Railway project, then `railway add` to provision a co-located MySQL (or Postgres, matching whatever `composer.json`/`config/database.php` targets) service.
3. Add a `nixpacks.toml` (or equivalent) pinning the PHP version to `8.3` and any required extensions, so Nixpacks' auto-detection doesn't drift from the composer.json constraint.
4. Set required `.env` values (`APP_KEY`, `DB_*`, the LLM API key for FR-004) via `railway variables set`, not the dashboard, to keep one source of truth.
5. Wire the GitHub Actions workflow for `ci_default_flow: auto-deploy-on-merge` from `tech-stack.md`: on merge to `main`, run `railway up` (or the Railway GitHub integration's auto-deploy) using a scoped `RAILWAY_TOKEN` stored in GitHub Secrets; enable the native Backups feature on the database service before the first `artisan migrate --force` runs in production.

## Out of Scope

The following were not evaluated in this research:
- Docker image configuration
- CI/CD pipeline setup (beyond the operational story above)
- Production-scale architecture (multi-region, HA, DR)
