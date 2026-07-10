---
project: "KnowledgeTest"
planned_at: 2026-07-09
platform: "Self-hosted VPS (raw, script-driven deploy)"
target_host: "91.195.134.22"
target_user: "iw10xdevs"
domain: "10xdevs.industryweb.pl"
database: mysql
repo: "industryweb-zz/10xdevs"
---

# First deploy: KnowledgeTest → self-hosted VPS

## Context

`context/foundation/infrastructure.md` picked a self-hosted VPS with a scripted, zero-downtime Nginx + PHP-FPM + GitHub Actions-over-SSH deploy (no PaaS, no Docker). The repo today is a genuinely unmodified Laravel 13 skeleton — confirmed via exploration: no `.github/workflows/`, no `Dockerfile`/`Procfile`/Deployer config, `.env.example` defaults to `sqlite`/`database`-driver everything, only the 3 stock migrations exist. Nothing to deploy *around*, but also nothing to reuse — this plan builds the full path from "app boots locally" to "push to `master` auto-deploys to `https://10xdevs.industryweb.pl`" from scratch.

**Deviation from infrastructure.md, discovered during execution (2026-07-10):** the VPS runs **Apache 2.4**, not Nginx (hand-rolled config, not a hosting panel). Decision: adapt the web-server layer to Apache instead of installing Nginx alongside it. Everywhere below, "Nginx" → "Apache vhost + `mod_rewrite`/`mod_ssl`"; PHP-FPM wiring becomes `mod_proxy_fcgi` (or the existing mod_php setup, already confirmed working) instead of Nginx's `fastcgi_pass`. The atomic symlink-release pattern, TLS-via-Certbot, and GitHub Actions-over-SSH deploy flow are unaffected by this swap.

Confirmed with the user:
- VPS: `91.195.134.22`, SSH user `iw10xdevs`, full root/sudo access (not shared hosting — `public_html` is just the chosen doc-root folder name).
- Production DB: MySQL.
- Domain: `10xdevs.industryweb.pl`, DNS can be pointed at the VPS now.
- GitHub repo: `industryweb-zz/10xdevs` (existing `origin` remote).

Goal of this deploy: get the vanilla skeleton live and auto-deploying on merge to `master`, per infrastructure.md's atomic symlink-release pattern — not to build the flashcard feature itself.

## Server-side layout

Atomic release layout under the `iw10xdevs` home directory, with `public_html` itself becoming the "current" symlink (matches the path the user already gave, and is what Nginx's `root` will point at):

```
/home/iw10xdevs/
  releases/<timestamp>/        # full checkout of the repo per release
  shared/.env                  # production .env, never in git, persists across releases
  shared/storage/              # Laravel storage/ persists across releases (symlinked in)
  public_html -> releases/<timestamp>/public   # atomic swap target, Nginx root points here
```

Rollback = repoint the `public_html` symlink to the previous `releases/<ts>` and reload PHP-FPM — no rebuild.

## Implementation steps

1. ✅ **Server bootstrap (manual, one-time, via SSH)** — DONE. Confirmed present: PHP 8.4.20 (satisfies `composer.json`'s `^8.3`) with required extensions already configured correctly, Apache 2.4, MySQL 8.0.44, Composer 2.9.7, Certbot 4.2.0. `releases/`, `shared/`, `shared/storage/{app,framework,logs}` directory tree created under `/home/iw10xdevs/`.

2. ✅ **MySQL setup** — DONE. Database `knowledgetest` and scoped user `knowledgetest`@`localhost` created; credentials verified with a working `SELECT 1;`. Password recorded only in `shared/.env` on the server (step 5), never in git.

3. ✅ **Apache vhost** — DONE. `10xdevs.industryweb.pl` already configured. **Correction (2026-07-10):** this is actually a **DirectAdmin-managed** vhost (`AssignUserID`, `/usr/local/etc/apache24/...`, `/home/logs/apache/...` — FreeBSD/DirectAdmin CustomBuild conventions), not the hand-rolled config we first assumed — editing the auto-generated vhost file directly risks being overwritten on panel regeneration. `DocumentRoot` is a fixed literal path `/home/iw10xdevs/public_html/public/`; Apache resolves this through the `public_html` symlink transparently at request time, so the atomic symlink-swap layout works without changing the vhost, **provided `FollowSymLinks` is enabled** — handled via `public/.htaccess` (version-controlled, redeployed every release) rather than editing the panel-managed vhost, since `AllowOverride` here already permits `Options=All`.

4. ✅ **TLS** — DONE. Certificate for `10xdevs.industryweb.pl` already issued and confirmed via `certbot certificates`. Still verify the renewal timer is enabled (`systemctl list-timers | grep certbot`) per the risk register in infrastructure.md before considering this fully closed.

5. ✅ **Production `.env`** — DONE. `shared/.env` written with `APP_ENV=production`, `APP_URL=https://10xdevs.industryweb.pl`, generated `APP_KEY`, `DB_CONNECTION=mysql` + credentials from step 2, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`. Confirmed `chmod 600`.

6. ✅ **Deploy script** — DONE. `deploy/release.sh` written implementing the symlink-swap:
   - `git clone --depth 1 --branch master` into a new `releases/<timestamp>/`
   - symlink in `shared/.env` and `shared/storage`
   - `composer install --no-dev --optimize-autoloader`
   - `php artisan migrate --force`
   - `php artisan config:cache route:cache view:cache`
   - atomically repoint `public_html` → new release's `public/`
   - prune old releases (keep last 5)

   **Deviation:** no PHP-FPM/Apache reload step — user explicitly opted out of any service-restart action in the deploy script (2026-07-10). If stale opcode-cache issues surface later (e.g. via OPcache with `validate_timestamps=0`), revisit this.

7. ✅ **GitHub Actions workflow** — DONE. `.github/workflows/deploy.yml` written: on push to `master`, SSHes in via `appleboy/ssh-action` using repo secrets (`VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_DEPLOY_PATH`) and runs `$VPS_DEPLOY_PATH/shared/release.sh`.

   **Note:** `release.sh` does its own `git clone` into a fresh `releases/<timestamp>/` each run, so it must live at a *stable* path that survives release pruning — copied once to `/home/iw10xdevs/shared/release.sh`, not inside a release directory. Still pending before step 8:
   - Copy `deploy/release.sh` to `/home/iw10xdevs/shared/release.sh` on the server (`chmod +x`).
   - Add GitHub repo secrets: `VPS_HOST=91.195.134.22`, `VPS_USER=iw10xdevs`, `VPS_SSH_KEY` (a deploy key added to the server's `~/.ssh/authorized_keys`), `VPS_DEPLOY_PATH=/home/iw10xdevs`.
   - The server needs its own deploy key with **read access to `industryweb-zz/10xdevs`** for `git clone` inside `release.sh` to work (separate from the GitHub Actions SSH-in key).

8. **First manual run** — trigger the first deploy (either by pushing to `master` or running the script by hand over SSH) to prove the pipeline end-to-end, then verify `https://10xdevs.industryweb.pl` serves the Laravel welcome page over valid TLS.

   **Pre-flight discovered during execution (2026-07-10):** `DocumentRoot` is `/home/iw10xdevs/public_html/public/` — Apache follows the `public_html` symlink transparently, so `release.sh`'s existing `ln -sfn "$RELEASE_DIR/public" "$CURRENT_LINK"` needs no code change. BUT `/home/iw10xdevs/public_html` is currently a **real directory with files** (not yet a symlink), so the very first `ln -sfn` would land the new symlink *inside* it rather than replacing it. One-time manual fix required before the first run:
   ```bash
   mv /home/iw10xdevs/public_html /home/iw10xdevs/public_html.bak-preexisting
   ```
   After that, `release.sh` creates `public_html` as a symlink cleanly on its first run. `FollowSymLinks` is enabled via `Options +FollowSymLinks` added to `public/.htaccess` in the repo (not the DirectAdmin-managed vhost, which permits this via `AllowOverride ... Options=All`) — committed alongside the deploy pipeline.

## Out of scope (matches infrastructure.md)

- Docker/containerization
- Multi-region/HA/DR
- The flashcard-generation feature itself — this is infra plumbing only

## Verification

- `curl -I https://10xdevs.industryweb.pl` returns `200` with a valid cert (no `-k` needed).
- `ssh iw10xdevs@91.195.134.22 'systemctl list-timers | grep certbot'` shows an active renewal timer.
- Push a trivial commit to `master`, confirm GitHub Actions run succeeds and the symlink target under `public_html` changes to a new `releases/<timestamp>`.
- `php artisan migrate:status` on the server shows the 3 stock migrations applied against MySQL (not sqlite).

## Status

In progress. Steps 1–7 confirmed done (2026-07-10, with the Apache-not-Nginx deviation and no-PHP-reload deviation noted above). Step 8 pending — see the outstanding server-side/GitHub-secrets setup listed under step 7 before triggering the first run.
