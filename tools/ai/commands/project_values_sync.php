<?php

declare(strict_types=1);

/**
 * project-values-sync: one-click propagation of resolved `.ai/project.yml` facts
 * into already-rendered (installed) files.
 *
 * Why this exists: `install`/`upgrade --dry-run` only act on template-source-hash
 * changes or missing files; `placeholders --apply` only replaces literal `<TOKEN>`
 * markers still present in a file. Once a file has been rendered once, its known
 * project-fact tokens (`<PRIMARY_LANGUAGE>` etc.) are already substituted to
 * literal text (even 'unknown'), so neither lever re-syncs a value-only
 * `.ai/project.yml` edit into already-installed files. This command closes that
 * gap for a fixed, safe allow-list of label-anchored lines (never touches
 * `packages/**` template sources, which must keep `<TOKEN>` syntax so every other
 * project that installs this kit still gets its own placeholders to fill).
 *
 * Safety model:
 * - Scan roots are the SAME as the existing placeholders scanner
 *   (['AGENTS.md', 'docs/ai', '.github', '.opencode']) so template sources under
 *   packages/ai-universal-rules/templates/** are structurally out of reach.
 * - Only lines matching a known literal label prefix + backtick-quoted value are
 *   touched (line-scoped, not whole-file overwrite), so unrelated content/user
 *   edits elsewhere in a rendered file are preserved.
 * - A field is only synced when its `.ai/project.yml` value is set and not the
 *   literal string 'unknown' (mirrors the existing P4-a override-when-set rule).
 * - Idempotent: running --apply twice in a row produces zero changes the second
 *   time once values match.
 */

/** @return array<string,list<string>> field => list of literal line-label prefixes (before the backtick value) */
function aiProjectValuesSyncLabelRegistry(): array
{
    return [
        'primaryLanguage' => ['- Primary language: `'],
        'primaryRuntime' => ['- Primary runtime: `'],
        'primaryVerifyCommand' => ['- Primary verification command: `', '- Main verification command: `'],
        'primaryBuildCommand' => ['- Primary build command: `', '- Main build command: `'],
        'primaryTestCommand' => ['- Primary test command: `', '- Main test command: `'],
        'packageManager' => ['- Package manager: `'],
    ];
}

/** @return list<string> scan roots — identical to the placeholders scanner's roots for parity/safety */
function aiProjectValuesSyncScanRoots(): array
{
    return ['AGENTS.md', 'docs/ai', '.github', '.opencode'];
}

/**
 * Scan (and optionally apply) label-anchored project-value line sync.
 *
 * @return array{
 *   fields_synced: list<string>,
 *   files_checked: int,
 *   mismatches: list<array{path:string,field:string,label:string,current:string,expected:string}>,
 *   files_changed: list<string>,
 *   applied: bool
 * }
 */
function aiProjectValuesSyncScan(string $root, bool $apply): array
{
    $values = aiInstallerLoadProjectValues($root, basename($root));
    $registry = aiProjectValuesSyncLabelRegistry();

    /** @var array<string,string> $fieldValues */
    $fieldValues = [];
    foreach (array_keys($registry) as $field) {
        $value = trim((string) ($values[$field] ?? ''));
        if ($value === '' || $value === 'unknown') {
            continue;
        }
        $fieldValues[$field] = $value;
    }

    $mismatches = [];
    $filesChanged = [];
    $filesChecked = 0;

    foreach (aiProjectValuesSyncScanRoots() as $rel) {
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            $filesChecked++;
            aiProjectValuesSyncFile($abs, $rel, $fieldValues, $registry, $apply, $mismatches, $filesChanged);
            continue;
        }
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }
            $fileRel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (aiInstallerShouldSkipPlaceholderScanPath($fileRel)) {
                continue;
            }
            $filesChecked++;
            aiProjectValuesSyncFile($file->getPathname(), $fileRel, $fieldValues, $registry, $apply, $mismatches, $filesChanged);
        }
    }

    return [
        'fields_synced' => array_keys($fieldValues),
        'files_checked' => $filesChecked,
        'mismatches' => $mismatches,
        'files_changed' => array_values(array_unique($filesChanged)),
        'applied' => $apply,
    ];
}

/**
 * @param array<string,string> $fieldValues
 * @param array<string,list<string>> $registry
 * @param list<array{path:string,field:string,label:string,current:string,expected:string}> $mismatches
 * @param list<string> $filesChanged
 */
function aiProjectValuesSyncFile(
    string $absPath,
    string $relPath,
    array $fieldValues,
    array $registry,
    bool $apply,
    array &$mismatches,
    array &$filesChanged
): void {
    if ($fieldValues === []) {
        return;
    }
    $content = (string) file_get_contents($absPath);
    $lines = explode("\n", $content);
    $changed = false;

    foreach ($lines as $i => $line) {
        // Tolerate CRLF-terminated content (e.g. a locally-edited copy on Windows):
        // match against the line with any trailing \r stripped, then re-append it
        // on write so the file's original line ending is preserved either way.
        $hasCr = str_ends_with($line, "\r");
        $matchLine = $hasCr ? substr($line, 0, -1) : $line;

        foreach ($registry as $field => $labels) {
            if (!isset($fieldValues[$field])) {
                continue;
            }
            $expectedValue = $fieldValues[$field];
            foreach ($labels as $label) {
                if (!str_starts_with($matchLine, $label) || !str_ends_with($matchLine, '`')) {
                    continue;
                }
                $current = substr($matchLine, strlen($label), -1);
                // Never touch an unresolved template placeholder token — that means this
                // file is a template source or an unrendered stub, not an installed copy.
                if ($current !== '' && $current[0] === '<' && str_ends_with($current, '>')) {
                    continue;
                }
                if ($current === $expectedValue) {
                    continue;
                }
                $mismatches[] = [
                    'path' => $relPath,
                    'field' => $field,
                    'label' => $label,
                    'current' => $current,
                    'expected' => $expectedValue,
                ];
                if ($apply) {
                    $lines[$i] = $label . $expectedValue . '`' . ($hasCr ? "\r" : '');
                    $changed = true;
                }
            }
        }
    }

    if ($apply && $changed) {
        file_put_contents($absPath, implode("\n", $lines));
        $filesChanged[] = $relPath;
    }
}

function aiRunProjectValuesSync(string $root, array $args): int
{
    $apply = in_array('--apply', $args, true);
    $fail = in_array('--fail', $args, true);

    $result = aiProjectValuesSyncScan($root, $apply);

    $status = 'ok';
    $recommended = 'All known project-value lines already match .ai/project.yml.';
    if ($result['mismatches'] !== []) {
        if ($apply) {
            $status = 'ok';
            $recommended = 'Applied ' . count($result['files_changed']) . ' file(s). Re-run without --apply to confirm zero remaining mismatches.';
        } else {
            $status = $fail ? 'failed' : 'warning';
            $recommended = 'Run `php tools/ai/ai.php project-values-sync --apply` to propagate .ai/project.yml values into rendered files.';
        }
    }

    $written = aiCliWriteArtifact(
        $root,
        'project-values-sync',
        'php tools/ai/ai.php project-values-sync' . ($apply ? ' --apply' : ''),
        $result,
        $status,
        null,
        $recommended
    );
    fwrite(STDOUT, 'OK: ' . aiCliArtifactSummary($written) . PHP_EOL);

    if ($fail && $status === 'failed') {
        return 1;
    }
    return 0;
}
