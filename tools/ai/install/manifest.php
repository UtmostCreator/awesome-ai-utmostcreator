<?php

declare(strict_types=1);

require_once __DIR__ . '/../repo-tool-inventory.php';

function aiInstallerCanonicalManifestPath(string $targetRoot): string
{
    return $targetRoot . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';
}

function aiInstallerDerivedManifestPath(string $targetRoot): string
{
    return $targetRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . 'install-manifest.json';
}

function aiInstallerWriteManifest(string $targetRoot, array $manifest): void
{
    $canonical = aiInstallerCanonicalManifestPath($targetRoot);
    $derived = aiInstallerDerivedManifestPath($targetRoot);

    aiInstallerMkdir(dirname($canonical));
    aiInstallerMkdir(dirname($derived));

    aiInstallerWriteSetupDocs($targetRoot, $manifest);

    $manifest = aiInstallerRefreshGeneratedManifestEntries($targetRoot, $manifest);
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    file_put_contents($canonical, $json);
    file_put_contents($derived, $json);
}

function aiInstallerBuildManifest(array $config, array $packs, array $plan): array
{
    $existingManifest = aiInstallerReadExistingManifest($config['targetRoot']);
    $files = [];
    foreach ($plan as $item) {
        $action = (string) ($item['action'] ?? '');
        if (in_array($action, ['SKIP_EXISTING_UNMANAGED', 'SKIP_PROTECTED_CORE', 'CONFLICT_FOREIGN'], true)) {
            continue;
        }

        $rel = $item['target'];
        $abs = $config['targetRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $hash = aiInstallerHashPath($abs);
        $sourceAbs = $config['sourceRoot'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $item['source']);
        $sourceHash = aiInstallerHashPath($sourceAbs);
        $mergeStrategy = $item['merge_strategy'] ?? 'skip-if-exists';
        $pack = (string) ($item['pack'] ?? '');
        $files[$rel] = [
            'pack' => $item['pack'],
            'source' => $item['source'],
            'source_hash' => $sourceHash,
            'installed_hash' => $hash,
            'managed' => true,
            'merge_strategy' => $mergeStrategy,
            'required' => true,
            'ownership' => aiInstallerResolveOwnership($item, $mergeStrategy),
            'component' => $pack,
            'runtimes' => aiInstallerResolveRuntimes($pack),
        ];
    }

    $mergedPacks = array_values(array_unique($packs));

    $pendingConfiguration = [
        'Fill docs/ai/project-context.md',
        'Run placeholder check via php tools/ai/ai.php placeholders',
    ];
    if (is_array($existingManifest['pending_configuration'] ?? null)) {
        $pendingConfiguration = array_values(array_unique(array_merge($existingManifest['pending_configuration'], $pendingConfiguration)));
    }

    $package = is_array($existingManifest['package'] ?? null) ? $existingManifest['package'] : [
        'name' => 'ai-universal-rules',
        'distribution' => 'git-tag',
        'source_repository' => 'UtmostCreator/app-configs',
        'source_remote' => 'origin',
        'source_ref' => 'unknown',
        'source_commit' => 'unknown',
        'installed_version' => 'unknown',
    ];

    return [
        'schema_version' => 1,
        'installer_version' => '0.2.0',
        'installed_at' => (string) ($existingManifest['installed_at'] ?? gmdate('c')),
        'updated_at' => gmdate('c'),
        'profile' => $config['profile'],
        'package' => $package,
        'packs' => $mergedPacks,
        'files' => $files,
        'pending_configuration' => $pendingConfiguration,
    ];
}

/**
 * Resolve the ownership class for an installed file.
 *
 * Ownership classes drive upgrade/reinstall/uninstall behaviour:
 *  - owned:    kit-managed; overwritten freely on upgrade (checksum-tracked).
 *  - template: installed once, then user-owned; never overwritten on upgrade.
 *  - rendered: regenerated each install/upgrade from project values.
 *
 * Derivation rule (no per-entry annotation required): an explicit `ownership` on the
 * registry entry always wins; otherwise `skip-if-exists` files are treated as `template`
 * (the installer already preserves them when present) and everything else is `owned`.
 *
 * @param array<string,mixed> $item Registry/plan entry.
 */
function aiInstallerResolveOwnership(array $item, string $mergeStrategy): string
{
    $explicit = $item['ownership'] ?? null;
    if (is_string($explicit) && in_array($explicit, ['owned', 'template', 'rendered'], true)) {
        return $explicit;
    }

    return $mergeStrategy === 'skip-if-exists' ? 'template' : 'owned';
}

/**
 * Resolve which runtimes an installed file belongs to, derived from its pack/component.
 *
 * @return list<string>
 */
function aiInstallerResolveRuntimes(string $pack): array
{
    if (str_contains($pack, 'copilot')) {
        return ['github-copilot'];
    }
    if (str_contains($pack, 'opencode')) {
        return ['opencode'];
    }

    return ['both'];
}

function aiInstallerReadExistingManifest(string $targetRoot): array
{
    $path = aiInstallerCanonicalManifestPath($targetRoot);
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function aiInstallerHashPath(string $path): string
{
    if (is_file($path)) {
        return 'sha256:' . hash_file('sha256', $path);
    }
    if (!is_dir($path)) {
        return 'unknown';
    }

    $parts = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $abs = $file->getPathname();
        $rel = str_replace('\\', '/', substr($abs, strlen($path) + 1));
        $parts[] = $rel . ':' . hash_file('sha256', $abs);
    }
    sort($parts);
    return 'sha256:' . hash('sha256', implode("\n", $parts));
}

function aiInstallerWriteSetupDocs(string $targetRoot, array $manifest): void
{
    $docsRoot = $targetRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';
    $generated = $docsRoot . DIRECTORY_SEPARATOR . 'generated';
    aiInstallerMkdir($docsRoot);
    aiInstallerMkdir($generated);

    $profile = (string) ($manifest['profile'] ?? 'unknown');
    $installedAt = (string) ($manifest['installed_at'] ?? 'unknown');
    $packs = is_array($manifest['packs'] ?? null) ? $manifest['packs'] : [];
    $files = is_array($manifest['files'] ?? null) ? array_keys($manifest['files']) : [];

    $setup = "# AI Setup\n\n";
    $setup .= "- Installed at: `{$installedAt}`\n";
    $setup .= "- Profile: `{$profile}`\n";
    $setup .= "- Package: `ai-universal-rules`\n\n";
    $setup .= "## Installed Packs\n\n";
    foreach ($packs as $pack) {
        $setup .= "- `{$pack}`\n";
    }
    $setup .= "\n## Next Steps\n\n";
    $setup .= "1. Update `docs/ai/project-context.md` with project facts.\n";
    $setup .= "2. Run `php tools/ai/ai.php placeholders --fail`.\n";
    $setup .= "3. Run `php tools/ai/ai.php verify`.\n";
    $setup .= "4. Read `docs/ai/POST-INSTALL.md` for pack-specific usage guidance.\n";

    $installedFiles = "# Installed Files\n\n";
    foreach ($files as $file) {
        $installedFiles .= "- `{$file}`\n";
    }

    $projectConfig = "# Project Configuration\n\n";
    $projectConfig .= "- Fill durable project facts in `docs/ai/project-context.md`.\n";
    $projectConfig .= "- Set real build/test/verify commands for this repository.\n";
    $projectConfig .= "- Confirm active and inactive paths.\n";
    $projectConfig .= "- Confirm runtime surface choices and optional packs.\n";

    $availablePacks = "# Available Packs\n\n";
    $availablePacks .= "Installed profile: `{$profile}`\n\n";
    $availablePacks .= "## Installed\n\n";
    foreach ($packs as $pack) {
        $availablePacks .= "- `{$pack}`\n";
    }
    $availablePacks .= "\n## Add Later\n\n";
    $availablePacks .= "- `docs-reference-pack`\n";
    $availablePacks .= "- `delivery-pack`\n";
    $availablePacks .= "- `optional-agents-pack`\n";
    $availablePacks .= "- `optional-prompts-pack`\n";

    $postInstall = "# Post Install\n\n";
    $postInstall .= "- Profile: `{$profile}`\n";
    $postInstall .= "- Packs: `" . implode(', ', array_map(static fn($v): string => (string) $v, $packs)) . "`\n";
    $postInstall .= "\n## How To Use Installed Assets\n\n";
    if (in_array('adapter-copilot', $packs, true)) {
        $postInstall .= "- Copilot assets: `.github/copilot-instructions.md`, `.github/instructions/`, `.github/agents/`, `.github/prompts/`.\n";
    }
    if (in_array('adapter-opencode', $packs, true)) {
        $postInstall .= "- OpenCode assets: `.opencode/agents/`, `.opencode/commands/`, `.opencode/skills/`.\n";
    }
    if (in_array('scripts-pack', $packs, true)) {
        $postInstall .= "- Scripts installed under `scripts/ai/` for search, context packing, verify, rollback, and investigation flows.\n";
        $postInstall .= "- Required tools: `bash`, `git`, `jq`, `rg`, `repomix`, `scc`.\n";
        $postInstall .= "- Optional tools: `fd`, `gh`, `fzf`, `bat`, `delta`, `yq`, `shellcheck`, `semgrep`, `ast-grep`.\n";
    }
    $postInstall .= "\n## Commands\n\n";
    $postInstall .= "- Post-install setup: `post-install-setup` (OpenCode command) or `post-install-setup` (workflow / skill on supported surfaces)\n";
    $postInstall .= "- Verify: `php tools/ai/ai.php verify`\n";
    $postInstall .= "- Strict verify: `php tools/ai/ai.php verify --strict`\n";
    $postInstall .= "- Placeholders: `php tools/ai/ai.php placeholders --fail`\n";
    $postInstall .= "- Upgrade preview: `php tools/ai/ai.php upgrade --dry-run`\n";
    $postInstall .= "- Rollback: `php tools/ai/ai.php rollback --backup <backup-id> --apply`\n";
    $postInstall .= "- Specific-file rollback: `php tools/ai/ai.php rollback --backup <backup-id> --only path/to/file --apply`\n";
    $postInstall .= "\n## Hook Wiring\n\n";
    $postInstall .= "- Hook scripts are installed when `hooks-pack` is selected; wiring remains explicit.\n";
    $postInstall .= "- Wire hooks with: `php tools/ai/ai.php hooks install --driver husky|lefthook|native`.\n";
    $postInstall .= "\n## Project Configuration Checklist\n\n";
    $postInstall .= "- Fill project facts and commands in `docs/ai/project-context.md`.\n";
    $postInstall .= "- Start with `post-install-setup` if you want a guided setup pass after install.\n";
    $postInstall .= "- Confirm risk areas and approval-required changes.\n";
    $postInstall .= "- Confirm active/inactive paths and runtime targets.\n";

    $summary = "# Install Summary\n\n";
    $summary .= "- Profile: `{$profile}`\n";
    $summary .= "- Packs: `" . implode(', ', array_map(static fn($v): string => (string) $v, $packs)) . "`\n";
    $summary .= "- Managed files: `" . count($files) . "`\n";

    file_put_contents($docsRoot . DIRECTORY_SEPARATOR . 'SETUP.md', $setup);
    file_put_contents($docsRoot . DIRECTORY_SEPARATOR . 'POST-INSTALL.md', $postInstall);
    file_put_contents($docsRoot . DIRECTORY_SEPARATOR . 'installed-files.md', $installedFiles);
    file_put_contents($docsRoot . DIRECTORY_SEPARATOR . 'project-configuration.md', $projectConfig);
    file_put_contents($docsRoot . DIRECTORY_SEPARATOR . 'available-packs.md', $availablePacks);
    file_put_contents($generated . DIRECTORY_SEPARATOR . 'install-summary.md', $summary);

    repoToolInventoryWriteOutput($targetRoot, 'docs/ai/repo-required-tools.md');

    if (function_exists('aiInstallerWriteInstallDocs')) {
        aiInstallerWriteInstallDocs($targetRoot, $manifest);
    }
}

function aiInstallerRefreshGeneratedManifestEntries(string $targetRoot, array $manifest): array
{
    $refreshable = [
        'docs/ai/POST-INSTALL.md',
        'docs/ai/available-packs.md',
        'docs/ai/repo-required-tools.md',
    ];

    foreach ($refreshable as $relativePath) {
        if (!isset($manifest['files'][$relativePath]) || !is_array($manifest['files'][$relativePath])) {
            continue;
        }

        $absolutePath = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $manifest['files'][$relativePath]['installed_hash'] = aiInstallerHashPath($absolutePath);
    }

    return $manifest;
}
