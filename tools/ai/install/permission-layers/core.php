<?php

declare(strict_types=1);

/**
 * Core, runtime-agnostic permission layers for generated agent permissions.
 *
 * Each entry is intentionally shaped like rendered OpenCode permission lines so
 * later slices can compare composed output against today's shipped frontmatter.
 *
 * @return array<string,list<array{permission:string,pattern:string,effect:string}>>
 */
function aiPermissionLayersCore(): array
{
    return [
        'hard-deny' => aiPermissionEntries('bash', [
            '*' => 'deny',
            // NOTE (bug fix, 2026-07-05): 'grep *' was previously listed here as an immutable
            // floor entry, but ground truth shows two shipped agents (repository-researcher,
            // repository-reviewer) already grant it 'ask' — proving it was never meant to be
            // un-overridable. Moved to the (non-immutable) safe-read default below, same
            // pattern as the 'sg *' and gh-pr-context precedents in this file.
            'python3 *' => 'deny',
            'php -r *' => 'deny',
            '* <<*' => 'deny',
            '* > *' => 'deny',
            '* >> *' => 'deny',
            'cat > *' => 'deny',
            'cat >> *' => 'deny',
            'rm -rf *' => 'deny',
            'sudo *' => 'deny',
            'ssh *' => 'deny',
            'scp *' => 'deny',
            'watch *' => 'deny',
            'git push*' => 'deny',
            // NOTE: bash scripts/ai/gh-pr-context.sh is intentionally NOT listed here.
            // It is dangerous-by-default (see script-tiers:ai-deny-dangerous, applied as a
            // layer, not the immutable floor) because reviewer/repository-reviewer legitimately
            // allow it via an agent exception; only truly universal denials belong in this
            // immutable floor.
            'bash scripts/ai/ai-task.sh *' => 'deny',
            'bash scripts/ai/pre-tool-use.sh *' => 'deny',
            'bash scripts/ai/post-tool-use.sh *' => 'deny',
            'bash scripts/ai/prune-shipped-targets.sh *' => 'deny',
            'bash scripts/ai/watch-loop.sh *' => 'deny',
            'bash scripts/ai/common.sh*' => 'deny',
        ]),
        'safe-read' => aiPermissionEntries('bash', [
            'command -v *' => 'allow',
            'test -f *' => 'allow',
            'test -x *' => 'allow',
            'test -d *' => 'allow',
            'stat *' => 'allow',
            'date *' => 'allow',
            'uuidgen' => 'allow',
            'pwd' => 'allow',
            'ls *' => 'allow',
            'fd *' => 'allow',
            'eza *' => 'allow',
            'rg *' => 'allow',
            'git grep *' => 'allow',
            // Default deny (not immutable — see the hard-deny NOTE above); repository-researcher
            // and repository-reviewer except this to 'ask' via their compositions.
            'grep *' => 'deny',
            // NOTE: 'sg *' (ast-grep alias) is intentionally NOT here. Ground truth shows it
            // granted only to impl-profile agents (implementer.md, bootstrapper.md), not
            // universally; add it per-agent (exception or overlay) when those agents migrate.
            'sed -n *' => 'allow',
            'head *' => 'allow',
            'tail *' => 'allow',
            'nl *' => 'allow',
            'wc *' => 'allow',
            'sort *' => 'allow',
            'uniq *' => 'allow',
            'file *' => 'allow',
            'du -h *' => 'allow',
            'jq *' => 'allow',
            'yq *' => 'allow',
            'scc *' => 'allow',
            'tokei *' => 'allow',
            'ast-grep *' => 'allow',
            'bat *' => 'allow',
            'fx *' => 'allow',
            'glow *' => 'allow',
            'difft *' => 'allow',
            'delta *' => 'allow',
            'ls -1 scripts/ai/*.sh | sort' => 'allow',
            // NOTE (bug fix, 2026-07-05): two literal, session-transcript-shaped compound
            // commands ('git status --short; echo "---BRANCH---"; ...' and
            // 'git status --short && git branch --show-current') were removed from here.
            // They were exact-match strings (not glob patterns), so they never generalized
            // to any other invocation, and the first one embeds unescaped double quotes
            // inside its own value — which corrupts YAML frontmatter for any agent rendered
            // with `quote: 'double'` (researcher.md; see compositions.php). Both are already
            // fully covered by 'git status*' and 'git branch*' in the git-read layer below.
        ]),
        'git-read' => aiPermissionEntries('bash', [
            'git status*' => 'allow',
            'git diff*' => 'allow',
            'git log*' => 'allow',
            'git show*' => 'allow',
            'git ls-files*' => 'allow',
            'git blame*' => 'allow',
            'git branch*' => 'allow',
            'git rev-parse*' => 'allow',
            // NOTE: 'git stash list*'/'git stash show*' are intentionally NOT here — ground
            // truth shows them only on impl-profile agents (implementer.md); researcher,
            // architect, reviewer, and workflow-auditor do not grant them. Add per-agent
            // (exception or a dedicated layer) when those agents migrate.
        ]),
        'git-mutating-ask' => aiPermissionEntries('bash', [
            'git add*' => 'ask',
            'git commit*' => 'ask',
            'git restore *' => 'ask',
            'git reset*' => 'ask',
            'git stash push*' => 'ask',
            'git stash pop*' => 'ask',
            'git stash apply*' => 'ask',
            'git stash drop*' => 'ask',
            'git fetch*' => 'ask',
            'git merge*' => 'ask',
            'git pull*' => 'ask',
            'git checkout*' => 'ask',
            'git switch*' => 'ask',
            'git tag*' => 'ask',
            'git cherry-pick*' => 'ask',
            'git revert*' => 'ask',
        ]),
        'package-manager-ask' => aiPermissionEntries('bash', [
            'composer install*' => 'ask',
            'composer update*' => 'ask',
            'composer require*' => 'ask',
            'npm install*' => 'ask',
            'npm ci*' => 'ask',
            'pnpm install*' => 'ask',
            'pnpm add*' => 'ask',
            'yarn install*' => 'ask',
            'yarn add*' => 'ask',
            'bun install*' => 'ask',
            'bun add*' => 'ask',
        ]),
        // Ground truth: packages/ai-universal-rules/templates/snippets/agent-tools-readonly.snippet.md,
        // kept in sync historically by generate-agent-snippets.php for agents not yet migrated to the
        // composed generator. Migrated agents get these entries here instead so the whole permission
        // block renders from one source; only net-new entries are listed (scc/tokei/ast-grep/etc and
        // repomix-freshness already live in 'safe-read' / the ai-read script tier).
        'shipped-cli-readonly' => aiPermissionEntries('bash', [
            'php tools/ai/ai.php placeholders*' => 'allow',
            'php tools/ai/ai.php verify*' => 'allow',
            'php tools/ai/ai.php preflight*' => 'allow',
            'php tools/ai/ai.php list' => 'allow',
            'php tools/ai/ai.php next*' => 'allow',
            'php tools/ai/ai.php freshness*' => 'allow',
            'php tools/ai/ai.php packs*' => 'allow',
            'php tools/ai/ai.php env-check*' => 'allow',
            'php tools/ai/ai.php install-docs --check' => 'allow',
            'lychee *' => 'allow',
            'actionlint*' => 'allow',
            'shfmt -d *' => 'allow',
            'shellcheck *' => 'allow',
            'bash scripts/ai/repomix-ensure-fresh.sh *' => 'ask',
        ]),
        // Slice C (docs/tickets/arch-todo-complete-permission-composition-migration/plan.md):
        // architecture-plan-writer is the first readonly-profile agent whose shipped block
        // grants NONE of shipped-cli-readonly's entries (no ai.php subcommands, no
        // lychee/actionlint/shfmt/shellcheck) — an intentionally empty cli-tools variant so
        // its composition can opt out via `cli_tools: 'none'` instead of hand-denying every
        // shipped-cli-readonly pattern back with agent-unique exceptions.
        'shipped-cli-none' => [],
        // Ground truth: packages/ai-universal-rules/templates/snippets/agent-tools-execute.snippet.md.
        'shipped-cli-execute' => aiPermissionEntries('bash', [
            'php tools/ai/ai.php placeholders*' => 'allow',
            'php tools/ai/ai.php verify*' => 'allow',
            'php tools/ai/ai.php preflight*' => 'allow',
            'php tools/ai/ai.php list' => 'allow',
            'php tools/ai/ai.php next*' => 'allow',
            'php tools/ai/ai.php freshness*' => 'allow',
            'php tools/ai/ai.php packs*' => 'allow',
            'php tools/ai/ai.php env-check*' => 'allow',
            'php tools/ai/ai.php install-docs --check' => 'allow',
            'lychee *' => 'allow',
            'actionlint*' => 'allow',
            'shfmt -d *' => 'allow',
            'semgrep *' => 'allow',
            'repomix *' => 'ask',
            'files-to-prompt *' => 'ask',
            'code2prompt *' => 'ask',
            'bash scripts/ai/repomix-ensure-fresh.sh *' => 'ask',
        ]),
    ];
}

/**
 * @param array<string,string> $patterns
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionEntries(string $permission, array $patterns): array
{
    $entries = [];
    foreach ($patterns as $pattern => $effect) {
        $entries[] = ['permission' => $permission, 'pattern' => (string) $pattern, 'effect' => (string) $effect];
    }

    return $entries;
}
