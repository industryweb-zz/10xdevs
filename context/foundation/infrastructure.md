---
project: "KnowledgeTest"
researched_at: 2026-07-09
recommended_platform: "Self-hosted VPS (raw, script-driven deploy)"
runner_up: "Laravel Forge (same VPS, managed layer)"
context_type: mvp
tech_stack:
  language: php
  framework: laravel
  runtime: php-8.3-fpm
---

## Recommendation

**Deploy to the VPS you already own, using a scripted zero-downtime release process (Nginx + PHP-FPM + GitHub Actions over SSH) rather than a managed PaaS.**

You already own the server, minimizing cost is the top priority, you have hands-on VPS experience, single-region is fine, and you want the database co-located — every interview answer points at the box you already have rather than a new vendor. Laravel's official deployment docs cover this path directly, and it costs $0 marginal beyond what you already pay for the VPS.

## Platform Comparison

| Platform | CLI-first | Managed/Serverless | Agent-readable docs | Stable deploy API | MCP / Integration | Total |
|---|---|---|---|---|---|---|
| Self-hosted VPS | Pass | Fail | Pass | Partial | Fail | 2.5 / 5 |
| Laravel Forge | Partial | Pass | Pass | Pass | Partial | 3.5 / 5 |
| Railway | Pass | Pass | Pass | Pass | Pass | 5 / 5 |
| Render | Partial | Pass | Partial | Pass | Partial | 3 / 5 |
| Fly.io | Pass | Pass | Pass | Pass | Partial | 4.5 / 5 |

Notes per platform:

- **Self-hosted VPS** — Fails "managed/serverless" by definition (it's raw infra: you own OS patching, TLS renewal, DB backups). Deployment is fully scriptable via GitHub Actions + SSH, so CLI-first passes, but there's no vendor deployment API or MCP server — the agent operates through plain SSH/CLI, which is a real but acceptable gap at this scale. Scored lowest on the agent-friendly criteria, highest on cost and familiarity, which is why it still wins: the criteria are heuristics, not gates, and cost/familiarity were the interview's dominant signals.
- **Laravel Forge** — Same VPS, adds a managed layer for zero-downtime deploys, SSL, and queue/scheduler daemons via CLI/API, plus a published `llms.txt`. Initial server provisioning still leans on the dashboard (needs live verification), and it's ~$12+/mo on top of the VPS you already pay for. Best runner-up: same infra, less ops burden, at a real recurring cost.
- **Railway** — Scores highest on the agent-friendliness criteria alone (official MCP server, GA CLI, co-located DB templates, PHP-native builder). Loses on cost (new $5–10/mo floor on infrastructure you didn't need to buy) and doesn't use the VPS you already have — the interview's cost and familiarity answers outweigh its higher agent-friendliness score.
- **Render** — PHP requires a self-maintained Dockerfile (no native runtime), free tier's 15-minute cold-start spin-down breaks the 10-second flashcard-generation latency budget in the PRD, and free Postgres expires after 30 days. Realistic viable cost (~$13-16/mo) is worse than Forge for less capability.
- **Fly.io** — Strong CLI/agent story, but default "Fly Postgres" is explicitly unmanaged (no automatic backups/failover) — a real MVP data-loss risk unless the newer Managed Postgres tier is used and verified live. Also requires Dockerfile maintenance. Costs more (~$13-20/mo) than the VPS you already own.

### Shortlisted Platforms

#### 1. Self-hosted VPS (Recommended)

Zero marginal cost, matches your stated hands-on VPS experience, keeps the database co-located on the same box, and the deploy flow (Nginx + PHP-FPM + Certbot + a symlink-swap release script triggered from GitHub Actions over SSH) is fully documented in Laravel's own deployment docs — no new vendor account, no Dockerfile to maintain for an app with no current containerization need.

#### 2. Laravel Forge

Same underlying VPS — Forge is an orchestration layer, not a host, so this isn't a rival infrastructure choice, it's "VPS plus a managed control plane." Worth adopting if the ops time spent scripting zero-downtime deploys and TLS renewal exceeds the ~$12+/mo subscription cost. Revisit this if background queue jobs get added later (Forge's daemon/scheduler management becomes more valuable once there's an actual queue to manage).

#### 3. Railway

The strongest pick if you ever want to stop managing a server entirely. Official MCP server and GA CLI make it the most "agent-native" option researched, but it means abandoning the already-paid-for VPS and taking on a new $5–10/mo floor — not justified by this project's constraints today.

## Anti-Bias Cross-Check: Self-hosted VPS

### Devil's Advocate — Weaknesses

1. **Zero-downtime deploy is not free** — a naive `git pull && composer install` on the live directory causes real request failures (500s) mid-`composer install`; it requires deliberate tooling (Deployer or a hand-rolled symlink-swap script), which is scope the "just deploy already" instinct will skip under a 3-week deadline.
2. **You are the entire ops team** — OS security patching, PHP/Nginx/MySQL version upgrades, and database backups have no managed fallback. A missed `apt upgrade` or an un-backed-up database is a silent risk until it's a production incident.
3. **TLS renewal is a cron job, not a platform feature** — Certbot's auto-renewal must be verified working (a systemd timer or cron entry), or the site quietly goes to an expired-cert error page with no alert.
4. **Single point of failure, no autoscaling** — one VPS means one outage takes the whole app down; acceptable at "small scale, low QPS" per the PRD, but this is a hard ceiling, not a "fix it later" item if usage grows past the plan's assumptions.
5. **No agent-native tooling at this layer** — every operational action (redeploy, check logs, restart a service) goes through raw SSH/CLI. There's no MCP server, no structured API, so an AI agent operating this infrastructure has a higher chance of an imprecise or destructive command (e.g., a wrong `systemctl` target) than on a platform with typed operations.

### Pre-Mortem — How This Could Fail

Six months in, KnowledgeTest goes down for four hours during a busy exam week and nobody notices until users complain on social media. The postmortem: the naive deploy script (`git pull` + `composer install` directly in the live directory, no symlink swap) had been causing brief 500s on every release, which the solo developer had gotten used to and assumed were harmless — until one deploy landed mid-migration and left the schema half-updated with no rollback path, because there was no Deployer-style atomic release process. Separately, the Let's Encrypt cert had silently failed to renew three weeks earlier (the renewal cron job was never verified after initial setup), so half of "the outage" was actually users hitting a certificate-expired warning and bouncing, invisible in server logs. The MySQL database, running on the same box with no automated offsite backup, had no recent snapshot to restore from when the migration corrupted user flashcard data — the developer had assumed "the VPS provider backs it up" without ever confirming that assumption.

### Unknown Unknowns

- **PHP-FPM "restart" vs "reload" matters** — a full restart drops in-flight requests; a graceful reload does not. This distinction isn't obvious from Laravel's deployment docs alone and is exactly the kind of command an agent might get wrong under time pressure.
- **`composer install --no-dev` inside the live directory is a race condition, not just slow** — running it in-place (rather than in a separate `releases/<timestamp>/` directory swapped in atomically) is the single most common cause of "why did my site 500 for 10 seconds during deploy."
- **Unattended-upgrades can silently restart services** — if OS auto-updates are enabled for convenience, an unattended MySQL or Nginx restart can happen at an arbitrary time, including during a flashcard-generation request.
- **A single VPS conflates app and database blast radius** — a resource spike in one (e.g., a runaway LLM-call retry loop maxing out CPU) can starve the other (MySQL) since they share the same box, unlike a managed-DB platform where they're isolated.
- **Certbot's renewal failure is silent by default** — there's no built-in alerting; failures only surface when a user hits an expired-cert warning, unless you explicitly wire up a renewal-failure notification (e.g., a cron script that emails/pings on `certbot renew` non-zero exit).

## Operational Story

- **Preview deploys**: No PaaS-native preview environments on a raw VPS. If a staging environment is wanted, it's a second Nginx server block + separate `.env` on the same or a second (cheap) VPS, deployed via the same GitHub Actions workflow against a different branch/host — not automatic, has to be set up explicitly.
- **Secrets**: `.env` lives on the server only, never in git. GitHub Actions holds the deploy secrets (SSH key, host, `.env` contents or a path to fetch them) in GitHub Secrets; the agent/developer pushes `.env` changes via SCP/SSH during setup, not via CI on every deploy.
- **Rollback**: With an atomic symlink-release layout (`releases/<timestamp>/` + `current` symlink), rollback is `ln -sfn releases/<previous-timestamp> current && systemctl reload php-fpm` — seconds, no rebuild. Caveat: a rollback does **not** automatically reverse a database migration that ran in the failed release; migrations that add non-nullable columns or drop data need a manually verified down-migration or a pre-deploy backup checkpoint.
- **Approval**: A human must approve provisioning a new server, rotating the SSH deploy key, or running a destructive migration (anything with `Schema::drop` or a data-loss migration). Routine deploys (`git push` to main → GitHub Actions → SSH release script) can run unattended per the tech-stack's stated `auto-deploy-on-merge` CI flow.
- **Logs**: `journalctl -u php8.3-fpm` / `-u nginx` for service logs, Laravel's own `storage/logs/laravel.log` (or `php artisan pail` locally / over SSH) for app logs — all read via SSH, no dashboard. No MCP server exists at this layer; an agent reads logs by running these commands over an already-authorized SSH session, not via a structured tool call.

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Naive in-place deploy causes brief 500s or a half-applied migration | Devil's advocate / Pre-mortem | H | M | Adopt an atomic symlink-release script (or Deployer) from day one: build in `releases/<timestamp>/`, run migrations, then swap `current` symlink; never deploy directly into the live directory. |
| TLS certificate silently fails to renew | Devil's advocate / Unknown unknowns | M | M | Automate Certbot renewal via systemd timer, and add a renewal-failure notification (script exit-code check → email/webhook) so a failure doesn't go unnoticed for weeks. |
| No automated database backup before a migration or in general | Pre-mortem / Research finding | M | H | Add a daily `mysqldump`/`pg_dump` cron job piping to off-box storage (e.g., object storage or a second cheap VPS), and take an explicit pre-migration snapshot as part of the deploy script. |
| OS/service auto-updates restart PHP-FPM/MySQL at an arbitrary time | Unknown unknowns | L | M | Disable fully-unattended service restarts for PHP-FPM/MySQL/Nginx; apply security patches on a scheduled, developer-triggered maintenance window instead. |
| Single VPS is a single point of failure; app and DB share resource blast radius | Devil's advocate | L | H | Acceptable at current "small scale, low QPS" per the PRD — explicitly revisit (separate DB host, or move to Forge/managed DB) if usage or the FR-004 AI generation load grows past the MVP assumption. |
| No MCP/structured API at this layer increases the chance of an imprecise agent-issued command | Devil's advocate | L | M | Prefer `systemctl reload` over `restart` for PHP-FPM in deploy scripts; keep the deploy/rollback logic in a single reviewed script rather than ad-hoc SSH commands typed per incident. |

## Getting Started

1. Confirm PHP 8.3-FPM, Nginx (or Caddy), and MySQL/Postgres are installed on the existing VPS, matching `composer.json`'s `"php": "^8.3"` constraint.
2. Set up an atomic release layout on the server (`/var/www/knowledgetest/releases/<timestamp>/`, `shared/.env`, `shared/storage`, `current` symlink) — either hand-rolled per Laravel's deployment docs or via `lorisleiva/laravel-deployer` (`composer require --dev lorisleiva/laravel-deployer` on your local machine, not the server).
3. Point Nginx's `fastcgi_pass` at the `php8.3-fpm.sock` socket and set the document root to `current/public`, per `laravel.com/docs/13.x/deployment`.
4. Automate TLS with Certbot (`certbot --nginx`) and verify the systemd timer/cron entry for renewal is actually enabled (`systemctl list-timers | grep certbot`).
5. Wire the GitHub Actions workflow already implied by `ci_default_flow: auto-deploy-on-merge` in `tech-stack.md`: on merge to `main`, SSH into the VPS (via `appleboy/ssh-action` or similar) and run the release script (build in new `releases/<timestamp>/`, `composer install --no-dev --optimize-autoloader`, `artisan migrate --force`, cache config/route/view, swap `current` symlink, `systemctl reload php8.3-fpm`).

## Out of Scope

The following were not evaluated in this research:
- Docker image configuration
- CI/CD pipeline setup (beyond the operational story above)
- Production-scale architecture (multi-region, HA, DR)
