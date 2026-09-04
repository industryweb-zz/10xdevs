---
project: "KnowledgeTest"
planned_at: 2026-07-14
platform: railway
db_engine: postgresql
llm_provider: anthropic
status: deployed
---

# Railway Deploy Plan

Executable plan for the first Railway deployment of KnowledgeTest, derived from `context/foundation/infrastructure.md`'s researched recommendation. This supersedes the removed self-hosted DirectAdmin/Apache pipeline (`deploy/release.sh`, the `FollowSymLinks` `.htaccess` addition — both already reverted).

Decisions made with the user during planning (not previously recorded anywhere):
- **DB engine: PostgreSQL** (infrastructure.md left this open as "co-located Postgres/MySQL").
- **LLM provider: Anthropic (Claude)**, via `ANTHROPIC_API_KEY` — no provider had been named yet for FR-004.

Repo-side artifacts already created as part of this plan: `nixpacks.toml` (pins PHP 8.3 / Node 20), `.github/workflows/deploy.yml` (rewritten from the stale SSH-to-VPS workflow to `railway up`), `.env.example` (added `ANTHROPIC_API_KEY` placeholder, noted `pgsql` as the production `DB_CONNECTION`).

## Phase 1 — Railway account & project provisioning (manual, human gate)

- [X] Create/confirm a Railway account.
- [X] Install the Railway CLI (`npm i -g @railway/cli`) and run `railway login`.
- [X] From the repo root, run `railway init` (project name: `knowledge-test`).
- [X] Run `railway add` to provision a co-located **PostgreSQL** service in the same project.
- [~] Enable the native **Backups** feature on the Postgres service before any production migration runs. — SKIPPED: Backups is Pro-plan-only, not available on the current Railway plan. Risk carried forward: no automated snapshot before `artisan migrate --force` (see Phase 6).
- [~] Set a usage alert / spending cap in the Railway dashboard billing settings. — SKIPPED: currently on the $5 trial credit, which is a hard ceiling by itself; revisit once on a paid usage-based plan.

## Phase 2 — Repo-side Railway config

- [x] `nixpacks.toml` added at repo root, pins PHP 8.3 + Node 20.
- [ ] On first build, check `railway logs` and confirm the auto-generated start command (Nixpacks' PHP provider should serve `public/` via nginx+php-fpm automatically) — do not assume, verify.
- [x] Confirmed `public/.htaccess`'s `FollowSymLinks` addition and `public/.htaccess.scaffold` are harmless Apache-only artifacts, irrelevant to Railway/Nixpacks — no action taken.

## Phase 3 — Environment variables & secrets (CLI only, no dashboard edits)

- [x] `railway variables set APP_KEY=$(php artisan key:generate --show)` — generate fresh, do not reuse the key in `.env.scaffold`.
- [x] `railway variables set APP_ENV=production APP_DEBUG=false`
- [ ] Set `APP_URL` after the first deploy assigns a domain.
- [x] Confirm Postgres connection vars are auto-injected (`railway variables`) once the DB service is linked; set `DB_CONNECTION=pgsql` explicitly.
- [x] Leave `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` as `database` (no Redis in scope; `tech-stack.md` has `has_background_jobs: false`) — confirmed already `database` in `.env.example`, no override needed.
- [x] `railway variables set ANTHROPIC_API_KEY=...` — key obtained from the Anthropic Console (external to this repo).

## Phase 4 — CI/CD

- [x] `.github/workflows/deploy.yml` rewritten: drops `appleboy/ssh-action`, installs the Railway CLI, runs `railway up --service knowledge-test-app --ci` gated on `secrets.RAILWAY_TOKEN`. Trigger kept as `push: branches: [master]` (matches the repo's actual default branch). NOTE: initially pointed at `--service knowledge-test` (the project name, not the app service name), which broke CI with `Service not found` once `knowledge-test-app` was the actual service alongside Postgres — corrected.
- [x] Generate a **project-scoped** Railway API token (not account-wide) and add it as the `RAILWAY_TOKEN` secret in GitHub repo settings.
- [ ] For the first several deploys, run `artisan migrate --force` manually (`railway run php artisan migrate --force`) rather than wiring it into the automated workflow — confirm the Phase 1 backup is active first, every time.

## Phase 5 — First deploy & verification

- [x] `railway up` (or push to `master`) for the first deploy; watch `railway logs` for the Nixpacks build, confirm PHP/Node versions match `nixpacks.toml`. NOTE: the first `railway up --ci` run had no `--service` flag and deployed onto the linked **Postgres** service by mistake, overwriting its image and crashing it. Recovered via dashboard redeploy of `ghcr.io/railwayapp-templates/postgres-ssl:18`; volume (`postgres-volume`) was untouched, no data loss. A separate `knowledge-test-app` service was then created for the app; all env vars (which had also landed on Postgres by mistake) were reset on the correct service via reference variables (`${{Postgres.PGHOST}}` etc.) and the stray copies removed from Postgres. Lesson: always pass `--service <name>` explicitly with `railway up`/`railway variables set` once more than one service exists in a project.
- [x] `railway ssh --service knowledge-test-app -- php artisan migrate --force` against the Postgres DB (ran via SSH into the deployed container, not `railway run`, since `railway run` executes locally and this machine lacks `pdo_pgsql`). No native Backups were enabled (Pro-plan-only, skipped in Phase 1) but the volume survived the incident regardless.
- [x] Hit the Railway-assigned URL, confirm the app responds — `HTTP 200` at `https://knowledge-test-app-production.up.railway.app`.
- [x] Set `APP_URL` to match the actual assigned domain — done via `railway variables set APP_URL=https://knowledge-test-app-production.up.railway.app --service knowledge-test-app`.

## Phase 6 — Operational guardrails

- [ ] Rollback (`railway redeploy` to a prior deployment ID) does **not** reverse a DB migration — take a Postgres snapshot before every `artisan migrate --force` in production, not just the first one. Since native Backups is unavailable on this plan, snapshot manually: `railway connect postgres` → `pg_dump` to a local file, or `railway run pg_dump $DATABASE_URL > backup.sql`, before each migration.
- [ ] Review the Railway billing dashboard monthly during the MVP period (usage-based pricing has no built-in ceiling).

## Known gaps carried forward (not part of this deploy plan)

- Laravel Breeze is not yet installed despite `tech-stack.md` naming it as the auth solution.
- No AI SDK / HTTP client code exists yet for the Anthropic call (FR-004) — `ANTHROPIC_API_KEY` is provisioned as infrastructure, the integration code is separate work.
