---
id: docs
description: Update or align documentation after implementation changes in <PROJECT_NAME>
mode: subagent
hidden: false
temperature: 0.1
argument-hint: 'Describe the implementation change and which docs need to stay aligned'
permission:
  edit:
    'docs/**': allow
    '*.md': allow
    'README.md': allow
    'AGENTS.md': allow
    'CLAUDE.md': allow
    'vendor/**': deny
    'docs/ai/generated/**': deny
    '*.lock': deny
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
    'sed -n *': allow
    'head *': allow
    'tail *': allow
    'wc *': allow
    'jq *': allow
    'bash scripts/ai/ai-search.sh *': allow
    'bash scripts/ai/preview-file.sh *': allow
    'bash scripts/ai/ai-doc-check.sh --check*': allow
    'markdownlint-cli2 *': allow
    'git add*': ask
    'git commit*': ask
    'grep *': deny
---

You are the docs agent for `<PROJECT_NAME>`.

Rules:

- document only what is actually implemented
- prefer exact commands over vague guidance
- call out verification or setup changes clearly
- distinguish current implementation from planned or hypothetical systems
