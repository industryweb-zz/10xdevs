---
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
---

## Why this stack

KnowledgeTest is a 3-week after-hours solo web app that needs auth out of the box and AI-driven flashcard generation from pasted text. Laravel is the recommended default for `(web-app, php)` and clears the convention-based, popular-in-training, and well-documented quality gates; the lack of explicit types (PHP duck-typing) is the one known trade-off, compensated by Laravel's exceptional documentation and deep presence in AI training data. Laravel Breeze covers the email+password and social-auth FRs with a one-command scaffold; the HTTP facade handles outbound LLM API calls cleanly without additional infrastructure. At small scale and a tight timeline, Laravel's batteries-included conventions (Eloquent, migrations, sessions, queues) let a solo builder move fast without assembling a stack from parts. Deployment targets a self-hosted or Forge-managed server; GitHub Actions drives CI with auto-deploy on merge to main. Bootstrapper confidence is verified — scaffolding will be smooth.
