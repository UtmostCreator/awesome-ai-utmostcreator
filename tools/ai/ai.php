<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_output_lib.php';
require_once __DIR__ . '/install/core.php';
require_once __DIR__ . '/advisor/scanner.php';
require_once __DIR__ . '/advisor/scorer.php';
require_once __DIR__ . '/advisor/validator.php';
require_once __DIR__ . '/advisor/secret-scan.php';
require_once __DIR__ . '/advisor/packer.php';
require_once __DIR__ . '/advisor/token-budget.php';
require_once __DIR__ . '/advisor/prompt-builder.php';
require_once __DIR__ . '/advisor/drift.php';
require_once __DIR__ . '/advisor/submitter.php';

require_once __DIR__ . '/commands/helpers.php';
require_once __DIR__ . '/commands/git.php';
require_once __DIR__ . '/commands/verify.php';
require_once __DIR__ . '/commands/next.php';
require_once __DIR__ . '/commands/analysis.php';
require_once __DIR__ . '/commands/workspace.php';
require_once __DIR__ . '/commands/decisions.php';
require_once __DIR__ . '/commands/install_commands.php';
require_once __DIR__ . '/commands/advisor_command.php';
require_once __DIR__ . '/commands/descriptors_command.php';

defined('AI_DIR_MODE') || define('AI_DIR_MODE', 0755);

function aiUsage(): void
{
    $usage = <<<'TXT'
Usage:
  php tools/ai/ai.php <command> [options]

Commands:
  list           List available AI workflow commands
  diff-summary   Summarize current branch diff and changed files
  risk           Score changed-slice risk using deterministic rules
  verify         Run repository AI verification digest
  next           Recommend the next required action
  rebase-state   Run snapshot->diff->risk->verify->freshness->budget->next
  decision       Add architecture/workflow decision records
  why            Show decision rationale history
  session-resume Build concise continuation summary from artifacts
  commit-msg     Generate commit message suggestion from artifacts
  pr-summary     Generate PR summary from artifacts
  logs           List or read generated verify logs
  env-check      Report environment/tooling readiness for AI workflow scripts
  file-context   Build focused context artifact for one file
  orphans        Detect possibly unreferenced/orphan workflow files
  auto-fix       Preview deterministic safe fixes (dry-run only)
  impact         Generate deterministic change impact map
  ask            Record structured blocking clarification questions
  estimate       Estimate task complexity/risk with deterministic heuristics
  conflicts      Summarize merge conflict state and suggested resolution posture
  find           Search tracked files by deterministic path/content match
  symbols        Extract top-level code symbols from tracked source files
  preflight      Check installer prerequisites and environment readiness
  doctor         Read-only health check: environment, tools, and install state
  status         Read-only install status: kit state, drift, conflicts, policy
  package-lock   Check or update source template checksum lock
  package-verify Verify source templates against checksum lock
  audit-instructions Audit local instruction surfaces and ownership hints
  adapter-plan   Generate deterministic install/upgrade plan preview
  plan           Alias of adapter-plan
  install        Run installer workflow (dry-run/default safe)
  upgrade        Preview or apply manifest-aware upgrades (planned)
  adapter-validate Validate installed adapter state and managed assets
  rollback       Restore from installer backup artifacts
                 Use --only <path> to rollback a specific file or directory prefix
  restore        Checksum-gated copy-back from a backup snapshot
                 Use --from <ts> to select the backup and --path <p> for one file.
                 Default is --dry-run; --apply logs to .ai/logs/.
  uninstall      Remove kit-installed files (owned/rendered); preserves template
                 files and .ai/ state unless --purge. Default is --dry-run.
  packs          List installer profiles and packs
  placeholders   Scan and manage unresolved placeholders
  hooks          Hook wiring and status helpers (compatibility surface)
  toolchain      Check/install-plan/apply safe AI toolchain dependencies
  run-script     Run approved scripts-pack helper scripts by registry id
  install-docs   Generate or check install instructions and catalog docs
  advisor        Project intelligence advisor pipeline commands
  descriptors    List relocated .ai/ kit descriptors or copy a safe one out to root
                 Use --list (default) or --copy-out --name <file> (--dry-run default)
  version        Show installer/package identity from canonical manifest
  freshness      Evaluate generated artifact freshness
  budget         Estimate context token budget from generated artifacts
  workflow       Show workflow dependency graph summary
  snapshot       Generate current repository snapshot
  help           Show this help

Examples:
  php tools/ai/ai.php list
  php tools/ai/ai.php freshness
  php tools/ai/ai.php budget --context-window 32000
  php tools/ai/ai.php workflow
  php tools/ai/ai.php snapshot
  php tools/ai/ai.php diff-summary --base main
  php tools/ai/ai.php risk --base main
  php tools/ai/ai.php verify --changed
  php tools/ai/ai.php next
  php tools/ai/ai.php rebase-state
  php tools/ai/ai.php decision add --file tools/ai/ai.php --reason "added workflow dispatcher"
  php tools/ai/ai.php why
  php tools/ai/ai.php session-resume
  php tools/ai/ai.php commit-msg
  php tools/ai/ai.php pr-summary
  php tools/ai/ai.php logs
  php tools/ai/ai.php env-check
  php tools/ai/ai.php file-context tools/ai/ai.php
  php tools/ai/ai.php orphans
  php tools/ai/ai.php auto-fix --dry-run
  php tools/ai/ai.php impact --base main
  php tools/ai/ai.php ask "Which runtime adapter is in scope?" --options "copilot,opencode,both" --default both
  php tools/ai/ai.php estimate "add workflow-control command"
  php tools/ai/ai.php conflicts
  php tools/ai/ai.php find workflow
  php tools/ai/ai.php symbols aiRun
  php tools/ai/ai.php preflight
  php tools/ai/ai.php package-lock --check
  php tools/ai/ai.php package-verify
  php tools/ai/ai.php install --dry-run --mode sidecar-only
  php tools/ai/ai.php plan --targets copilot,opencode
  php tools/ai/ai.php packs --validate
  php tools/ai/ai.php toolchain --with repomix,scc --install-plan
  php tools/ai/ai.php run-script --list
  php tools/ai/ai.php install-docs --check
  php tools/ai/ai.php advisor --all
  php tools/ai/ai.php descriptors --list
  php tools/ai/ai.php descriptors --copy-out --name manifest.json --apply
  php tools/ai/ai.php placeholders --fail
  php tools/ai/ai.php version
TXT;

    fwrite(STDOUT, $usage . PHP_EOL);
}

try {
    $root = aiCliRepoRoot();
    $argv = $_SERVER['argv'] ?? [];
    $command = $argv[1] ?? 'help';
    $args = array_slice($argv, 2);

    switch ($command) {
        case 'help':
        case '--help':
        case '-h':
            aiUsage();
            exit(0);
        case 'list':
            exit(aiRunList($root));
        case 'freshness':
            exit(aiRunFreshness($root));
        case 'budget':
            exit(aiRunBudget($root, $args));
        case 'workflow':
            exit(aiRunWorkflow($root));
        case 'snapshot':
            exit(aiRunSnapshot($root));
        case 'diff-summary':
            exit(aiRunDiffSummary($root, $args));
        case 'risk':
            exit(aiRunRisk($root, $args));
        case 'verify':
            exit(aiRunVerify($root, $args));
        case 'next':
            exit(aiRunNext($root));
        case 'rebase-state':
            exit(aiRunRebaseState($root));
        case 'decision':
            exit(aiRunDecision($root, $args));
        case 'why':
            exit(aiRunWhy($root, $args));
        case 'session-resume':
            exit(aiRunSessionResume($root));
        case 'commit-msg':
            exit(aiRunCommitMsg($root));
        case 'pr-summary':
            exit(aiRunPrSummary($root));
        case 'logs':
            exit(aiRunLogs($root, $args));
        case 'env-check':
            exit(aiRunEnvCheck($root));
        case 'file-context':
            exit(aiRunFileContext($root, $args));
        case 'orphans':
            exit(aiRunOrphans($root));
        case 'auto-fix':
            exit(aiRunAutoFix($root, $args));
        case 'impact':
            exit(aiRunImpact($root, $args));
        case 'ask':
            exit(aiRunAsk($root, $args));
        case 'estimate':
            exit(aiRunEstimate($root, $args));
        case 'conflicts':
            exit(aiRunConflicts($root));
        case 'find':
            exit(aiRunFind($root, $args));
        case 'symbols':
            exit(aiRunSymbols($root, $args));
        case 'preflight':
            exit(aiRunPreflight($root));
        case 'doctor':
            exit(aiRunDoctor($root));
        case 'status':
            exit(aiRunStatus($root));
        case 'package-lock':
            exit(aiRunPackageLock($root, $args));
        case 'package-verify':
            exit(aiRunPackageVerify($root));
        case 'audit-instructions':
            exit(aiRunAuditInstructions($root));
        case 'adapter-plan':
            exit(aiRunAdapterPlan($root, $args));
        case 'plan':
            exit(aiRunAdapterPlan($root, $args));
        case 'install':
            exit(aiRunInstallWorkflow($root, $args));
        case 'upgrade':
            exit(aiRunUpgradeWorkflow($root, $args));
        case 'adapter-validate':
            exit(aiRunAdapterValidate($root));
        case 'rollback':
            exit(aiRunRollbackWorkflow($root, $args));
        case 'restore':
            exit(aiRunRestoreWorkflow($root, $args));
        case 'uninstall':
            exit(aiRunUninstallWorkflow($root, $args));
        case 'packs':
            exit(aiRunPacks($root, $args));
        case 'placeholders':
            exit(aiRunPlaceholders($root, $args));
        case 'hooks':
            exit(aiRunHooks($root, $args));
        case 'toolchain':
            exit(aiRunToolchain($root, $args));
        case 'run-script':
            exit(aiRunScriptCommand($root, $args));
        case 'install-docs':
            exit(aiRunInstallDocs($root, $args));
        case 'advisor':
            exit(aiRunAdvisor($root, $args));
        case 'descriptors':
            exit(aiRunDescriptors($root, $args));
        case 'version':
            exit(aiRunVersion($root));
        default:
            fwrite(STDERR, "Error: unknown command '{$command}'" . PHP_EOL . PHP_EOL);
            aiUsage();
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
