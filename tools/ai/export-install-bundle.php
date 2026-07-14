<?php

declare(strict_types=1);

/**
 * Export a standalone, offline-runnable copy of the installer (this kit's own install
 * mechanism plus the source templates it reads from) into a user-specified path.
 *
 * This is distinct from export-ai-universal-rules.php, which packages a rendered
 * "starter profile" snapshot for release/distribution. This script instead vendors the
 * *installer itself* — install-ai-kit.sh, tools/ai/install-ai-kit.php, tools/ai/ai.php,
 * their tools/ai/install/** and tools/ai/commands/** dependencies, and every `source`
 * path the pack registry references — so the bundle can run
 * `install-ai-kit.sh --target <any-other-repo>` without needing the rest of this
 * development repo (tests/, docs/tickets/, v0.5-upgrade/, etc).
 *
 * Usage:
 *   php tools/ai/export-install-bundle.php --target <path> [--profile <name>] [--dry-run|--apply]
 *
 * The bundle always includes every pack's referenced source templates (not just one
 * profile's) — tools/ai/validate-install-surface.php validates the *entire* source
 * catalog regardless of which profile a later install picks, so a bundle scoped to one
 * profile's files alone would always fail that check. --profile instead selects which
 * profile the bundle's own README/manifest recommends as the default for the later
 * `install-ai-kit.sh --target <other-repo> --profile <name>` step; it does not reduce
 * what gets copied. Defaults to `full-governance` (this kit's broadest shipped profile)
 * when omitted.
 *
 * Default is --dry-run (preview only, prints the file list and counts). Pass --apply to
 * actually copy files. Never deletes or overwrites files outside the given --target path.
 */

require_once __DIR__ . '/install/registry.php';
require_once __DIR__ . '/install/profiles.php';

/**
 * Core installer mechanism paths always included regardless of --profile, so the
 * exported bundle can run its own install command standalone. Every flat tools/ai/*.php
 * file is included (validators, generators, and the ai.php dispatcher all live there and
 * install-ai-kit.sh invokes several of them directly — validate-install-surface.php,
 * validate-ai-config.php, validate-ai-catalog.php, ai.php advisor/descriptors), plus the
 * subdirectories those files require_once at runtime.
 *
 * @param string $root
 * @return list<string>
 */
function exportInstallBundleCoreMechanismPaths(string $root): array
{
    $paths = [
        'install-ai-kit.sh',
        'tools/ai/install-copilot-kit.sh',
        'tools/ai/install-opencode-kit.sh',
        'tools/ai/install-claude-kit.sh',
        'tools/ai/install',
        'tools/ai/commands',
        'tools/ai/validation',
        'tools/ai/advisor',
        'tools/ai/rules',
        'tools/ai/sh-introspect',
        'schemas/ai',
    ];

    foreach (glob($root . '/tools/ai/*.php') ?: [] as $absolute) {
        $paths[] = 'tools/ai/' . basename($absolute);
    }

    return $paths;
}

/**
 * Collect every distinct `source` path referenced by the given pack names, plus every
 * pack's own dir/file entries verbatim (some packs reference repo-root paths like
 * scripts/ai/** directly rather than under packages/ai-universal-rules/templates, since
 * this repo is a self-installed source kit — see docs/ai/maintainer-guide.md).
 *
 * @param list<string> $packNames
 * @param array<string,list<array<string,mixed>>> $registry
 * @return list<string>
 */
function exportInstallBundleCollectPackSources(array $packNames, array $registry): array
{
    $sources = [];

    foreach ($packNames as $packName) {
        foreach ($registry[$packName] ?? [] as $item) {
            $source = (string) ($item['source'] ?? '');
            if ($source !== '') {
                $sources[$source] = true;
            }
        }
    }

    return array_keys($sources);
}

/**
 * Recursively copy a file or directory from $root/$relativeSource into
 * $target/$relativeSource, creating parent directories as needed. Returns the count of
 * files copied (0 if the source does not exist — reported as a warning by the caller).
 */
function exportInstallBundleCopyPath(string $root, string $target, string $relativeSource, bool $apply): int
{
    $sourceAbsolute = $root . '/' . $relativeSource;
    if (!file_exists($sourceAbsolute)) {
        return 0;
    }

    if (is_file($sourceAbsolute)) {
        if ($apply) {
            $destination = $target . '/' . $relativeSource;
            @mkdir(dirname($destination), 0777, true);
            copy($sourceAbsolute, $destination);
        }

        return 1;
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceAbsolute, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $relativeFile = $relativeSource . '/' . substr(
            str_replace('\\', '/', $item->getPathname()),
            strlen(str_replace('\\', '/', $sourceAbsolute)) + 1
        );

        if ($apply) {
            $destination = $target . '/' . $relativeFile;
            @mkdir(dirname($destination), 0777, true);
            copy($item->getPathname(), $destination);
        }

        $count++;
    }

    return $count;
}

function exportInstallBundlePrintHelp(): void
{
    fwrite(STDOUT, <<<'TXT'
Usage:
  php tools/ai/export-install-bundle.php --target <path> [options]

Options:
  --target <path>   Destination directory for the standalone installer bundle (required).
  --profile <name>  Record which profile the bundle recommends by default (minimal|copilot|
                     opencode|claude|dual|guarded|accelerated|full-governance|docs-reference|
                     basic|standard|creator|full|agents-only). Defaults to full-governance.
                     Does not change which files are bundled — every pack's source
                     templates are always included so validate-install-surface.php passes
                     regardless of which profile the bundle is later installed with.
  --dry-run         Print the planned file list and counts only (default).
  --apply           Actually copy files into --target.
  --help            Show this help.

Examples:
  php tools/ai/export-install-bundle.php --target /tmp/ai-kit-bundle --profile full-governance --dry-run
  php tools/ai/export-install-bundle.php --target /tmp/ai-kit-bundle --profile full-governance --apply

After --apply, run the bundle standalone:
  bash /tmp/ai-kit-bundle/install-ai-kit.sh --target /path/to/other-project --profile full-governance
TXT
    );
    fwrite(STDOUT, PHP_EOL);
}

function exportInstallBundleMain(array $argv): int
{
    if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
        exportInstallBundlePrintHelp();

        return 0;
    }

    $root = realpath(__DIR__ . '/../..');
    if ($root === false) {
        fwrite(STDERR, "ERROR: cannot resolve repo root\n");

        return 1;
    }

    $target = null;
    $profile = null;
    $apply = in_array('--apply', $argv, true);

    foreach ($argv as $index => $argument) {
        if ($argument === '--target') {
            $target = $argv[$index + 1] ?? null;
        } elseif (str_starts_with($argument, '--target=')) {
            $target = substr($argument, strlen('--target='));
        } elseif ($argument === '--profile') {
            $profile = $argv[$index + 1] ?? null;
        } elseif (str_starts_with($argument, '--profile=')) {
            $profile = substr($argument, strlen('--profile='));
        }
    }

    if ($target === null || $target === '') {
        fwrite(STDERR, "ERROR: --target <path> is required\n");
        exportInstallBundlePrintHelp();

        return 1;
    }

    $registry = aiInstallerPackRegistry();
    $profileDefs = aiInstallerProfileDefinitions();

    if ($profile !== null && !isset($profileDefs[$profile])) {
        fwrite(STDERR, "ERROR: unknown --profile '{$profile}'. Known profiles: " . implode(', ', array_keys($profileDefs)) . "\n");

        return 1;
    }

    $defaultProfile = $profile ?? 'full-governance';

    // Always bundle every pack's referenced sources (see file header for why --profile
    // must not scope this down): validate-install-surface.php checks the whole catalog.
    $packNames = array_keys($registry);
    $sources = exportInstallBundleCollectPackSources($packNames, $registry);
    $allPaths = array_values(array_unique(array_merge(exportInstallBundleCoreMechanismPaths($root), $sources)));
    sort($allPaths);

    // Always bring the package descriptors so a bundled --source points at a complete tree.
    $allPaths[] = 'packages/ai-universal-rules/manifest.json';
    $allPaths[] = 'packages/ai-universal-rules/catalog.json';
    $allPaths[] = 'packages/ai-universal-rules/package-lock.ai.json';
    $allPaths[] = 'packages/ai-universal-rules/templates';
    $allPaths[] = 'packages/ai-universal-rules/docs';
    $allPaths[] = 'packages/ai-universal-rules/policies';
    $allPaths = array_values(array_unique($allPaths));
    sort($allPaths);

    $targetAbsolute = str_starts_with($target, '/') ? $target : $root . '/' . $target;

    $totalFiles = 0;
    $missing = [];

    if ($apply) {
        @mkdir($targetAbsolute, 0777, true);
    }

    foreach ($allPaths as $relativePath) {
        $count = exportInstallBundleCopyPath($root, $targetAbsolute, $relativePath, $apply);
        if ($count === 0 && !file_exists($root . '/' . $relativePath)) {
            $missing[] = $relativePath;
            continue;
        }
        $totalFiles += $count;
    }

    foreach ($missing as $relativePath) {
        fwrite(STDERR, "WARN: source path not found, skipped: {$relativePath}\n");
    }

    if ($apply) {
        $manifest = [
            'schema_version' => 1,
            'generated_at' => gmdate('c'),
            'based_on_source' => $root,
            'default_profile' => $defaultProfile,
            'pack_count' => count($packNames),
            'file_count' => $totalFiles,
        ];
        file_put_contents(
            $targetAbsolute . '/.bundle-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
        fwrite(STDOUT, "OK: exported {$totalFiles} files (default profile: {$defaultProfile}) to {$targetAbsolute}\n");
        fwrite(STDOUT, "Next: bash {$targetAbsolute}/install-ai-kit.sh --target /path/to/other-project --profile {$defaultProfile}\n");
    } else {
        fwrite(STDOUT, "DRY-RUN: would export {$totalFiles} files (default profile: {$defaultProfile}) to {$targetAbsolute}\n");
        fwrite(STDOUT, "Rerun with --apply to write. --profile only sets the bundle's recorded default; every pack's files are always included.\n");
    }

    return 0;
}

try {
    exit(exportInstallBundleMain($argv));
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
