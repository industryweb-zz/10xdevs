---
bootstrapped_at: 2026-07-08T18:45:21Z
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
  deployment_target: self-host
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

### Why this stack

KnowledgeTest is a 3-week after-hours solo web app that needs auth out of the box and AI-driven flashcard generation from pasted text. Laravel is the recommended default for `(web-app, php)` and clears the convention-based, popular-in-training, and well-documented quality gates; the lack of explicit types (PHP duck-typing) is the one known trade-off, compensated by Laravel's exceptional documentation and deep presence in AI training data. Laravel Breeze covers the email+password and social-auth FRs with a one-command scaffold; the HTTP facade handles outbound LLM API calls cleanly without additional infrastructure. At small scale and a tight timeline, Laravel's batteries-included conventions (Eloquent, migrations, sessions, queues) let a solo builder move fast without assembling a stack from parts. Deployment targets a self-hosted or Forge-managed server; GitHub Actions drives CI with auto-deploy on merge to main. Bootstrapper confidence is verified — scaffolding will be smooth.

## Pre-scaffold verification

| Signal             | Value                              | Severity | Notes                              |
| ------------------- | ----------------------------------- | -------- | ----------------------------------- |
| npm package        | not run                            | n/a      | non-JS starter (language_family: php) |
| GitHub repo        | not run                            | n/a      | `gh` CLI unavailable in this environment (command not found); network recency check skipped per WARN-AND-CONTINUE |

## Scaffold log

**Resolved invocation**: `composer create-project laravel/laravel .bootstrap-scaffold --no-interaction --prefer-dist`
**Strategy**: subdir-then-move
**Exit code**: 0
**Files moved**: 24 top-level entries (files and directories) moved from `.bootstrap-scaffold/` to cwd, including `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `vendor/`, `artisan`, `composer.json`, `composer.lock`, `package.json`, `phpunit.xml`, `vite.config.js`, `README.md`, `.env`, `.env.example`, `.editorconfig`, `.gitattributes`, `.gitignore`, `.npmrc`
**Conflicts (.scaffold siblings)**: none — cwd contained no overlapping paths (existing cwd held only `.agents/`, `.claude/`, `CLAUDE.md`, `context/`, `skills-lock.json`, none of which the scaffold wrote)
**.gitignore handling**: moved silently (cwd had no pre-existing `.gitignore`)
**.bootstrap-scaffold cleanup**: deleted

Note: composer reported that `https://repo.packagist.org/packages.json` could not be fully downloaded (SSL certificate verification failure) and fell back to local cache data, which may be out of date. Dependency installation and migration still completed successfully (exit code 0).

## Post-scaffold audit

**Tool**: skipped — no built-in audit tool for php
**Recommended external tool**: Roave's `security-advisories` Composer plugin, or `local-php-security-checker`

## Hints recorded but not acted on

| Hint                       | Value                              |
| --------------------------- | ------------------------------------ |
| bootstrapper_confidence    | verified                            |
| quality_override           | false                               |
| path_taken                 | standard                            |
| self_check_answers         | null                                |
| team_size                  | solo                                |
| deployment_target          | self-host                           |
| ci_provider                | github-actions                      |
| ci_default_flow            | auto-deploy-on-merge                |
| has_auth                   | true                                |
| has_payments                | false                                |
| has_realtime                | false                                |
| has_ai                      | true                                 |
| has_background_jobs         | false                                |

## Next steps

Next: a future skill will set up agent context (CLAUDE.md, AGENTS.md). For now, your project is scaffolded and verified — happy hacking.

Useful manual steps in the meantime:
- `git init` (if you have not already) to start your own repo history.
- Review any `.scaffold` siblings the conflict policy created and decide which version of each file to keep (none were created in this run).
- Address audit findings per your project's risk tolerance — no built-in PHP audit tool ran in v1; consider Roave's `security-advisories` or `local-php-security-checker` manually.
- The composer registry SSL verification failed during this run and packages were resolved from local cache; consider re-running `composer update` once network/SSL access to packagist.org is confirmed, to ensure dependencies are current.
