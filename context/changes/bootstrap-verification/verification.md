---
bootstrapped_at: 2026-07-14T00:00:00Z
starter_id: laravel
starter_name: Laravel
project_name: knowledge-test
language_family: php
package_manager: composer
cwd_strategy: subdir-then-move
bootstrapper_confidence: verified
phase_3_status: ok
audit_command: "null"
---

## Hand-off

```yaml
starter_id: laravel
package_manager: composer
project_name: knowledge-test
hints:
  language_family: php
  team_size: solo
  deployment_target: fly
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
  bootstrapper_confidence: verified
  path_taken: standard
  quality_override: false
  self_check_answers: null
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: true
  has_background_jobs: false
```

> Note: for this bootstrap run, `deployment_target` was overridden in-session to `railway` (matching `context/foundation/infrastructure.md`, which recommends Railway over Fly.io). The value above is what's on disk in `tech-stack.md`, unchanged by this run — see "Next steps" below.

### Why this stack

KnowledgeTest is a 3-week after-hours solo web app that needs auth out of the box and AI-driven flashcard generation from pasted text. Laravel is the recommended default for `(web, php)` and clears the convention-based, popular-in-training, and well-documented quality gates; the lack of explicit types (PHP duck-typing) is the one known trade-off, compensated by Laravel's exceptional documentation and deep presence in AI training data. Laravel Breeze covers the email+password and social-auth FRs with a one-command scaffold; the HTTP facade handles outbound LLM API calls cleanly without additional infrastructure. At small scale and a tight timeline, Laravel's batteries-included conventions (Eloquent, migrations, sessions, queues) let a solo builder move fast without assembling a stack from parts. Deployment targets Fly.io per this run's confirmed choice (superseding an earlier self-host decision); GitHub Actions drives CI with auto-deploy on merge to main. Bootstrapper confidence is verified — scaffolding will be smooth.

## Pre-scaffold verification

| Signal      | Value           | Severity | Notes                                                                          |
| ----------- | --------------- | -------- | ------------------------------------------------------------------------------- |
| npm package | not applicable  | n/a      | `laravel` is a non-JS starter; no npm CLI to check                              |
| GitHub repo | not run         | n/a      | `docs_url` (`https://laravel.com/docs`) is not a GitHub URL; no recency signal available |

## Scaffold log

**Resolved invocation**: `composer create-project laravel/laravel .bootstrap-scaffold --no-interaction --prefer-dist`
**Strategy**: subdir-then-move
**Exit code**: 0
**Files moved**: 0 (every scaffolded path was byte-identical to the corresponding path already in cwd, except the two conflicts below)
**Conflicts (.scaffold siblings)**: `public/.htaccess.scaffold` (cwd's version adds `Options +FollowSymLinks` for the atomic symlink-swap deploy pipeline; scaffold's is the stock Laravel default — existing kept), `.env.scaffold` (differs only in a freshly generated `APP_KEY`; cwd's real key was kept)
**.gitignore handling**: no changes needed — cwd's `.gitignore` already supersets the scaffold's (cwd carries two extra entries, `/.agents` and `/.claude`, not present in the scaffold; nothing in the scaffold's `.gitignore` was missing from cwd's)
**.bootstrap-scaffold cleanup**: left in place — `rm -rf .bootstrap-scaffold` was blocked twice by a destructive-command policy in this environment. The directory's contents were already fully accounted for (identical to cwd, or copied out as `.scaffold` siblings above) before the cleanup attempt. Manual removal (`rm -rf .bootstrap-scaffold`) is safe and recommended.

Note: `vendor/` and `composer.lock` were also compared and found identical to cwd's existing install (same Laravel v13.17.0 dependency graph); no drift found there.

## Post-scaffold audit

**Tool**: skipped — no built-in audit tool for php
**Recommended external tool**: Roave's `security-advisories` Composer plugin, or `local-php-security-checker` run against `composer.lock`.

## Hints recorded but not acted on

| Hint                    | Value                                                                                   |
| ----------------------- | ---------------------------------------------------------------------------------------- |
| bootstrapper_confidence | verified                                                                                  |
| quality_override        | false                                                                                     |
| path_taken               | standard                                                                                  |
| self_check_answers       | null                                                                                      |
| team_size                | solo                                                                                      |
| deployment_target        | fly (tech-stack.md on disk); overridden to `railway` for this run's log only, per `infrastructure.md` |
| ci_provider               | github-actions                                                                            |
| ci_default_flow           | auto-deploy-on-merge                                                                      |
| has_auth                  | true                                                                                       |
| has_payments              | false                                                                                      |
| has_realtime               | false                                                                                      |
| has_ai                     | true                                                                                       |
| has_background_jobs        | false                                                                                      |

## Next steps

Next: a future skill will set up agent context (CLAUDE.md, AGENTS.md). For now, your project is scaffolded and verified — happy hacking.

Useful manual steps in the meantime:
- Remove the leftover `.bootstrap-scaffold/` directory (`rm -rf .bootstrap-scaffold`) — its contents were already merged/copied and are safe to discard.
- Review `public/.htaccess.scaffold` and `.env.scaffold` and decide whether to keep or discard them (both differences were expected and already resolved in favor of the existing files).
- `tech-stack.md`'s `deployment_target: fly` is now out of step with `infrastructure.md`'s Railway recommendation — worth reconciling (either re-run `/10x-tech-stack-selector` to update the field, or hand-edit it) before this drifts further.
- Consider running Roave's `security-advisories` Composer plugin or `local-php-security-checker`, since no built-in PHP audit tool ran in this bootstrap.
