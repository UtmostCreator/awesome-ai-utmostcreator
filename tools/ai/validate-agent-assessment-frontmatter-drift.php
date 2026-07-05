<?php

declare(strict_types=1);

/**
 * D3b drift check: proves docs/ai/agent-scores.yaml (source) <=> each canonical
 * agent TEMPLATE's rendered `agent_assessment:` frontmatter block <=> the
 * docs/ai/AGENTS-MANIFEST.md `Risk` column agree, using an IDENTITY mapping (same
 * low|medium|high|critical vocabulary end to end — no translation table).
 *
 * Three-way check per live agent template key:
 *   1. source.risk_level   == template.agent_assessment.risk_level
 *   2. source.decision     == template.agent_assessment.decision
 *   3. template.risk_level == manifest Risk column, WHEN the key has a manifest row
 *      (bootstrapper is the only key with both; ui-builder and super-implementer are
 *      documented manifest gaps — see docs/ai/AGENTS-MANIFEST.md "Surface coverage
 *      differences" and docs/tickets/arch-todo-agent-score-frontmatter-20260614-104816/
 *      D3-plan.md; a missing manifest row is reported as informational, not an error).
 *
 * Reuses the existing restricted-subset parsers rather than re-implementing them:
 *   - aiAavParse()/aiAavTemplateKeys() from validate-agent-assessment-values.php
 *   - aiAgentExtractFrontmatter()/aiAgentParseAssessment() from validate-agent-assessment.php
 *
 * Usage:
 *   php tools/ai/validate-agent-assessment-frontmatter-drift.php [--root=PATH]
 * Exit: 0 no drift, 1 drift found, 2 usage/IO error.
 */
require_once __DIR__ . '/validate-agent-assessment-values.php';
require_once __DIR__ . '/validate-agent-assessment.php';

/**
 * Parses the `Risk` column (7th pipe-delimited cell) out of the AGENTS-MANIFEST.md
 * "Agent inventory" table, keyed by the backtick-quoted agent id in column 1.
 *
 * @return array<string,string> agent key => risk_level
 */
function aiAafdParseManifestRisk(string $manifest): array
{
    $out = [];
    foreach (preg_split('/\R/', $manifest) ?: [] as $line) {
        if (!str_starts_with(trim($line), '|')) {
            continue;
        }
        if (!preg_match('/^\|\s*`([a-z0-9-]+)`\s*\|/', $line, $m)) {
            continue;
        }
        $cells = array_map('trim', explode('|', $line));
        // Leading/trailing empty strings from the outer pipes at index 0 and last.
        $cells = array_values(array_filter($cells, static fn (int $i): bool => $i > 0, ARRAY_FILTER_USE_KEY));
        // $cells is now: [Agent, OpenCode, GitHub, Lifecycle, Mutating, Gate, Risk, Purpose, '']
        if (count($cells) < 7) {
            continue;
        }
        $out[$m[1]] = $cells[6];
    }

    return $out;
}

/**
 * @param list<string> $argv
 */
function aiAafdMain(array $argv): int
{
    $root = realpath(__DIR__ . '/..' . '/..');
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $candidate = realpath(substr($arg, 7));
            if ($candidate === false) {
                fwrite(STDERR, "ERROR: --root path not found\n");
                return 2;
            }
            $root = $candidate;
        }
    }
    if ($root === false) {
        fwrite(STDERR, "ERROR: repository root not found\n");
        return 2;
    }

    $sourcePath = $root . '/docs/ai/agent-scores.yaml';
    if (!is_file($sourcePath)) {
        fwrite(STDERR, "ERROR: source file not found: {$sourcePath}\n");
        return 2;
    }
    $manifestPath = $root . '/docs/ai/AGENTS-MANIFEST.md';
    if (!is_file($manifestPath)) {
        fwrite(STDERR, "ERROR: manifest file not found: {$manifestPath}\n");
        return 2;
    }

    $source = aiAavParse((string) file_get_contents($sourcePath));
    $manifestRisk = aiAafdParseManifestRisk((string) file_get_contents($manifestPath));
    $templateKeys = aiAavTemplateKeys($root);

    $errors = [];
    $infoSkips = [];
    $checked = 0;

    foreach ($templateKeys as $key) {
        if (!isset($source['agents'][$key])) {
            $errors[] = "{$key}: no source entry in docs/ai/agent-scores.yaml (should have been caught by validate-agent-assessment-values.php)";
            continue;
        }
        $srcAssessment = $source['agents'][$key]['assessment'];

        $templatePath = null;
        foreach (['core', 'optional'] as $tier) {
            $candidate = "{$root}/packages/ai-universal-rules/templates/{$tier}/agents/{$key}.md";
            if (is_file($candidate)) {
                $templatePath = $candidate;
                break;
            }
        }
        if ($templatePath === null) {
            $errors[] = "{$key}: template file not found under templates/{core,optional}/agents/";
            continue;
        }

        $content = (string) file_get_contents($templatePath);
        $fm = aiAgentExtractFrontmatter($content);
        $tmplAssessment = $fm !== null ? aiAgentParseAssessment($fm) : null;
        $rel = ltrim(str_replace($root, '', $templatePath), '/\\');

        if ($tmplAssessment === null) {
            $errors[] = "{$rel}: no agent_assessment: block in template frontmatter (run the D3b writer)";
            $checked++;
            continue;
        }

        if (($tmplAssessment['risk_level'] ?? null) !== ($srcAssessment['risk_level'] ?? null)) {
            $errors[] = "{$key}: risk_level drift — source='" . ($srcAssessment['risk_level'] ?? '?') . "' template ({$rel})='" . ($tmplAssessment['risk_level'] ?? '?') . "'";
        }
        if (($tmplAssessment['decision'] ?? null) !== ($srcAssessment['decision'] ?? null)) {
            $errors[] = "{$key}: decision drift — source='" . ($srcAssessment['decision'] ?? '?') . "' template ({$rel})='" . ($tmplAssessment['decision'] ?? '?') . "'";
        }
        if (isset($tmplAssessment['rationale'])) {
            $errors[] = "{$key}: rationale must never be rendered into frontmatter ({$rel})";
        }

        if (isset($manifestRisk[$key])) {
            if ($manifestRisk[$key] !== ($tmplAssessment['risk_level'] ?? null)) {
                $errors[] = "{$key}: risk_level drift — template ({$rel})='" . ($tmplAssessment['risk_level'] ?? '?') . "' manifest Risk column='" . $manifestRisk[$key] . "'";
            }
        } else {
            $infoSkips[] = $key;
        }

        $checked++;
    }

    if ($errors !== []) {
        fwrite(STDERR, "ERROR: agent_assessment drift found (source <=> template <=> manifest):\n");
        foreach ($errors as $message) {
            fwrite(STDERR, ' - ' . $message . "\n");
        }
        return 1;
    }

    $skipNote = $infoSkips === [] ? '' : ' (no manifest row, skipped identity check: ' . implode(', ', $infoSkips) . ')';
    fwrite(STDOUT, "OK: no agent_assessment drift across {$checked} agent template(s): source <=> template <=> manifest agree{$skipNote}\n");

    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiAafdMain($argv));
}
