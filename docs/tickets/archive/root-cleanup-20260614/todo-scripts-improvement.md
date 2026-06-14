# Improved Git Toolkit Plan 🧙‍♂️⚔️

> STATUS (verified against live repo): this plan predates the `scripts/ai`
> reorg and several phases have landed. Corrections:
>
> - ALREADY-DONE — Phase 7 secret gate: `pack-context.sh` calls
>   `require_clean_secret_scan` (impl `scripts/ai/internal/lib/70-secrets.sh`).
> - ALREADY-DONE — Phase 4 dynamic base discovery: implemented in
>   `scripts/ai/git-branch-origin.sh` (upstream/merge-base ranking + fallback
>   chain); no hard-coded `origin/main` assumption remains.
> - STALE PATHS — `common.sh` is now a thin facade over `scripts/ai/internal/lib/*`;
>   the proposed `common.sh` git helpers belong in `internal/lib/`, and only
>   `git_root` exists today (the other ~12 helpers are still valid TODOs).
> - WRONG — verification commands referencing `scripts/ai/check-safety-contracts.sh`
>   and `scripts/ai/validate-machine-output.sh` (files do not exist); they are
>   unbuilt deliverables, not runnable checks.
> - STALE — every `grep -R ... scripts/ai` verification snippet now double-counts
>   flat impls + `internal/**` modules + `bin/**` shims; scope to canonical paths
>   or exclude `internal/`+`bin/`. Prefer `scripts/ai/bin/read/ai-search.sh`.
> - STILL-VALID — Phase 3 safety headers, Phase 5 preflight/operation-state, the
>   remaining `internal/lib` git helpers, and `repo-tool-inventory.sh --security`.
>   Reconcile Phase 3 intent with the existing `scripts/ai/MANIFEST.md`.

## Executive Verdict

Your original command map was **strategically strong**: it chose the right Git families for scouting, reviewing, verifying, searching, rollback, PR context, and repo forensics.

My review added the missing “battle armor”:

| Layer             | Original Draft                     | My Review                             | Improved Final Plan                                                                  |
| ----------------- | ---------------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------ |
| Command selection | Mostly correct commands per script | Keep most commands                    | Keep command map, but make every command script-safe                                 |
| Output parsing    | Mostly human-readable              | Add `-z`, `--format`, ISO dates       | Require machine-safe output by default                                               |
| Safety            | Guards obvious danger commands     | Add safety classes and mutation gates | Every script gets a formal safety contract                                           |
| Branch base       | Mostly assumes `origin/main`       | Warned this is brittle                | Add dynamic base discovery                                                           |
| Context packing   | Strong Git context idea            | Warned about secrets                  | Add secret scanning and exclusion rules                                              |
| Rollback          | Uses stash/reflog/reset/revert     | Warned stash/reset can bite           | Add checkpoint policy and recovery tests                                             |
| Automation        | Good command ideas                 | Need non-interactive guarantees       | Add `--no-pager`, no prompts, no hanging                                             |
| Testing           | Not explicit enough                | Add edge-case tests                   | Add regression suite with weird filenames, dirty states, secrets, and branch layouts |

The important upgrade is this:

> **Do not just choose useful Git commands. Build a safe Git command runtime.**

Git commands such as `diff --name-status`, `--stat`, `--word-diff`, `--name-only`, and triple-dot branch diffs are excellent for review and context generation, but they must be wrapped in safe parsing and branch-base logic. Git’s own diff machinery supports these compact review styles and triple-dot merge-base comparisons, which makes them ideal for AI-oriented context summaries.

---

# Corrections to the Proposed Phase Plan

## Keep These Ideas

These are right and should stay:

```bash
git status --porcelain=v1 -z
git diff --name-status -z
git diff --name-only -z
git diff --numstat -z
git ls-files -z
git diff --check
git rev-parse --show-toplevel
git rev-parse --verify HEAD
git merge-base
git grep -n -I
git log -S
git log -G
git blame -L
git reflog
git stash push -u
```

## Fix These Details

### 1. Do not verify many pseudo-refs in one `rev-parse`

This is fragile:

```bash
git rev-parse --verify -q MERGE_HEAD REBASE_HEAD CHERRY_PICK_HEAD REVERT_HEAD
```

Use a loop:

```bash
for ref in MERGE_HEAD CHERRY_PICK_HEAD REVERT_HEAD; do
  if git rev-parse -q --verify "$ref" >/dev/null; then
    echo "blocked: active $ref" >&2
    exit 1
  fi
done

git_dir="$(git rev-parse --git-dir)"
test -d "$git_dir/rebase-merge" || test -d "$git_dir/rebase-apply"
```

### 2. Do not always `git switch -c` for checkpoints

This changes the user’s current branch. Better:

```bash
git branch "checkpoint/ai-${script}-$(date -u +%Y%m%d-%H%M%S)" HEAD
```

Then, only if the tree is dirty and mutation is dangerous:

```bash
git stash push -u -m "checkpoint before ${script} $(date -u +%FT%TZ)"
```

`stash` is useful, but it is not magic. Applying a stash can conflict, `pop` may drop successful entries, and stash recovery is local. Git stash behavior and conflict caveats are well documented.

### 3. `git fetch --prune` is not read-only

This mutates remote-tracking refs. Use:

```bash
git fetch --dry-run --prune origin
```

Only run the real command behind `--execute`.

### 4. `origin/main` must become a fallback, not a default assumption

Use dynamic base discovery:

```bash
upstream="$(git rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || true)"
origin_head="$(git symbolic-ref -q --short refs/remotes/origin/HEAD 2>/dev/null || true)"
base_ref="${upstream:-${origin_head:-origin/main}}"
base="$(git merge-base HEAD "$base_ref")"
```

### 5. “Up to date” must not be trusted without fetch metadata

`git status` can say the branch is up to date with `origin/main`, but that only means up to date with the local remote-tracking ref, not necessarily the live remote.

So context scripts should report:

```bash
git for-each-ref refs/remotes/origin/HEAD refs/remotes/origin/main \
  --format='%(refname:short)%09%(objectname)%09%(committerdate:iso8601-strict)'
```

---

# Universal Git Runtime Policy

Every script should inherit this baseline from `scripts/ai/common.sh`.

```bash
export GIT_PAGER=cat
export GIT_TERMINAL_PROMPT=0
export LC_ALL=C
export TZ=UTC
export NO_COLOR=1
```

Every repeated Git command should go through helpers:

```bash
git_root() {
  git rev-parse --show-toplevel
}

git_head() {
  git rev-parse --verify HEAD
}

git_branch() {
  git branch --show-current 2>/dev/null || true
}

git_status_z() {
  GIT_OPTIONAL_LOCKS=0 git --no-pager status --porcelain=v1 -z
}

git_changed_files_z() {
  git --no-pager diff --name-status -z "$@"
}

git_ls_files_z() {
  git --no-pager ls-files -z "$@"
}

git_require_no_operation_in_progress() {
  local git_dir ref
  git_dir="$(git rev-parse --git-dir)"

  for ref in MERGE_HEAD CHERRY_PICK_HEAD REVERT_HEAD; do
    if git rev-parse -q --verify "$ref" >/dev/null; then
      echo "blocked: repository has active $ref" >&2
      return 1
    fi
  done

  if [ -d "$git_dir/rebase-merge" ] || [ -d "$git_dir/rebase-apply" ]; then
    echo "blocked: repository has active rebase" >&2
    return 1
  fi

  if [ -f "$(git rev-parse --git-path BISECT_LOG)" ]; then
    echo "blocked: repository has active bisect" >&2
    return 1
  fi
}
```

---

# Safety Contract Header for Every Script

Each script should start with this block:

```bash
# AI-GIT-SAFETY:
# mode: read-only | index-mutating | worktree-mutating | history-mutating | network-mutating
# requires-clean-tree: yes/no
# touches-index: yes/no
# touches-worktree: yes/no
# touches-refs: yes/no
# touches-remote: yes/no
# requires-confirmation: yes/no
# creates-checkpoint-first: yes/no
# machine-output-default: yes/no
```

This is the “character sheet” for every script. No mystery NPCs holding `git reset --hard` behind their back.

---

# Most Useful Git Commands Per Script

## Baseline Commands for Almost Every Script

Use these unless the script has a very narrow purpose:

```bash
git --no-pager rev-parse --show-toplevel
git --no-pager rev-parse --is-inside-work-tree
git --no-pager rev-parse --verify HEAD
git --no-pager branch --show-current
git --no-pager status --porcelain=v1 -z
```

Why: every report should identify the repo, branch, commit, and dirty state. Otherwise the tool is like loading the wrong save file before a boss fight.

---

## Script Command Matrix

| Script                                  | Most Useful Git Commands                                                                                                                                                                            | Why They Matter for AI-Optimized Behavior                                                                                                                                                     |
| --------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `scripts/ai/ai-diff-context.sh`         | `git diff --name-status -z`, `git diff --stat`, `git diff --numstat -z`, `git diff --unified=0`, `git diff --word-diff=porcelain`, `git diff --check`, `git merge-base`, `git diff "$base" HEAD --` | Builds compact review context. Use `-z` for file safety, `--unified=0` for tiny hunks, `--check` for whitespace/conflict-marker hazards, and merge-base for branch-aware review.              |
| `scripts/ai/ai-doc-check.sh`            | `git diff --name-only -z "$base" HEAD -- '*.md'`, `git ls-files -z '*.md'`, `git grep -n -I`, `git log -1 --format='%cI%x09%H%x09%s' -- "$file"`                                                    | Detects changed docs, stale docs, missing references, and last-touch metadata.                                                                                                                |
| `scripts/ai/ai-edit.sh`                 | `git status --porcelain=v1 -z`, `git diff --check`, `git diff -- "$file"`, `git add -- "$file"`, `git diff --cached -- "$file"`, `git restore --staged -- "$file"`                                  | Edit mode needs surgical visibility. Avoid `git add -p` unless TTY. Always show before/after and staged diff.                                                                                 |
| `scripts/ai/ai-file-freshness.sh`       | `git ls-files --error-unmatch -- "$file"`, `git log -1 --format='%cI%x09%H%x09%an%x09%s' -- "$file"`, `git blame --line-porcelain -- "$file"`                                                       | Tells whether a file is ancient dragon lore or fresh battlefield intel.                                                                                                                       |
| `scripts/ai/ai-install-coverage.sh`     | `git ls-files -z`, `git grep -n -I -e 'install' -e 'setup' -e 'bootstrap' -e 'coverage' --`, `git diff --name-only -z "$base" HEAD`                                                                 | Maps changed files to install/setup/coverage docs.                                                                                                                                            |
| `scripts/ai/ai-rollback.sh`             | `git reflog --date=iso`, `git show --stat --patch`, `git stash list`, `git stash show -p`, `git restore --source=HEAD -- "$file"`, `git revert`, `git reset --soft`, guarded `git reset --hard`     | Rollback needs discovery first, mutation second. `reflog` is the resurrection shrine, but `reset --hard` is cursed gear. Reset can throw away uncommitted work and move branches dangerously. |
| `scripts/ai/ai-search-introspect.sh`    | `git rev-parse --show-toplevel`, `git ls-files -z`, `git check-ignore -v -- "$path"`, `git grep -n -I`                                                                                              | Explains what search will include/exclude without executing unknown tools.                                                                                                                    |
| `scripts/ai/ai-search-multi.sh`         | `git grep -n -I -e "$pattern" --`, `git grep -l -z -I`, `git log -S"$needle" --`, `git log -G"$regex" --`, `git ls-files -z`                                                                        | Combines current-tree search with history search. `git grep` searches repository content; pickaxe searches diffs across history.                                                              |
| `scripts/ai/ai-search.sh`               | `git grep -n -I -- "$pattern" --`, `git grep --untracked -n -I`, `git ls-files -z`, `git check-ignore -v -- "$path"`                                                                                | Safer than raw filesystem search because it respects tracked files. Add `--untracked` only when explicitly requested.                                                                         |
| `scripts/ai/ai-structured.sh`           | `git status --porcelain=v1 -z`, `git diff --numstat -z`, `git diff --name-status -z`, `git log --format='%H%x09%an%x09%cI%x09%s'`, `git ls-files -z`                                                | Produces stable records for downstream tooling. TSV/NUL beats prose.                                                                                                                          |
| `scripts/ai/ai-task.sh`                 | `git branch --show-current`, `git rev-parse --verify HEAD`, `git log -1 --format='%h%x09%s'`, `git diff --name-only -z "$base" HEAD`, `git status --porcelain=v1 -z`                                | Anchors task reports to branch, commit, and touched files.                                                                                                                                    |
| `scripts/ai/ai-test-select.sh`          | `git diff --name-only -z "$base" HEAD`, `git grep -n -I -e 'describe' -e 'it(' -e 'test_' -e 'pytest' -e 'phpunit' --`, `git log --name-only --format='' --since='90 days ago'`                     | Selects likely tests from changed code and test history.                                                                                                                                      |
| `scripts/ai/ai-verify.sh`               | `git diff --check`, `git diff --cached --check`, `git status --porcelain=v1 -z`, `git ls-files --others --exclude-standard -z`, `git ls-files -u`                                                   | Catches whitespace, conflict markers, untracked files, staged surprises, and unresolved merges.                                                                                               |
| `scripts/ai/all_in_one.sh`              | `git rev-parse --show-toplevel`, `git rev-parse --verify HEAD`, `git status --porcelain=v1 -z`, `git diff --name-status -z`, `git log -1 --oneline`                                                 | Wrapper should print the minimap before running powerful commands.                                                                                                                            |
| `scripts/ai/build-ai-help-bundle.sh`    | `git ls-files -z 'scripts/ai/*.sh'`, `git grep -n -I -e 'Usage:' -e '--help' -e '--introspect' -- scripts/ai`, `git log -1 --format='%cI%x09%H%x09%s' -- scripts/ai`                                | Builds help docs from tracked scripts and their latest change metadata.                                                                                                                       |
| `scripts/ai/check-file-refs.sh`         | `git ls-files -z`, `git grep -n -I`, `git check-ignore -v -- "$path"`, `git log --follow -- "$file"`                                                                                                | Finds broken references and follows rename history.                                                                                                                                           |
| `scripts/ai/common.sh`                  | `git rev-parse`, `git status --porcelain=v1 -z`, `git diff --check`, `git merge-base`, `git symbolic-ref`, `git for-each-ref`                                                                       | Centralized safe Git runtime. This is the guild hall.                                                                                                                                         |
| `scripts/ai/fd-files.sh`                | `git ls-files -z`, `git ls-files --others --exclude-standard -z`, `git check-ignore -v -- "$path"`                                                                                                  | Prefer tracked inventory over raw filesystem wandering.                                                                                                                                       |
| `scripts/ai/gh-pr-context.sh`           | `git merge-base`, `git diff --name-status -z "$base" HEAD`, `git log --format='%H%x09%s' "$base"..HEAD`, `git for-each-ref`, plus guarded `gh pr view`, `gh pr checks`                              | Combines local Git truth with PR metadata. Must disclose whether local remote refs are stale.                                                                                                 |
| `scripts/ai/git-branch-origin.sh`       | `git reflog --date=iso`, `git branch -vv`, `git for-each-ref --format`, `git merge-base --fork-point`, `git log --graph --decorate --oneline --all`                                                 | Git does not store branch origin directly, so use evidence trails: reflog, upstream, fork-point, graph.                                                                                       |
| `scripts/ai/git-forensics.sh`           | `git blame -L`, `git log -S`, `git log -G`, `git bisect`, `git show --stat --patch`, `git reflog`, `git fsck --unreachable`                                                                         | Detective kit. `blame` explains line ancestry; `log -S` finds commits that add/remove a string.                                                                                               |
| `scripts/ai/install-mandatory-tools.sh` | `git rev-parse --show-toplevel`, `git status --porcelain=v1 -z`, `git ls-files -z`, `git grep -n -I -e 'shellcheck' -e 'jq' -e 'gh' -e 'repomix' --`                                                | Ensures install actions are rooted in the intended repo and current tracked config.                                                                                                           |
| `scripts/ai/pack-context.sh`            | `git ls-files -z`, `git diff --name-only -z`, `git log -1 --format='%cI%x09%H' -- "$file"`, `git grep -n -I` secret scan, `git check-attr export-ignore -- "$file"`                                 | Packs only tracked, relevant, fresh, non-secret context.                                                                                                                                      |
| `scripts/ai/post-tool-use.sh`           | `git status --porcelain=v1 -z`, `git diff --name-status -z`, `git diff --stat`, `git diff --check`, `git log -1 --oneline`                                                                          | After-action combat log: what changed, what broke, what remains dirty.                                                                                                                        |
| `scripts/ai/pre-tool-use.sh`            | `git rev-parse --show-toplevel`, `git rev-parse --verify HEAD`, `git status --porcelain=v1 -z`, `git diff --quiet`, `git ls-files --error-unmatch -- "$file"`                                       | Preflight guard before tools touch files.                                                                                                                                                     |
| `scripts/ai/preview-file.sh`            | `git ls-files --error-unmatch -- "$file"`, `git cat-file -s`, `git show HEAD:"$file"`, `git log -1 --format='%cI%x09%H%x09%s' -- "$file"`, `git blame -L`                                           | Safe preview of tracked file content with freshness metadata. Add size/binary limits.                                                                                                         |
| `scripts/ai/prune-shipped-targets.sh`   | `git fetch --dry-run --prune`, `git branch -vv`, `git branch --merged`, `git log "$branch" --not "$base_ref"`, `git worktree list --porcelain`                                                      | Dangerous. Must preview branches, worktrees, and unmerged commits before deletion.                                                                                                            |
| `scripts/ai/query-usage.sh`             | `git grep -n -I -e 'Usage:' -e 'Examples:' -e '--help' -- scripts/ai`, `git ls-files -z 'scripts/ai/*.sh'`                                                                                          | Static usage discovery without executing scripts.                                                                                                                                             |
| `scripts/ai/repo-stats.sh`              | `git shortlog -sn --no-merges`, `git log --numstat --format`, `git log --format='%ad' --date=format:%Y-%m`, `git ls-files -z`, `git rev-list --count HEAD`                                          | Hotspots, churn, velocity, authorship, file count. Great for planning.                                                                                                                        |
| `scripts/ai/repo-tool-inventory.sh`     | `git ls-files -z`, `git grep -n -I -e 'shellcheck' -e 'jq' -e 'rg' -e 'fd' -e 'gh' -e 'npm' -e 'pytest' --`, `git log -1 -- scripts .github`                                                        | Discovers available tools and recent toolchain changes.                                                                                                                                       |
| `scripts/ai/repomix-context-tree.sh`    | `git ls-files -z`, `git diff --name-only -z`, `git log --name-only --format='' --since='30 days ago'`, `git rev-parse HEAD`                                                                         | Creates stable context tree from tracked files, changed files, and recent files.                                                                                                              |
| `scripts/ai/repomix-ensure-fresh.sh`    | `git rev-parse HEAD`, `git diff --quiet`, `git diff --cached --quiet`, `git log -1 --format='%H' -- "$file"`                                                                                        | Verifies bundle freshness against current HEAD and dirty state.                                                                                                                               |
| `scripts/ai/repomix-freshness.sh`       | `git rev-parse HEAD`, `git log -1 --format='%H%x09%cI'`, `git diff --name-only -z`, `git status --porcelain=v1 -z`                                                                                  | Read-only freshness report.                                                                                                                                                                   |
| `scripts/ai/repomix-scc-router.sh`      | `git ls-files -z`, `git diff --name-only -z`, `git log --numstat --format`, `git rev-list --count HEAD`                                                                                             | Routes small repos to full context and large repos to selective context.                                                                                                                      |
| `scripts/ai/rg-code.sh`                 | `git grep -n -I`, `git grep -n --function-context`, `git ls-files -z`, optional `rg --files` only outside tracked mode                                                                              | Prefer `git grep` for tracked repo truth; `rg` only for external/untracked modes.                                                                                                             |
| `scripts/ai/run-repo-tests.sh`          | `git rev-parse HEAD`, `git status --porcelain=v1 -z`, `git diff --name-only -z "$base" HEAD`, `git log -1 --oneline`                                                                                | Records exactly what commit and diff the test run validates.                                                                                                                                  |
| `scripts/ai/run-repomix-context.sh`     | `git rev-parse HEAD`, `git ls-files -z`, `git diff --name-only -z`, `git status --porcelain=v1 -z`, secret scan                                                                                     | Large context generation needs stable identity and dirty-tree disclosure.                                                                                                                     |
| `scripts/ai/run-repomix-file.sh`        | `git ls-files --error-unmatch -- "$file"`, `git log -1 --format='%cI%x09%H' -- "$file"`, `git show HEAD:"$file"`, `git cat-file -s`                                                                 | Single-file context with existence, freshness, size, and content guardrails.                                                                                                                  |
| `scripts/ai/run-test-focused.sh`        | `git diff --name-only -z "$base" HEAD`, `git grep -n -I -e 'test' -e 'describe' -e 'it(' -e 'class .*Test' --`, `git log --follow -- "$testfile"`                                                   | Selects focused tests using changed files, naming conventions, and test history.                                                                                                              |
| `scripts/ai/session-checkpoint.sh`      | `git status --porcelain=v1 -z`, `git diff --stat`, `git diff --name-status -z`, `git stash push -u -m`, `git branch checkpoint/... HEAD`, optional `git commit --allow-empty -m`                    | Save progress before risky work. Prefer visible checkpoint refs plus stash for dirty work.                                                                                                    |
| `scripts/ai/sh-introspect.sh`           | `git ls-files -z 'scripts/**/*.sh'`, `git grep -n -I -e '^#' -e 'Usage:' -e 'case .* in' -e 'getopts' --`, `git log -1 -- "$script"`                                                                | Static shell script map. Avoid executing unknown scripts.                                                                                                                                     |
| `scripts/ai/watch-loop.sh`              | `git status --porcelain=v1 -z`, `git diff --name-status -z`, `git diff --quiet`, `git log -1 --oneline`                                                                                             | Detects changes repeatedly, summarizes safely, and avoids mutation loops.                                                                                                                     |

---

# Improved Implementation Plan with TODOs, ACs, Tests, and Verification

## Phase 1 — Build the Shared Git Runtime

### TODO 1.1 — Create `common.sh` Git helpers

**Implement:**

```bash
git_root
git_head
git_branch
git_status_z
git_ls_files_z
git_changed_files_z
git_discover_base_ref
git_merge_base_for_review
git_require_no_operation_in_progress
git_emit_preflight
git_checkpoint_ref
git_checkpoint_stash
git_secret_scan_paths
```

**ACs:**

- All repeated Git patterns live in `common.sh`.
- Every script sources `common.sh`.
- Scripts do not manually reimplement repo root, HEAD, branch, status, or base-branch discovery.
- All file-list helpers return NUL-delimited output.

**Tests:**

- Run helper tests inside:
  - normal repo
  - detached HEAD
  - repo with no upstream
  - repo with `master`
  - repo with `develop`
  - repo with `origin/HEAD`

**Verification:**

```bash
grep -R "git rev-parse --show-toplevel" scripts/ai \
  | grep -v common.sh && exit 1 || true

grep -R "git diff --name-only" scripts/ai \
  | grep -v -- "-z" && exit 1 || true
```

---

## Phase 2 — Make Output Machine-Safe

### TODO 2.1 — Convert file-producing commands to `-z`

**Commands to replace:**

```bash
git status --porcelain=v1
git diff --name-only
git diff --name-status
git diff --numstat
git ls-files
git ls-files --others --exclude-standard
```

**Preferred:**

```bash
git status --porcelain=v1 -z
git diff --name-only -z
git diff --name-status -z
git diff --numstat -z
git ls-files -z
git ls-files --others --exclude-standard -z
```

**ACs:**

- Every script that parses filenames uses `-z`.
- Every shell loop uses:

```bash
while IFS= read -r -d '' path; do
  ...
done
```

- Rename parsing handles two-path records.

**Tests:**

Create files:

```bash
touch "normal.txt"
touch "file with spaces.txt"
touch "tabs	inside.txt"
touch "emoji-🧙.txt"
printf 'x' > "line
break.txt"
```

Then verify all scripts preserve names exactly.

**Verification:**

```bash
# CORRECTED: scope to canonical impls; exclude internal/ modules + bin/ shims to
# avoid triple-counting after the reorg. (The only `while read` already uses
# NUL-safe `while IFS= read -r -d ''`; query-usage.sh has one plain `xargs` on
# the rg fallback path that is still valid to fix.)
rg "while read" scripts/ai --glob '!scripts/ai/internal/**' --glob '!scripts/ai/bin/**' && exit 1 || true
rg "xargs " scripts/ai --glob '!scripts/ai/internal/**' --glob '!scripts/ai/bin/**' | rg -v "xargs -0" && exit 1 || true
```

---

## Phase 3 — Add Safety Contracts

### TODO 3.1 — Add safety headers to every script

**ACs:**

Each script declares:

```bash
# mode:
# requires-clean-tree:
# touches-index:
# touches-worktree:
# touches-refs:
# touches-remote:
# requires-confirmation:
# creates-checkpoint-first:
# machine-output-default:
```

**Tests:**

Create `scripts/ai/check-safety-contracts.sh`.

It should fail if:

- Header missing.
- Script uses `git add` but says `touches-index: no`.
- Script uses `git restore`, `git checkout`, `git clean`, or `git reset --hard` but says `touches-worktree: no`.
- Script uses `git fetch --prune`, `git push`, or `gh` mutation but says `touches-remote: no`.

**Verification:**

```bash
scripts/ai/check-safety-contracts.sh
```

---

## Phase 4 — Branch Base Discovery

### TODO 4.1 — Remove hard-coded `origin/main`

> ALREADY-DONE (behavior): dynamic base discovery with upstream/merge-base
> ranking and a fallback chain already exists in
> `scripts/ai/git-branch-origin.sh` (fallback loop `origin/main origin/master
> main master`). The helper is not named `git_discover_base_ref`, but the
> behavior is present. The verification snippet below is STALE: it false-positives
> on a comment in `ai-verify.sh` and the intended fallback in `git-branch-origin.sh`.

**ACs (satisfied by `git-branch-origin.sh`):**

- Dynamic base discovery, not a hard-coded default.
- Fallback order: `@{u}` -> `refs/remotes/origin/HEAD` -> `origin/main` (warn).
- Merge-base computed once; triple-dot semantics documented.

---

## Phase 5 — Preflight and Transitional State Blocking

### TODO 5.1 — Add preflight to mutating scripts

**ACs:**

Before mutation, scripts print or emit structured data for:

```text
repo root
HEAD
branch
dirty state
staged changes
unstaged changes
untracked files
operation-in-progress state
```

**Tests:**

Run mutating scripts during:

```bash
git merge --no-commit some-branch
git rebase ...
git cherry-pick --no-commit ...
git bisect start
```

Expected result: script exits before mutation unless explicitly designed for that state.

**Verification:**

```bash
scripts/ai/ai-verify.sh
```

Must report:

```text
operation_state: clean | merge | rebase | cherry-pick | revert | bisect
```

---

## Phase 6 — Mutation Checkpoints

### TODO 6.1 — Add checkpoint before state changes

**ACs:**

For scripts that touch worktree/index/refs:

- Create checkpoint ref:

```bash
git branch "checkpoint/ai-${script}-${timestamp}" HEAD
```

- If dirty tree exists and script may overwrite changes:

```bash
git stash push -u -m "checkpoint before ${script} ${timestamp}"
```

- Destructive commands require `--force` or `--execute`.

**Tests:**

- Dirty tracked file survives failed script.
- Untracked file survives failed script.
- Checkpoint branch exists.
- Stash entry exists when dirty tree was present.
- Recovery instructions are printed.

**Verification:**

```bash
git branch --list 'checkpoint/ai-*'
git stash list
```

---

## Phase 7 — Secret and Sensitive File Protection

### TODO 7.1 — Add context-packing secret gate

**ACs:**

Before including file content in context bundles:

- Skip ignored files unless explicitly allowed.
- Skip known sensitive patterns:

```text
.env
*.pem
*.key
*.p12
id_rsa
credentials
.kube/config
```

- Run a basic secret scan:

```bash
git grep -n -I \
  -e 'AWS_SECRET' \
  -e 'PRIVATE KEY' \
  -e 'TOKEN=' \
  -e 'PASSWORD=' \
  -e 'SECRET=' \
  --
```

- Support a local allowlist file for false positives.

**Tests:**

Create tracked files containing:

```text
AWS_SECRET_ACCESS_KEY=fake
-----BEGIN PRIVATE KEY-----
TOKEN=fake
```

Expected: file is excluded or redacted.

**Verification:**

```bash
scripts/ai/pack-context.sh > /tmp/context.txt
! grep -E 'AWS_SECRET|PRIVATE KEY|TOKEN=' /tmp/context.txt
```

---

## Phase 8 — GitHub Actions and Tooling Security

### TODO 8.1 — Scan workflow risks

**ACs:**

`repo-tool-inventory.sh` or a dedicated checker reports:

```bash
git grep -n -I 'permissions:' .github/workflows
git grep -n -I 'write-all' .github/workflows
git grep -n -I 'pull_request_target' .github/workflows
git grep -n -I 'secrets\.' .github/workflows
```

Critical flags:

```text
permissions: write-all
pull_request_target without strict checkout/ref handling
unscoped GITHUB_TOKEN use
```

**Tests:**

Add fixture workflows with safe and unsafe permissions.

**Verification:**

```bash
scripts/ai/repo-tool-inventory.sh --security
```

---

## Phase 9 — Human vs Machine Output Modes

### TODO 9.1 — Add `--format machine|pretty`

**ACs:**

Default mode for automation:

```text
TSV records
NUL file lists
ISO dates
no colors
no pager
stable field order
```

Pretty mode only with:

```bash
--pretty
```

**Tests:**

- Machine output parses with shell/Python.
- Pretty output is not used by other scripts.
- CI uses machine mode only.

**Verification:**

```bash
scripts/ai/ai-structured.sh --format machine | scripts/ai/validate-machine-output.sh
```

---

## Phase 10 — Final Regression Test Suite

### TODO 10.1 — Add repository fixture tests

**Test cases:**

| Test                  | Expected Result                                   |
| --------------------- | ------------------------------------------------- |
| Weird filenames       | No filename corruption                            |
| Dirty tree            | Mutating scripts checkpoint or exit               |
| Staged changes        | Verify scripts detect them                        |
| Missing `origin/main` | Base discovery still works                        |
| Active merge          | Mutating scripts block                            |
| Active rebase         | Mutating scripts block                            |
| Active bisect         | Mutating scripts block                            |
| Tracked secret        | Context scripts exclude/redact                    |
| Large file            | Preview/context scripts cap output                |
| Binary file           | Preview/context scripts avoid dumping raw content |
| Workflow `write-all`  | Security scan flags critical                      |
| `git clean` candidate | Dry-run shown before deletion                     |

**Verification command:**

```bash
scripts/ai/run-repo-tests.sh
scripts/ai/ai-verify.sh
scripts/ai/check-safety-contracts.sh
```

---

# Final Definition of Done

The toolkit is complete when:

```text
✅ Every script has a safety contract.
✅ Every parsed file list is NUL-delimited.
✅ Every mutating script has checkpoint logic.
✅ No script assumes origin/main without fallback logic.
✅ No script hangs in CI.
✅ Context packers exclude secrets and large/binary files.
✅ Rollback scripts show before they mutate.
✅ Network-mutating scripts support dry-run first.
✅ Tests cover weird filenames, dirty trees, missing upstreams, active Git operations, and secrets.
✅ Machine output is stable and documented.
```

---

# Priority Order

## First 5 Tasks to Implement

1. **Centralize `common.sh` helpers.**
2. **Convert all file parsing to `-z`.**
3. **Add safety contract headers.**
4. **Replace hard-coded `origin/main`.**
5. **Add secret gate to context packers.**

These five give you the biggest safety upgrade for the smallest implementation cost.

---

# Final Recommendation

Your original list chose the right spells. The improved plan adds the spellcasting rules:

```text
Scout first.
Parse safely.
Never assume origin/main.
Never dump secrets.
Never mutate without a checkpoint.
Never trust pretty output in automation.
Never fight Git mid-merge unless that is the quest.
```

That turns the toolkit from “useful Git command collection” into a **production-grade AI-optimized Git operating layer**.
