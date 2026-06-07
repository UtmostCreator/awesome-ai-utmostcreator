<?php

declare(strict_types=1);

/**
 * Phase 10: StubValidator.
 *
 * Detects "phantom" surfaces — files that present as a real doc or script but carry no
 * substantive content. The check is content-based, not size-based, to avoid flagging the many
 * legitimately terse docs and thin command wrappers in this repo:
 *
 *  - A markdown stub has a heading (or nothing) but no body: no paragraph, list, code block,
 *    table, or blockquote, OR it contains an unresolved stub marker (TODO/PLACEHOLDER/coming soon).
 *  - A shell stub has a shebang but no executable statement (only comments, blanks, and `set`).
 *
 * Generated artifacts, vendor, node_modules, and an explicit allowlist are excluded.
 *
 * Usage:
 *   php tools/ai/validate-stub-surfaces.php [--root=PATH]
 * Exit: 0 when no stubs found, 1 when stub surfaces are detected.
 */

function aiStubMain(array $argv): int
{
    $root = getcwd() ?: '.';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $root = substr($arg, 7);
        }
    }
    $root = rtrim(str_replace('\\', '/', (string) (realpath($root) ?: $root)), '/');

    $files = aiStubListTrackedFiles($root);
    $allowlist = aiStubAllowlist();

    $stubs = [];
    foreach ($files as $rel) {
        if (aiStubIsExcluded($rel) || in_array($rel, $allowlist, true)) {
            continue;
        }
        $abs = $root . '/' . $rel;
        if (!is_file($abs)) {
            continue;
        }
        $content = (string) file_get_contents($abs);

        if (str_ends_with($rel, '.md') && aiStubIsMarkdownStub($content)) {
            $stubs[] = [$rel, 'markdown surface has no substantive body'];
        } elseif (str_ends_with($rel, '.sh') && aiStubIsShellStub($content)) {
            $stubs[] = [$rel, 'shell script has no executable statement'];
        }
    }

    if ($stubs === []) {
        fwrite(STDOUT, 'OK: no phantom stub surfaces detected (' . count($files) . " files scanned)\n");
        return 0;
    }

    fwrite(STDERR, "ERROR: phantom stub surfaces detected:\n");
    foreach ($stubs as [$rel, $why]) {
        fwrite(STDERR, " - {$rel}: {$why}\n");
    }
    fwrite(STDERR, "Complete the file, delete it, or add it to the StubValidator allowlist with justification.\n");

    return 1;
}

/** @return list<string> */
function aiStubListTrackedFiles(string $root): array
{
    // Prefer git for the tracked set; fall back to a recursive scan.
    $out = [];
    $exit = 0;
    $prev = getcwd();
    if (is_string($prev) && @chdir($root)) {
        exec('git ls-files -- "*.md" "*.sh" 2>/dev/null', $out, $exit);
        chdir($prev);
    }
    if ($exit === 0 && $out !== []) {
        return array_values(array_map(static fn(string $p): string => str_replace('\\', '/', trim($p)), $out));
    }

    $found = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $ext = strtolower($item->getExtension());
        if ($ext === 'md' || $ext === 'sh') {
            $found[] = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($root))), '/');
        }
    }

    return $found;
}

function aiStubIsExcluded(string $rel): bool
{
    foreach (['vendor/', 'node_modules/', 'docs/ai/generated/', '.git/', 'tests/fixtures/'] as $prefix) {
        if (str_starts_with($rel, $prefix) || str_contains($rel, '/' . $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * Intentionally terse files that are NOT phantom stubs (they carry real, if short, content).
 * Keep this list small and justified.
 *
 * @return list<string>
 */
function aiStubAllowlist(): array
{
    return [];
}

function aiStubIsMarkdownStub(string $content): bool
{
    // A line that is *only* a stub marker (not prose that merely mentions the word) counts as
    // a stub. This deliberately does not match normal documentation about placeholders/TODOs.
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $trimmed = trim($line);
        if (preg_match('/^(_?\(?(TODO|FIXME|stub|placeholder|coming soon|to be written|to be filled)\)?_?[.:]?)$/i', $trimmed) === 1) {
            return true;
        }
        if (preg_match('/^<!--\s*(TODO|stub|placeholder)\b/i', $trimmed) === 1) {
            return true;
        }
    }

    $hasBody = false;
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        // Headings and HTML comments do not count as body.
        if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, '<!--')) {
            continue;
        }
        // Any other non-empty line (paragraph, list, code fence, table, quote, link) is body.
        $hasBody = true;
        break;
    }

    return !$hasBody;
}

function aiStubIsShellStub(string $content): bool
{
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        // Comments, shebang, and shell option lines are not executable statements.
        if (str_starts_with($trimmed, '#')) {
            continue;
        }
        if (preg_match('/^set\s+[-+]/', $trimmed) === 1) {
            continue;
        }
        // Any other non-empty line is a real statement.
        return false;
    }

    return true;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiStubMain($argv));
}
