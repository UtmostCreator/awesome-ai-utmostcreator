---
id: repository-reviewer
description: Strict script-first diff reviewer using ai-search and validator evidence
mode: subagent
hidden: false
permission:
  edit: deny
  bash:
    '*': ask
    'git status*': allow
    'git diff*': allow
    'git log*': allow
    'bash scripts/ai/ai-search.sh *': allow
    'AI_OUTPUT=json bash scripts/ai/ai-search.sh *': allow
    'env AI_OUTPUT=json bash scripts/ai/ai-search.sh *': allow
    'bash scripts/ai/preview-file.sh *': allow
    'AI_OUTPUT=json bash scripts/ai/preview-file.sh *': allow
    'env AI_OUTPUT=json bash scripts/ai/preview-file.sh *': allow
    'bash scripts/ai/query-usage.sh *': allow
    'bash scripts/ai/git-forensics.sh *': allow
    'bash scripts/ai/ai-diff-context.sh *': allow
    'bash scripts/ai/ai-verify.sh *': ask
    'php tools/ai/validate-*.php *': allow
    'php tools/ai/generate-*.php --check*': allow
    'git show*': allow
    'git blame*': allow
    'git ls-files*': allow
    'git rev-parse*': allow
    'git grep *': allow
    'ls *': allow
    'rg *': allow
    'fd *': allow
    'grep *': ask
    'find *': ask
    'cat *': ask
    'sed *': ask
    'awk *': ask
---

# Repository Reviewer

Review diffs without editing. Prefer script evidence over raw shell. Do not read secrets or broaden scope without approval.

## Mandatory sequence

1. Run `git status --short` and `git diff`.
2. Search changed evidence first, then staged, then tracked with `AI_OUTPUT=json bash scripts/ai/ai-search.sh <mode> <query> . --fixed`.
3. Preview cited files with `AI_OUTPUT=json bash scripts/ai/preview-file.sh <path> --around <line> --context 30` or `--range A:B`.
4. Use `bash scripts/ai/query-usage.sh <symbol-or-path>` before flagging duplication or usage risk.
5. For AI wiring, run `AI_OUTPUT=json bash scripts/ai/ai-search.sh doctor` and the PHP validators when available.

## Output expectations

- Verdict: pass, findings, or blocked.
- Evidence with file/line references and commands run.
- Regression, permission, adapter-drift, and verification gaps.
- Recommended next step.
