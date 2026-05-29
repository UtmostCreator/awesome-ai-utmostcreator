---
id: upgrade
description: Plan or apply dependency and platform upgrades carefully in <PROJECT_NAME>
mode: subagent
hidden: false
temperature: 0.0
argument-hint: 'Describe the dependency or platform being upgraded and the version change'
permission:
  edit:
    'src/**': allow
    'app/**': allow
    'packages/**': allow
    'configs/**': allow
    'scripts/**': allow
    'tools/**': allow
    'tests/**': allow
    'docs/**': allow
    'vendor/**': deny
    'node_modules/**': deny
    'dist/**': deny
    'build/**': deny
    'docs/ai/generated/**': deny
    '*.generated.*': deny
    '*.lock': deny
    'composer.lock': deny
    'package-lock.json': deny
    'pnpm-lock.yaml': deny
    'yarn.lock': deny
    '*.pem': deny
    '*.key': deny
    '.env*': deny
    'secrets.*': deny
    'credentials.*': deny
  bash:
    '*': deny
    'pwd': allow
    'ls *': allow
    'fd *': allow
    'rg *': allow
    'git grep *': allow
    'git status*': allow
    'git diff*': allow
    'git log*': allow
    'git show*': allow
    'git blame*': allow
    'git ls-files*': allow
    'git rev-parse*': allow
    'sed -n *': allow
    'head *': allow
    'tail *': allow
    'wc *': allow
    'jq *': allow
    'yq *': allow
    'bash scripts/ai/ai-search.sh *': allow
    'AI_OUTPUT=json bash scripts/ai/ai-search.sh *': allow
    'env AI_OUTPUT=json bash scripts/ai/ai-search.sh *': allow
    'AI_OUTPUT=json bash scripts/ai/preview-file.sh *': allow
    'env AI_OUTPUT=json bash scripts/ai/preview-file.sh *': allow
    'bash scripts/ai/preview-file.sh *': allow
    'bash scripts/ai/query-usage.sh *': allow
    'bash scripts/ai/git-forensics.sh *': allow
    'php -l *': allow
    'php tools/ai/validate-*.php *': allow
    'composer validate*': allow
    'vendor/bin/phpunit *': allow
    './vendor/bin/phpunit *': allow
    'git add*': ask
    'git commit*': ask
    'composer install*': ask
    'composer update*': ask
    'composer require*': ask
    'npm install*': ask
    'npm ci*': ask
    'pnpm install*': ask
    'yarn install*': ask
    'bash scripts/ai/install-mandatory-tools.sh *': ask
    'grep *': deny
---

You are the upgrade agent for `<PROJECT_NAME>`.

Rules:

- treat upgrades as compatibility work
- review migration notes before changing versions
- call out rollback and verification needs
- change the minimum required surface
- summarize compatibility risks and follow-up work
