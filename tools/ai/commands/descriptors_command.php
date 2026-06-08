<?php

declare(strict_types=1);

/**
 * Phase 2: descriptor provenance copy-out.
 *
 * The kit relocates its package descriptors under `.ai/` (kit-manifest.json,
 * kit-manifest.yml, catalog.json, package-lock.ai.json) so they never collide with a
 * consumer project's own root files (see aiInstallerDescriptorProvenance in install/core.php).
 *
 * `descriptors` is an opt-in, read-mostly surface that lets a user:
 *   --list      see the relocated descriptors, their canonical root names, and copy-out safety
 *   --copy-out  copy a copyOutSafe descriptor back to its canonical root filename
 *
 * Copy-out is gated EXACTLY like the installer's foreign-file protection: a differing root
 * file is NEVER overwritten. Instead the incoming kit copy is snapshotted under
 * `.ai/conflicts/<ts>-descriptors/incoming/` and the command fails. `--dry-run` is the default;
 * writes require an explicit `--apply`.
 *
 * This command only reads Phase 1 provenance + the live `.ai/` files. It does not mutate the
 * installer planner, install manifest, or any Phase 1 logic.
 */

/**
 * Pure resolver: map a canonical root descriptor name (e.g. "manifest.json") to the live
 * `.ai/` source path plus its provenance.
 *
 * Resolution prefers the live `.ai/<descriptor>` files that exist on disk, and cross-checks
 * each against aiInstallerDescriptorProvenance (the Phase 1 source of truth). The optional
 * `.ai/local-manifest.json` is only used to enumerate which descriptors a prior install
 * recorded; it is never treated as a write allowlist.
 *
 * @return array{aiPath:string,absSource:string,canonicalRootName:string,copyOutSafe:bool,exists:bool}|null
 */
function aiDescriptorsResolveByCanonicalName(string $root, string $name): ?array
{
    foreach (aiDescriptorsKnownAiPaths() as $aiPath) {
        $prov = aiInstallerDescriptorProvenance($aiPath);
        if ($prov === null) {
            continue;
        }
        if ($prov['canonicalRootName'] !== $name) {
            continue;
        }
        $absSource = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $aiPath);

        return [
            'aiPath' => $aiPath,
            'absSource' => $absSource,
            'canonicalRootName' => $prov['canonicalRootName'],
            'copyOutSafe' => $prov['copyOutSafe'],
            'exists' => is_file($absSource),
        ];
    }

    return null;
}

/**
 * The four relocated kit descriptors, in canonical order. Each is cross-checked against
 * aiInstallerDescriptorProvenance, so this list never drifts from Phase 1 semantics.
 *
 * @return list<string>
 */
function aiDescriptorsKnownAiPaths(): array
{
    return [
        '.ai/kit-manifest.json',
        '.ai/kit-manifest.yml',
        '.ai/catalog.json',
        '.ai/package-lock.ai.json',
    ];
}

/**
 * Build the descriptor inventory rows from the live `.ai/local-manifest.json` plus the
 * on-disk `.ai/` descriptor files. Returns null when the local manifest is missing.
 *
 * @return list<array{aiPath:string,canonicalRootName:string,copyOutSafe:bool,aiCopyExists:bool,rootExists:bool,rootMatches:bool}>|null
 */
function aiDescriptorsListRows(string $root): ?array
{
    $localManifestPath = $root . DIRECTORY_SEPARATOR . '.ai' . DIRECTORY_SEPARATOR . 'local-manifest.json';
    if (!is_file($localManifestPath)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($localManifestPath), true);
    $files = is_array($decoded) && is_array($decoded['files'] ?? null) ? $decoded['files'] : [];

    $rows = [];
    foreach ($files as $aiPath => $meta) {
        if (!is_string($aiPath) || !is_array($meta) || !isset($meta['descriptor']) || !is_array($meta['descriptor'])) {
            continue;
        }
        // Cross-check against the Phase 1 source of truth rather than trusting the manifest copy.
        $prov = aiInstallerDescriptorProvenance($aiPath);
        if ($prov === null) {
            continue;
        }

        $absSource = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $aiPath);
        $absRoot = $root . DIRECTORY_SEPARATOR . $prov['canonicalRootName'];
        $rootExists = is_file($absRoot);
        $rootMatches = $rootExists && is_file($absSource) && aiInstallerPathsAreIdentical($absSource, $absRoot);

        $rows[] = [
            'aiPath' => $aiPath,
            'canonicalRootName' => $prov['canonicalRootName'],
            'copyOutSafe' => $prov['copyOutSafe'],
            'aiCopyExists' => is_file($absSource),
            'rootExists' => $rootExists,
            'rootMatches' => $rootMatches,
        ];
    }

    return $rows;
}

/**
 * Emit a `descriptors` JSON envelope to stdout (AI_OUTPUT=json) consistent with the repo's
 * documented schema/status/results shape, and return the exit code unchanged.
 *
 * @param array<string,mixed> $data
 */
function aiDescriptorsEmitJson(string $action, string $status, array $data, int $exitCode): int
{
    $envelope = [
        'schema' => 'ai.descriptors/v1',
        'status' => $status,
        'tool' => 'ai.php descriptors',
        'action' => $action,
        'data' => $data,
    ];
    $encoded = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    fwrite(STDOUT, ($encoded === false ? '{"status":"error"}' : $encoded) . PHP_EOL);

    return $exitCode;
}

function aiDescriptorsJsonEnabled(): bool
{
    return getenv('AI_OUTPUT') === 'json';
}

function aiRunDescriptors(string $root, array $args): int
{
    $sub = $args[0] ?? '';
    if ($sub === '' || $sub === 'list' || $sub === '--list') {
        return aiRunDescriptorsList($root);
    }
    if ($sub === '--copy-out' || $sub === 'copy-out') {
        return aiRunDescriptorsCopyOut($root, $args);
    }

    $message = "Error: unknown descriptors subcommand '{$sub}'. Use --list or --copy-out --name <canonicalRootName>.";
    if (aiDescriptorsJsonEnabled()) {
        return aiDescriptorsEmitJson('unknown', 'error', ['message' => $message], 1);
    }
    fwrite(STDERR, $message . PHP_EOL);
    return 1;
}

function aiRunDescriptorsList(string $root): int
{
    $rows = aiDescriptorsListRows($root);
    if ($rows === null) {
        $message = 'No .ai/local-manifest.json found at ' . $root
            . '. Run the installer first, or this target has no relocated kit descriptors.';
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('list', 'error', ['message' => $message, 'descriptors' => []], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    if (aiDescriptorsJsonEnabled()) {
        return aiDescriptorsEmitJson('list', 'ok', ['descriptors' => $rows], 0);
    }

    if ($rows === []) {
        fwrite(STDOUT, 'No relocated kit descriptors recorded in .ai/local-manifest.json.' . PHP_EOL);
        return 0;
    }

    fwrite(STDOUT, 'Relocated kit descriptors under .ai/ (canonical root name <- .ai path):' . PHP_EOL);
    foreach ($rows as $row) {
        $copyOut = $row['copyOutSafe'] ? 'copy-out: yes' : 'copy-out: no';
        if ($row['rootExists']) {
            $rootState = $row['rootMatches'] ? 'root: present (matches .ai copy)' : 'root: present (DIFFERS from .ai copy)';
        } else {
            $rootState = 'root: absent';
        }
        fwrite(STDOUT, sprintf(
            '  %-24s <- %-28s  %-14s  %s%s',
            $row['canonicalRootName'],
            $row['aiPath'],
            $copyOut,
            $rootState,
            PHP_EOL
        ));
    }

    return 0;
}

function aiRunDescriptorsCopyOut(string $root, array $args): int
{
    $apply = in_array('--apply', $args, true);
    $name = aiDescriptorsFlagValue($args, '--name');

    if ($name === null || $name === '') {
        $message = 'Error: --copy-out requires --name <canonicalRootName> (e.g. --name manifest.json).';
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'error', ['message' => $message], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $resolved = aiDescriptorsResolveByCanonicalName($root, $name);
    if ($resolved === null) {
        $message = "Error: unknown descriptor '{$name}'. Known canonical names: "
            . 'manifest.json, manifest.yml, catalog.json, package-lock.ai.json.';
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'error', ['message' => $message, 'name' => $name], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    if (!$resolved['copyOutSafe']) {
        $message = "Refusing to copy '{$name}' to root: it is not copy-out safe. The kit's "
            . 'descriptor resolver prefers the .ai/ copy, and a root copy can confuse the legacy '
            . 'root fallback in ai_catalog_lib.php. This descriptor is informational-only at root.';
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'refused', [
                'message' => $message,
                'name' => $name,
                'reason' => 'not_copy_out_safe',
            ], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $absSource = $resolved['absSource'];
    if (!is_file($absSource)) {
        $message = "Error: source .ai descriptor missing on disk: {$resolved['aiPath']}.";
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'error', ['message' => $message, 'name' => $name], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $absTarget = $root . DIRECTORY_SEPARATOR . $name;

    // Classify using the same gate semantics as the installer's foreign-file protection.
    if (!file_exists($absTarget)) {
        return aiDescriptorsCopyOutCreate($root, $name, $absSource, $absTarget, $apply);
    }

    if (aiInstallerPathsAreIdentical($absSource, $absTarget)) {
        $message = "Nothing to do: {$name} already exists at root and is byte-identical to {$resolved['aiPath']}.";
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'skip_identical', [
                'message' => $message,
                'name' => $name,
                'target' => $name,
            ], 0);
        }
        fwrite(STDOUT, $message . PHP_EOL);
        return 0;
    }

    // CONFLICT_FOREIGN: a differing root file. NEVER overwrite it.
    return aiDescriptorsCopyOutConflict($root, $name, $absSource, $apply);
}

/**
 * CREATE: target missing. dry-run (default) describes only; --apply copies and verifies sha256.
 */
function aiDescriptorsCopyOutCreate(string $root, string $name, string $absSource, string $absTarget, bool $apply): int
{
    if (!$apply) {
        $message = "Dry-run: would create {$name} at root from .ai copy (no file written). Re-run with --apply to write.";
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'dry_run', [
                'message' => $message,
                'name' => $name,
                'plannedAction' => 'CREATE',
            ], 0);
        }
        fwrite(STDOUT, $message . PHP_EOL);
        return 0;
    }

    if (!copy($absSource, $absTarget)) {
        $message = "Error: failed to copy {$name} to root.";
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'error', ['message' => $message, 'name' => $name], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $sourceHash = hash_file('sha256', $absSource);
    $targetHash = hash_file('sha256', $absTarget);
    if ($sourceHash === false || $targetHash === false || $sourceHash !== $targetHash) {
        // Never leave a corrupt/partial root file behind on a failed verify.
        @unlink($absTarget);
        $message = "Error: copy verification failed for {$name} (sha256 mismatch after write); partial file removed.";
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'error', ['message' => $message, 'name' => $name], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $message = "Created {$name} at root (sha256 verified against the .ai copy).";
    if (aiDescriptorsJsonEnabled()) {
        return aiDescriptorsEmitJson('copy-out', 'created', [
            'message' => $message,
            'name' => $name,
            'target' => $name,
            'sha256' => $targetHash,
        ], 0);
    }
    fwrite(STDOUT, $message . PHP_EOL);
    return 0;
}

/**
 * CONFLICT_FOREIGN: a differing root file exists. The root file is NEVER touched. Under --apply
 * we snapshot the incoming kit copy under `.ai/conflicts/<ts>-descriptors/incoming/`; dry-run
 * describes only. Always returns non-zero.
 */
function aiDescriptorsCopyOutConflict(string $root, string $name, string $absSource, bool $apply): int
{
    $stamp = gmdate('Ymd\THis\Z');
    $incomingRel = aiInstallerPrivateConflictRel('descriptors', 'incoming', $stamp) . '/' . $name;

    if (!$apply) {
        $message = "Dry-run: {$name} already exists at root and DIFFERS from the .ai copy. The root file "
            . "would be preserved; the incoming kit copy would be snapshotted at {$incomingRel}. "
            . 'Re-run with --apply to write the incoming snapshot.';
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'conflict_dry_run', [
                'message' => $message,
                'name' => $name,
                'plannedAction' => 'CONFLICT_FOREIGN',
                'incomingPath' => $incomingRel,
            ], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $incomingDir = aiInstallerPrivateConflictDir($root, 'descriptors', 'incoming', $stamp);
    aiInstallerMkdir($incomingDir, aiInstallerPrivateDirMode());
    $incomingAbs = $incomingDir . DIRECTORY_SEPARATOR . $name;

    if (!copy($absSource, $incomingAbs)) {
        $message = "Error: failed to write incoming conflict snapshot for {$name}.";
        if (aiDescriptorsJsonEnabled()) {
            return aiDescriptorsEmitJson('copy-out', 'error', ['message' => $message, 'name' => $name], 1);
        }
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }

    $message = "Conflict: {$name} already exists at root and DIFFERS from the .ai copy. The root file "
        . "was NOT overwritten. The incoming kit copy was snapshotted at {$incomingRel}.";
    if (aiDescriptorsJsonEnabled()) {
        return aiDescriptorsEmitJson('copy-out', 'conflict_foreign', [
            'message' => $message,
            'name' => $name,
            'incomingPath' => $incomingRel,
        ], 1);
    }
    fwrite(STDERR, $message . PHP_EOL);
    return 1;
}

/** Read the value following a `--flag` in an argv-style array, or null when absent. */
function aiDescriptorsFlagValue(array $args, string $flag): ?string
{
    $count = count($args);
    for ($i = 0; $i < $count; $i++) {
        if ($args[$i] === $flag) {
            return isset($args[$i + 1]) ? (string) $args[$i + 1] : null;
        }
    }

    return null;
}
