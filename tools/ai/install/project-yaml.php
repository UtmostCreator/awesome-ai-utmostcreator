<?php

declare(strict_types=1);

/**
 * Minimal, dependency-free helpers for the small flat YAML used by .ai/project.yml.
 *
 * Shared by the installer (core.php) and the standalone policy compiler so the indent /
 * comment / list-item parsing lives in exactly one place. Intentionally tiny and free of
 * other install dependencies so the compiler can require it without pulling in the full
 * installer surface.
 */

if (!function_exists('aiInstallerProjectYamlQuote')) {
    function aiInstallerProjectYamlQuote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}

if (!function_exists('aiInstallerProjectYamlUnquote')) {
    function aiInstallerProjectYamlUnquote(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
            return str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
        }
        // Single-quoted scalars: strip the surrounding quotes (YAML single quotes do not
        // process backslash escapes), matching prior policy-allow parsing behavior.
        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('aiInstallerParseProjectYamlList')) {
    /**
     * Parse a simple two-level YAML list (`<topKey>:` then `  <listKey>:` then `    - "item"`).
     *
     * @return list<string>
     */
    function aiInstallerParseProjectYamlList(string $yaml, string $topKey, string $listKey): array
    {
        $items = [];
        $inTop = false;
        $inList = false;
        $listPattern = '/^' . preg_quote($listKey, '/') . ':\s*(\[\])?\s*$/';

        foreach (preg_split('/\R/', $yaml) ?: [] as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            $trimmed = trim($line);

            if ($indent === 0) {
                $inTop = ($trimmed === $topKey . ':');
                $inList = false;
                continue;
            }
            if (!$inTop) {
                continue;
            }
            if (preg_match($listPattern, $trimmed) === 1) {
                $inList = true;
                continue;
            }
            // A sibling key under the top block ends the list.
            if ($inList && !str_starts_with($trimmed, '- ') && str_ends_with($trimmed, ':')) {
                $inList = false;
                continue;
            }
            if ($inList && str_starts_with($trimmed, '- ')) {
                $value = aiInstallerProjectYamlUnquote(trim(substr($trimmed, 2)));
                if ($value !== '') {
                    $items[] = $value;
                }
            }
        }

        return array_values(array_unique($items));
    }
}
