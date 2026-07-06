<?php

declare(strict_types=1);

function aiInstallerScriptRegistry(): array
{
    $scripts = [
        'common' => [
            'label' => 'Shared helper library for AI shell scripts',
            'source_path' => 'scripts/ai/common.sh',
            'installed_path' => 'scripts/ai/common.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ai-search' => [
            'label' => 'Safe repository text search wrapper',
            'source_path' => 'scripts/ai/ai-search.sh',
            'installed_path' => 'scripts/ai/ai-search.sh',
            'pack' => 'scripts-pack',
            // Core tools the wrapper always needs. Mode-specific tools (rg/fd/ast-grep)
            // live in optional_tools so a missing one does not block every mode: the
            // backend's own per-mode guard (scripts/ai/internal/search/60-guards.sh,
            // 85-backend-ast.sh) still fails closed for the modes that truly need them.
            'required_tools' => ['bash', 'git', 'jq'],
            'optional_tools' => ['rg', 'fd', 'ast-grep'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
            'introspection' => [
                'introspect_flag' => '--introspect',
                'help_summary' => true,
                'tool' => 'sh-introspect',
            ],
        ],
        'ai-search-multi' => [
            'label' => 'Batch wrapper: run one ai-search mode over several queries',
            'source_path' => 'scripts/ai/ai-search-multi.sh',
            'installed_path' => 'scripts/ai/ai-search-multi.sh',
            'pack' => 'scripts-pack',
            // See ai-search: core tools always required; rg/fd/ast-grep are mode-specific.
            'required_tools' => ['bash', 'git', 'jq'],
            'optional_tools' => ['rg', 'fd', 'ast-grep'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
        ],
        'rg-code' => [
            'label' => 'Code-focused ripgrep wrapper',
            'source_path' => 'scripts/ai/rg-code.sh',
            'installed_path' => 'scripts/ai/rg-code.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'rg'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'fd-files' => [
            'label' => 'File discovery wrapper',
            'source_path' => 'scripts/ai/fd-files.sh',
            'installed_path' => 'scripts/ai/fd-files.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq', 'rg'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'run-repo-tests' => [
            'label' => 'Parallel-first repository test runner',
            'source_path' => 'scripts/ai/run-repo-tests.sh',
            'installed_path' => 'scripts/ai/run-repo-tests.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'php'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'run-test-focused' => [
            'label' => 'Focused PHPUnit runner (--filter or single file)',
            'source_path' => 'scripts/ai/run-test-focused.sh',
            'installed_path' => 'scripts/ai/run-test-focused.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'php'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'preview-file' => [
            'label' => 'Safe file preview wrapper',
            'source_path' => 'scripts/ai/preview-file.sh',
            'installed_path' => 'scripts/ai/preview-file.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
        ],
        'query-usage' => [
            'label' => 'Symbol usage query wrapper',
            'source_path' => 'scripts/ai/query-usage.sh',
            'installed_path' => 'scripts/ai/query-usage.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'rg'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'git-forensics' => [
            'label' => 'Read-only git history tracing wrapper',
            'source_path' => 'scripts/ai/git-forensics.sh',
            'installed_path' => 'scripts/ai/git-forensics.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'php'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'git-branch-origin' => [
            'label' => 'Resolve branch origin, merge base, and branch distance',
            'source_path' => 'scripts/ai/git-branch-origin.sh',
            'installed_path' => 'scripts/ai/git-branch-origin.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => ['--json'],
        ],
        'gh-pr-context' => [
            'label' => 'GitHub PR context wrapper',
            'source_path' => 'scripts/ai/gh-pr-context.sh',
            'installed_path' => 'scripts/ai/gh-pr-context.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'gh', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ai-doc-check' => [
            'label' => 'AI docs consistency checker wrapper',
            'source_path' => 'scripts/ai/ai-doc-check.sh',
            'installed_path' => 'scripts/ai/ai-doc-check.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'rg'],
            'risk' => 'read-only',
            'supports_dry_run' => true,
            'default_args' => ['--check'],
        ],
        'ai-diff-context' => [
            'label' => 'Diff-aware context extraction wrapper',
            'source_path' => 'scripts/ai/ai-diff-context.sh',
            'installed_path' => 'scripts/ai/ai-diff-context.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ai-verify' => [
            'label' => 'Verification workflow wrapper',
            'source_path' => 'scripts/ai/ai-verify.sh',
            'installed_path' => 'scripts/ai/ai-verify.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        // Manual-only, non-autowired per-language convenience wrappers around
        // `ai-verify.sh --language <lang>`. Not referenced by any agent
        // permission tier, capability, command, skill, or hook — a human runs
        // these directly. Each wrapper itself answers `--introspect`/`--help`
        // by delegating to scripts/ai/ai-verify.sh (the canonical
        // implementation), same delegating-shim pattern as
        // scripts/ai/bin/verify/ai-verify.sh; `supports_json: false` here
        // describes the wrapper's own direct-invocation output, not its
        // --introspect support. See
        // docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959/plan.md §8-P3.
        'ai-verify-html' => [
            'label' => 'HTML-only verification wrapper (ai-verify.sh --language html)',
            'source_path' => 'scripts/ai/ai-verify-html.sh',
            'installed_path' => 'scripts/ai/ai-verify-html.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => ['.ai-logs/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'ai-verify-js' => [
            'label' => 'JS-only verification wrapper (ai-verify.sh --language js)',
            'source_path' => 'scripts/ai/ai-verify-js.sh',
            'installed_path' => 'scripts/ai/ai-verify-js.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => ['.ai-logs/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'ai-verify-php' => [
            'label' => 'PHP-only verification wrapper (ai-verify.sh --language php)',
            'source_path' => 'scripts/ai/ai-verify-php.sh',
            'installed_path' => 'scripts/ai/ai-verify-php.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => ['.ai-logs/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'ai-verify-ts' => [
            'label' => 'TS-only verification wrapper (ai-verify.sh --language ts)',
            'source_path' => 'scripts/ai/ai-verify-ts.sh',
            'installed_path' => 'scripts/ai/ai-verify-ts.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => ['.ai-logs/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'ai-verify-vue' => [
            'label' => 'Vue-only verification wrapper (ai-verify.sh --language vue)',
            'source_path' => 'scripts/ai/ai-verify-vue.sh',
            'installed_path' => 'scripts/ai/ai-verify-vue.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => ['.ai-logs/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'ai-rollback' => [
            'label' => 'Rollback snapshot helper',
            'source_path' => 'scripts/ai/ai-rollback.sh',
            'installed_path' => 'scripts/ai/ai-rollback.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'mutating',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier3',
            'mutates_state' => true,
            'writes_paths' => ['.ai-logs/', 'docs/ai/generated/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'ai-edit' => [
            'label' => 'Scoped repository edit wrapper',
            'source_path' => 'scripts/ai/ai-edit.sh',
            'installed_path' => 'scripts/ai/ai-edit.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'python3'],
            'risk' => 'mutating',
            'supports_dry_run' => true,
            'default_args' => [],
            'tier' => 'tier3',
            'mutates_state' => true,
            'writes_paths' => ['repo-scoped'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'pre-tool-use' => [
            'label' => 'Pre-tool policy decision hook',
            'source_path' => 'scripts/ai/pre-tool-use.sh',
            'installed_path' => 'scripts/ai/pre-tool-use.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
        ],
        'post-tool-use' => [
            'label' => 'Post-tool evidence writer hook',
            'source_path' => 'scripts/ai/post-tool-use.sh',
            'installed_path' => 'scripts/ai/post-tool-use.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq', 'date'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => ['.ai-logs/'],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
        ],
        'repomix-context' => [
            'label' => 'Generate Repomix context bundle',
            'source_path' => 'scripts/ai/run-repomix-context.sh',
            'installed_path' => 'scripts/ai/run-repomix-context.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'repomix', 'scc'],
            'risk' => 'read-only',
            'supports_dry_run' => true,
            'default_args' => [],
        ],
        'repomix-file' => [
            'label' => 'Generate Repomix bundle for a single file',
            'source_path' => 'scripts/ai/run-repomix-file.sh',
            'installed_path' => 'scripts/ai/run-repomix-file.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq', 'repomix'],
            'risk' => 'read-only',
            'supports_dry_run' => true,
            'default_args' => [],
        ],
        'repomix-tree' => [
            'label' => 'Generate Repomix context tree',
            'source_path' => 'scripts/ai/repomix-context-tree.sh',
            'installed_path' => 'scripts/ai/repomix-context-tree.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'repomix', 'scc'],
            'risk' => 'read-only',
            'supports_dry_run' => true,
            'default_args' => [],
        ],
        'repomix-scc-router' => [
            'label' => 'Generate SCC-ranked Repomix context',
            'source_path' => 'scripts/ai/repomix-scc-router.sh',
            'installed_path' => 'scripts/ai/repomix-scc-router.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'jq', 'rg', 'repomix', 'scc'],
            'risk' => 'read-only',
            'supports_dry_run' => true,
            'default_args' => [],
        ],
        'repomix-freshness' => [
            'label' => 'Check Repomix context bundle freshness (warn 2d, block >7d)',
            'source_path' => 'scripts/ai/repomix-freshness.sh',
            'installed_path' => 'scripts/ai/repomix-freshness.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
        ],
        'repomix-ensure-fresh' => [
            'label' => 'Ensure Repomix context is fresh; ask-gated, opt-in regeneration',
            'source_path' => 'scripts/ai/repomix-ensure-fresh.sh',
            'installed_path' => 'scripts/ai/repomix-ensure-fresh.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq', 'git', 'repomix', 'scc'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier2',
            'mutates_state' => false,
            'writes_paths' => ['.repomix-context/'],
            'reads_secret_values' => false,
            'supports_json' => false,
            'bounded_output' => true,
            'requires_approval' => true,
        ],
        'pack-context' => [
            'label' => 'Pack AI context bundle',
            'source_path' => 'scripts/ai/pack-context.sh',
            'installed_path' => 'scripts/ai/pack-context.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'jq', 'rg'],
            'risk' => 'read-only',
            'supports_dry_run' => true,
            'default_args' => [],
        ],
        'ai-structured' => [
            'label' => 'Emit structured AI helper output',
            'source_path' => 'scripts/ai/ai-structured.sh',
            'installed_path' => 'scripts/ai/ai-structured.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ai-task' => [
            'label' => 'Task context helper wrapper',
            'source_path' => 'scripts/ai/ai-task.sh',
            'installed_path' => 'scripts/ai/ai-task.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git', 'jq'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ai-test-select' => [
            'label' => 'Select focused tests for a change',
            'source_path' => 'scripts/ai/ai-test-select.sh',
            'installed_path' => 'scripts/ai/ai-test-select.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'session-checkpoint' => [
            'label' => 'Write AI session checkpoint notes',
            'source_path' => 'scripts/ai/session-checkpoint.sh',
            'installed_path' => 'scripts/ai/session-checkpoint.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'mutating',
            'supports_dry_run' => true,
            'default_args' => ['--dry-run'],
        ],
        'ai-file-freshness' => [
            'label' => 'Check AI file freshness signals',
            'source_path' => 'scripts/ai/ai-file-freshness.sh',
            'installed_path' => 'scripts/ai/ai-file-freshness.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ai-install-coverage' => [
            'label' => 'Check installer pack coverage',
            'source_path' => 'scripts/ai/ai-install-coverage.sh',
            'installed_path' => 'scripts/ai/ai-install-coverage.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'check-file-refs' => [
            'label' => 'Check documentation file references',
            'source_path' => 'scripts/ai/check-file-refs.sh',
            'installed_path' => 'scripts/ai/check-file-refs.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'repo-stats' => [
            'label' => 'Summarize repository file statistics',
            'source_path' => 'scripts/ai/repo-stats.sh',
            'installed_path' => 'scripts/ai/repo-stats.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'repo-tool-inventory' => [
            'label' => 'Generate/check required tools inventory doc',
            'source_path' => 'scripts/ai/repo-tool-inventory.sh',
            'installed_path' => 'scripts/ai/repo-tool-inventory.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'ship-audit' => [
            'label' => 'Audit tracked shipping surface for forbidden paths',
            'source_path' => 'scripts/ai/ship-audit.sh',
            'installed_path' => 'scripts/ai/ship-audit.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'git'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'sh-introspect' => [
            'label' => 'Static introspection of shell scripts (never executes target)',
            'source_path' => 'scripts/ai/sh-introspect.sh',
            'installed_path' => 'scripts/ai/sh-introspect.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'php'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
            'tier' => 'tier1',
            'mutates_state' => false,
            'writes_paths' => [],
            'reads_secret_values' => false,
            'supports_json' => true,
            'bounded_output' => true,
            'requires_approval' => false,
        ],
        'install-mandatory-tools' => [
            'label' => 'Install mandatory CLI tools by OS',
            'source_path' => 'scripts/ai/install-mandatory-tools.sh',
            'installed_path' => 'scripts/ai/install-mandatory-tools.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash'],
            'risk' => 'mutating',
            'supports_dry_run' => true,
            'default_args' => ['--dry-run'],
            'source_repo_only' => true,
        ],
        'watch-loop' => [
            'label' => 'Watched command retry wrapper',
            'source_path' => 'scripts/ai/watch-loop.sh',
            'installed_path' => 'scripts/ai/watch-loop.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash'],
            'risk' => 'read-only',
            'supports_dry_run' => false,
            'default_args' => [],
        ],
        'prune-shipped-targets' => [
            'label' => 'Kit-author cleanup of shipped-template duplicates',
            'source_path' => 'scripts/ai/prune-shipped-targets.sh',
            'installed_path' => 'scripts/ai/prune-shipped-targets.sh',
            'pack' => 'scripts-pack',
            'required_tools' => ['bash', 'jq', 'git'],
            'risk' => 'mutating',
            'supports_dry_run' => true,
            'default_args' => ['--list'],
            'source_repo_only' => true,
        ],
    ];

    foreach ($scripts as $id => $entry) {
        if (!isset($entry['autonomy_level'])) {
            $scripts[$id]['autonomy_level'] = aiInstallerInferScriptAutonomyLevel($entry);
        }
    }

    return $scripts;
}

function aiInstallerInferScriptAutonomyLevel(array $entry): string
{
    if (($entry['risk'] ?? '') === 'mutating' || ($entry['mutates_state'] ?? false) === true || ($entry['requires_approval'] ?? false) === true) {
        return 'act_with_approval';
    }

    $writesPaths = $entry['writes_paths'] ?? [];
    if (is_array($writesPaths) && $writesPaths !== []) {
        return 'advise';
    }

    if (($entry['supports_dry_run'] ?? false) === true) {
        return 'advise';
    }

    return 'observe';
}

function aiInstallerCommandPolicyRiskTiers(): array
{
    return [
        'tier0' => 'environment probe',
        'tier1' => 'bounded read-only wrapper',
        'tier2' => 'ask-gated mutation-adjacent or broad read',
        'tier3' => 'explicit approval mutation',
        'tier4' => 'deny',
    ];
}

/**
 * Single source of truth mapping agent names to permission profiles.
 *
 * Profiles intentionally reuse the existing risk/autonomy taxonomy rather than
 * introducing a new scale. See docs/tickets/arch-todo-agent-permission-rethink-*.
 *
 * @return array<string,string>
 */
function aiInstallerAgentProfiles(): array
{
    return [
        'architect' => 'readonly',
        'researcher' => 'readonly',
        'repository-researcher' => 'readonly',
        'reviewer' => 'readonly',
        'repository-reviewer' => 'readonly',
        'release-auditor' => 'readonly',
        'workflow-auditor' => 'readonly',
        // Extended to all 15 shipped .opencode/agents/*.md per
        // docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
        // Slice 4. Keyed by filename stem, never frontmatter `id` (super-implementer
        // ships `id: implementer` while its filename differs).
        'architecture-plan-writer' => 'readonly',
        'config-maintainer' => 'verify',
        'script-runner' => 'verify',
        'implementer' => 'impl',
        'super-implementer' => 'impl',
        'refactorer' => 'impl',
        'post-install' => 'impl',
        'bootstrapper' => 'impl',
    ];
}

/**
 * Distinct profile identifiers derived from the agent->profile map.
 *
 * @return list<string>
 */
function aiInstallerScriptProfileNames(): array
{
    $names = array_values(array_unique(array_values(aiInstallerAgentProfiles())));
    sort($names);

    return $names;
}

/**
 * Resolve which permission profiles may see/run a registry entry.
 *
 * Derivation rules (no new metadata required on entries):
 * - read-only, non-approval tools are visible to every profile.
 * - read-only tools that still require approval are gated to verify/impl.
 * - mutating tools (or any requires_approval/mutates_state tool) are impl-only.
 *
 * @param array<string,mixed> $entry
 * @return list<string>
 */
function aiInstallerScriptProfiles(array $entry): array
{
    $all = aiInstallerScriptProfileNames();

    $risk = (string) ($entry['risk'] ?? 'read-only');
    $mutates = ($entry['mutates_state'] ?? false) === true;
    $requiresApproval = ($entry['requires_approval'] ?? false) === true;
    $autonomy = (string) ($entry['autonomy_level'] ?? aiInstallerInferScriptAutonomyLevel($entry));

    $isMutating = $risk === 'mutating' || $mutates || $autonomy === 'act_with_approval';

    if ($isMutating) {
        // Only write-capable profiles can run mutation-class tools.
        return array_values(array_filter($all, static fn (string $p): bool => $p === 'impl'));
    }

    if ($requiresApproval) {
        // Read-only but approval-gated: keep out of pure read-only profiles.
        return array_values(array_filter($all, static fn (string $p): bool => $p !== 'readonly'));
    }

    return $all;
}

/**
 * Whether running a registry entry under a profile requires explicit approval.
 *
 * @param array<string,mixed> $entry
 */
function aiInstallerScriptRequiresApproval(array $entry): bool
{
    $risk = (string) ($entry['risk'] ?? 'read-only');
    $mutates = ($entry['mutates_state'] ?? false) === true;
    $requiresApproval = ($entry['requires_approval'] ?? false) === true;
    $autonomy = (string) ($entry['autonomy_level'] ?? aiInstallerInferScriptAutonomyLevel($entry));

    return $risk === 'mutating' || $mutates || $requiresApproval || $autonomy === 'act_with_approval';
}

/**
 * P3.0 — Single normalized projection of a registry entry.
 *
 * This is the one place that turns the mixed static + derived PHP registry data
 * into a stable, fully-populated contract. The gateway descriptor and the
 * `registry:export` generator both build on this so JSON/docs stay in sync with
 * the canonical PHP source. Derived fields (autonomy_level, profiles,
 * requires_approval) are computed here, never hand-stored.
 *
 * Key order is fixed and deterministic so generated output is byte-stable.
 *
 * @param array<string,mixed> $entry
 * @return array<string,mixed>
 */
function aiInstallerNormalizeScriptEntry(string $id, array $entry): array
{
    $required = is_array($entry['required_tools'] ?? null) ? array_values($entry['required_tools']) : [];
    $optional = is_array($entry['optional_tools'] ?? null) ? array_values($entry['optional_tools']) : [];
    $writes = is_array($entry['writes_paths'] ?? null) ? array_values($entry['writes_paths']) : [];

    $normalized = [
        'id' => $id,
        'source_path' => (string) ($entry['source_path'] ?? ''),
        'installed_path' => (string) ($entry['installed_path'] ?? ($entry['source_path'] ?? '')),
        'pack' => (string) ($entry['pack'] ?? 'unknown'),
        'risk' => (string) ($entry['risk'] ?? 'read-only'),
        'autonomy_level' => (string) ($entry['autonomy_level'] ?? aiInstallerInferScriptAutonomyLevel($entry)),
        'profiles' => aiInstallerScriptProfiles($entry),
        'mutates_state' => ($entry['mutates_state'] ?? false) === true,
        'requires_approval' => aiInstallerScriptRequiresApproval($entry),
        'supports_dry_run' => ($entry['supports_dry_run'] ?? false) === true,
        'required_tools' => $required,
        'optional_tools' => $optional,
        'writes_paths' => $writes,
    ];

    if (array_key_exists('tier', $entry)) {
        $normalized['tier'] = (string) $entry['tier'];
    }
    if (isset($entry['introspection']) && is_array($entry['introspection'])) {
        $normalized['introspection'] = $entry['introspection'];
    }
    if (isset($entry['description']) && (string) $entry['description'] !== '') {
        $normalized['description'] = (string) $entry['description'];
    }

    return $normalized;
}

/**
 * P3.1 — Deterministic normalized projection of the whole registry.
 *
 * @return array<string,array<string,mixed>>
 */
function aiInstallerNormalizedScriptRegistry(): array
{
    $out = [];
    foreach (aiInstallerScriptRegistry() as $id => $entry) {
        $out[(string) $id] = aiInstallerNormalizeScriptEntry((string) $id, $entry);
    }

    return $out;
}

/**
 * P3.1 — Render the canonical generated JSON for docs/ai/script-registry.json.
 *
 * Output is byte-stable: fixed schema_version, stable key order (registry
 * insertion order preserved), pretty-printed, trailing newline.
 */
function aiInstallerRenderScriptRegistryJson(): string
{
    $payload = [
        'schema_version' => '1.1.0',
        'scripts' => aiInstallerNormalizedScriptRegistry(),
    ];

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode normalized script registry JSON.');
    }

    return $json . PHP_EOL;
}
