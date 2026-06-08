<?php

declare(strict_types=1);

require_once __DIR__ . '/profiles.php';
require_once __DIR__ . '/packs.php';
require_once __DIR__ . '/script-registry.php';
require_once __DIR__ . '/toolchain-registry.php';
require_once __DIR__ . '/generated-header.php';

function aiInstallerBuildInstalledInstructionsData(string $targetRoot, array $manifest): array
{
    $profile = (string) ($manifest['profile'] ?? 'unknown');
    $packs = is_array($manifest['packs'] ?? null) ? $manifest['packs'] : [];
    $files = is_array($manifest['files'] ?? null) ? array_keys($manifest['files']) : [];
    sort($files);

    $scripts = [];
    foreach (aiInstallerScriptRegistry() as $id => $entry) {
        if (in_array((string) ($entry['pack'] ?? ''), $packs, true)) {
            $scripts[] = [
                'id' => $id,
                'label' => (string) ($entry['label'] ?? $id),
                'path' => (string) ($entry['installed_path'] ?? ''),
                'required_tools' => $entry['required_tools'] ?? [],
            ];
        }
    }

    $toolReq = aiInstallerPackToolRequirements($packs);

    return [
        'installed_at' => (string) ($manifest['installed_at'] ?? 'unknown'),
        'profile' => $profile,
        'packs' => $packs,
        'files' => $files,
        'scripts' => $scripts,
        'required_tools' => $toolReq['required'] ?? [],
        'optional_tools' => $toolReq['optional'] ?? [],
        'commands' => [
            'preflight' => 'php tools/ai/ai.php preflight',
            'package_verify' => 'php tools/ai/ai.php package-verify',
            'adapter_plan' => 'php tools/ai/ai.php adapter-plan --profile ' . $profile,
            'install_dry_run' => 'php tools/ai/ai.php install --profile ' . $profile . ' --reinstall --dry-run',
            'install_backup' => 'php tools/ai/ai.php install --backup-only --apply --profile ' . $profile . ' --reinstall',
            'install_apply' => 'php tools/ai/ai.php install --apply --profile ' . $profile . ' --reinstall --backup <backup-id>',
            'verify' => 'php tools/ai/ai.php verify --json',
            'placeholders' => 'php tools/ai/ai.php placeholders --fail',
            'toolchain_check' => 'php tools/ai/ai.php toolchain --with repomix,scc --check',
            'toolchain_plan' => 'php tools/ai/ai.php toolchain --with repomix,scc --install-plan',
            'repo_tools_check' => 'bash scripts/ai/repo-tool-inventory.sh --check',
            'repo_tools_generate' => 'bash scripts/ai/repo-tool-inventory.sh',
            'mandatory_tools_install_dry_run' => 'bash scripts/ai/install-mandatory-tools.sh --dry-run',
            'scripts_list' => 'php tools/ai/ai.php run-script --list',
            'repomix_analyze' => 'bash scripts/ai/repomix-context-tree.sh analyze .',
            'advisor_all' => 'php tools/ai/ai.php advisor --all',
            'full_install_verify' => 'php tools/ai/verify-full-install.php',
        ],
    ];
}

function aiInstallerRenderInstalledInstructionsMarkdown(array $data): string
{
    $commands = is_array($data['commands'] ?? null) ? $data['commands'] : [];

    $md = aiInstallerGeneratedMarkdownHeader('installer instruction renderer') . "# Install Instructions\n\n";
    $md .= "- Installed at: `" . ($data['installed_at'] ?? 'unknown') . "`\n";
    $md .= "- Profile: `" . ($data['profile'] ?? 'unknown') . "`\n";
    $md .= "- Packs: `" . implode(', ', $data['packs'] ?? []) . "`\n\n";

    $md .= "## Step Chain\n\n";
    $md .= "1. Step 1 -> Preflight: `" . ($commands['preflight'] ?? 'php tools/ai/ai.php preflight') . "`\n";
    $md .= "   - Next: Step 2 (`package-verify`)\n";
    $md .= "2. Step 2 -> Package Verify: `" . ($commands['package_verify'] ?? 'php tools/ai/ai.php package-verify') . "`\n";
    $md .= "   - Next: Step 3 (`adapter-plan`)\n";
    $md .= "3. Step 3 -> Adapter Plan: `" . ($commands['adapter_plan'] ?? 'php tools/ai/ai.php adapter-plan') . "`\n";
    $md .= "   - Next: Step 4 (`install --dry-run`)\n";
    $md .= "4. Step 4 -> Install Dry-Run: `" . ($commands['install_dry_run'] ?? 'php tools/ai/ai.php install --dry-run') . "`\n";
    $md .= "   - Next: Step 5 (`install --backup-only`)\n";
    $md .= "5. Step 5 -> Backup: `" . ($commands['install_backup'] ?? 'php tools/ai/ai.php install --backup-only --apply') . "`\n";
    $md .= "   - Next: Step 6 (`install --apply --backup <id>`)\n";
    $md .= "6. Step 6 -> Apply: `" . ($commands['install_apply'] ?? 'php tools/ai/ai.php install --apply --backup <backup-id>') . "`\n";
    $md .= "   - Next: Step 7 (post-install verification sequence)\n\n";

    $md .= "## Before Install\n\n";
    $md .= "1. Run dry-run first.\n";
    $md .= "2. Confirm profile and optional packs.\n";
    $md .= "3. Check required tools for selected packs.\n\n";

    $md .= "## During Install\n\n";
    $md .= "- Dry-run: `" . ($commands['install_dry_run'] ?? ('php tools/ai/ai.php install --profile ' . ($data['profile'] ?? 'dual') . ' --dry-run')) . "`\n";
    $md .= "- Backup: `" . ($commands['install_backup'] ?? ('php tools/ai/ai.php install --backup-only --apply --profile ' . ($data['profile'] ?? 'dual'))) . "`\n";
    $md .= "- Apply: `" . ($commands['install_apply'] ?? ('php tools/ai/ai.php install --apply --profile ' . ($data['profile'] ?? 'dual') . ' --backup <backup-id>')) . "`\n\n";

    $profile = (string) ($data['profile'] ?? 'dual');
    $md .= "## Selective Updates\n\n";
    $md .= "- Runtime-only refresh: `php tools/ai/ai.php install --profile {$profile} --no-base --reinstall --dry-run`\n";
    $md .= "- Add scripts pack: `php tools/ai/ai.php install --profile {$profile} --with scripts-pack --reinstall --dry-run`\n";
    $md .= "- Add advisor pack: `php tools/ai/ai.php install --profile {$profile} --with advisor-pack --reinstall --dry-run`\n";
    $md .= "- Create merge-safe upgrade copies instead of skipping collisions: `php tools/ai/install-ai-kit.php --target /path/to/repo --profile {$profile} --upgrade-suffix=-upgrade`\n";
    $md .= "- Remove an included pack for comparison: `php tools/ai/ai.php install --profile {$profile} --without <pack-id> --reinstall --dry-run`\n";
    $md .= "- Run a helper after apply: `php tools/ai/ai.php install --profile {$profile} --reinstall --apply --run-after-install repomix-tree`\n\n";

    $md .= "## After Install\n\n";
    $md .= "- Verify: `" . ($commands['verify'] ?? 'php tools/ai/ai.php verify --json') . "`\n";
    $md .= "- Resolve placeholders: `" . ($commands['placeholders'] ?? 'php tools/ai/ai.php placeholders --fail') . "`\n";
    $md .= "- Toolchain check: `" . ($commands['toolchain_check'] ?? 'php tools/ai/ai.php toolchain --check') . "`\n";
    $md .= "- Required-tools inventory check: `" . ($commands['repo_tools_check'] ?? 'bash scripts/ai/repo-tool-inventory.sh --check') . "`\n";
    $md .= "- Required-tools inventory regenerate: `" . ($commands['repo_tools_generate'] ?? 'bash scripts/ai/repo-tool-inventory.sh') . "`\n";
    $md .= "- Mandatory-tools installer dry-run: `" . ($commands['mandatory_tools_install_dry_run'] ?? 'bash scripts/ai/install-mandatory-tools.sh --dry-run') . "`\n";
    $md .= "- Script list: `" . ($commands['scripts_list'] ?? 'php tools/ai/ai.php run-script --list') . "`\n\n";
    $md .= "- Repomix analyze: `" . ($commands['repomix_analyze'] ?? 'bash scripts/ai/repomix-context-tree.sh analyze .') . "`\n";
    $md .= "- Advisor analyze/fixes: `" . ($commands['advisor_all'] ?? 'php tools/ai/ai.php advisor --all') . "`\n";
    $md .= "- Full-install verifier: `" . ($commands['full_install_verify'] ?? 'php tools/ai/verify-full-install.php') . "`\n\n";
    $md .= "Advisor recommendations are strongest after a full OpenCode install and fresh Repomix analysis, because advisor consumes generated repository signals/context artifacts under `docs/ai/generated/`.\n\n";
    $md .= "OpenCode agent visibility note: agents in `.opencode/agents/` must not be marked `hidden: true`; use `mode: all` for agents you want in Tab rotation and `mode: subagent` for specialist agents that should appear via `@` mentions.\n\n";

    $md .= "## Completion Criteria\n\n";
    $md .= "- Run `" . ($commands['full_install_verify'] ?? 'php tools/ai/verify-full-install.php') . "` after the sequence above.\n";
    $md .= "- Completion is `full` only when install, validation, repomix analysis, and advisor checks all pass in order.\n";
    $md .= "- If status is not `full`, follow the script output for ordered remediation steps.\n\n";

    $md .= "For broader operator recipes across Copilot, OpenCode, docs, scripts, hooks, advisor, and Repomix helpers, read `docs/ai/install-order.md`.\n\n";

    $md .= "## Installed Scripts\n\n";
    if (($data['scripts'] ?? []) === []) {
        $md .= "- none\n";
    } else {
        foreach ($data['scripts'] as $script) {
            $md .= "- `" . ($script['id'] ?? '') . "` -> `" . ($script['path'] ?? '') . "`\n";
        }
    }

    $md .= "\n## Installed Files\n\n";
    foreach ($data['files'] ?? [] as $file) {
        $md .= "- `{$file}`\n";
    }

    return $md;
}

function aiInstallerBuildCatalogData(string $root): array
{
    $profiles = aiInstallerProfileDefinitions();
    $packs = aiInstallerPackRegistry();
    $scripts = aiInstallerScriptRegistry();
    $tools = aiInstallerToolchainRegistry();

    $packSummary = [];
    foreach ($packs as $id => $items) {
        $packSummary[] = ['id' => $id, 'item_count' => is_array($items) ? count($items) : 0];
    }

    return [
        'profiles' => $profiles,
        'packs' => $packSummary,
        'scripts' => $scripts,
        'toolchain' => $tools,
    ];
}

function aiInstallerRenderCatalogMarkdown(array $data): string
{
    $md = aiInstallerGeneratedMarkdownHeader('installer catalog renderer') . "# Install Catalog\n\n";
    $md .= "Deterministic catalog generated from installer registries.\n\n";
    $md .= "## Profiles\n\n";
    foreach (($data['profiles'] ?? []) as $id => $packs) {
        $md .= "- `{$id}`: `" . implode(', ', (array) $packs) . "`\n";
    }
    $md .= "\n## Packs\n\n";
    foreach (($data['packs'] ?? []) as $pack) {
        $md .= "- `" . ($pack['id'] ?? '') . "` (" . (int) ($pack['item_count'] ?? 0) . " items)\n";
    }
    $md .= "\n## Script IDs\n\n";
    foreach (($data['scripts'] ?? []) as $id => $script) {
        $md .= "- `{$id}` -> `" . (string) ($script['installed_path'] ?? '') . "`\n";
    }
    $md .= "\n## Toolchain\n\n";
    foreach (($data['toolchain'] ?? []) as $id => $tool) {
        $md .= "- `{$id}`";
        if (!empty($tool['safe_auto_install'])) {
            $md .= " (safe auto-install)";
        }
        $md .= "\n";
    }
    return $md;
}

function aiInstallerWriteInstallDocs(string $targetRoot, array $manifest): array
{
    $docsRoot = $targetRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';
    $generated = $docsRoot . DIRECTORY_SEPARATOR . 'generated';
    aiInstallerMkdir($docsRoot);
    aiInstallerMkdir($generated);

    $data = aiInstallerBuildInstalledInstructionsData($targetRoot, $manifest);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $md = aiInstallerRenderInstalledInstructionsMarkdown($data);

    $jsonPath = $generated . DIRECTORY_SEPARATOR . 'install-instructions.json';
    $mdPath = $generated . DIRECTORY_SEPARATOR . 'install-instructions.md';
    file_put_contents($jsonPath, $json);
    file_put_contents($mdPath, $md);

    return ['json' => $jsonPath, 'md' => $mdPath, 'data' => $data];
}

function aiInstallerWriteCatalogDocs(string $root): array
{
    $generated = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
    aiInstallerMkdir($generated);
    aiInstallerMkdir($root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'docs');

    $data = aiInstallerBuildCatalogData($root);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $md = aiInstallerRenderCatalogMarkdown($data);

    $jsonPath = $generated . DIRECTORY_SEPARATOR . 'install-catalog.json';
    $mdPath = $generated . DIRECTORY_SEPARATOR . 'install-catalog.md';
    $pkgMdPath = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'INSTALL-CATALOG.md';
    file_put_contents($jsonPath, $json);
    file_put_contents($mdPath, $md);
    file_put_contents($pkgMdPath, $md);

    return ['json' => $jsonPath, 'md' => $mdPath, 'package_md' => $pkgMdPath, 'data' => $data];
}

function aiInstallerCheckCatalogDocs(string $root): array
{
    $generated = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated';
    $jsonPath = $generated . DIRECTORY_SEPARATOR . 'install-catalog.json';
    $mdPath = $generated . DIRECTORY_SEPARATOR . 'install-catalog.md';
    $pkgMdPath = $root . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'INSTALL-CATALOG.md';

    $data = aiInstallerBuildCatalogData($root);
    $jsonExpected = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $mdExpected = aiInstallerRenderCatalogMarkdown($data);

    $drift = [];
    if (!is_file($jsonPath) || (string) file_get_contents($jsonPath) !== $jsonExpected) {
        $drift[] = 'docs/ai/generated/install-catalog.json';
    }
    if (!is_file($mdPath) || (string) file_get_contents($mdPath) !== $mdExpected) {
        $drift[] = 'docs/ai/generated/install-catalog.md';
    }
    if (!is_file($pkgMdPath) || (string) file_get_contents($pkgMdPath) !== $mdExpected) {
        $drift[] = 'packages/ai-universal-rules/docs/INSTALL-CATALOG.md';
    }

    return ['drift' => $drift, 'data' => $data];
}
