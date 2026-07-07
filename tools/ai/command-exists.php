<?php

declare(strict_types=1);

/**
 * Shared cross-platform "is this command on PATH?" check.
 *
 * Extracted from two byte-identical copies found by this repo's jscpd duplication guardrail
 * (`scripts/ai/internal/ai-verify/35-jscpd.sh`): `aiAdvisorCommandExists()` in
 * `tools/ai/advisor/registry.php` and `aiCliCommandExists()` in `tools/ai/commands/helpers.php`.
 * Both call sites now delegate here; their original names and signatures are unchanged so no
 * caller needed to be edited (see
 * docs/tickets/arch-todo-jscpd-duplicate-code-cleanup-20260707-005840/plan.md, P0).
 *
 * On Windows, falls back to scanning the WinGet package-links directory when a plain `where`
 * lookup misses, and prepends the discovered binary's directory to PATH (mutating
 * `putenv`/$_SERVER/$_ENV) so a subsequent `exec()`/`proc_open()` call in the same process can
 * find it too. On POSIX, this is a plain `command -v` check.
 */
function aiCommandExists(string $command): bool
{
    $out = [];
    $exit = 0;
    if (PHP_OS_FAMILY === 'Windows') {
        exec('where ' . escapeshellarg($command) . ' >NUL 2>&1', $out, $exit);
        if ($exit === 0) {
            return true;
        }
        $user = getenv('USERPROFILE');
        if (is_string($user) && $user !== '') {
            $base = $user . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'WinGet' . DIRECTORY_SEPARATOR . 'Packages';
            if (is_dir($base)) {
                $wanted = strtolower($command . '.exe');
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $entry) {
                    if (!$entry->isFile()) {
                        continue;
                    }
                    if (strtolower($entry->getFilename()) === $wanted) {
                        $dir = (string) $entry->getPath();
                        $path = (string) getenv('PATH');
                        $parts = preg_split('/;/', $path) ?: [];
                        $hasDir = false;
                        foreach ($parts as $part) {
                            if (strcasecmp(trim($part), $dir) === 0) {
                                $hasDir = true;
                                break;
                            }
                        }
                        if (!$hasDir) {
                            $newPath = $dir . ';' . $path;
                            putenv('PATH=' . $newPath);
                            $_SERVER['PATH'] = $newPath;
                            $_ENV['PATH'] = $newPath;
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }
    exec('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1', $out, $exit);
    return $exit === 0;
}
