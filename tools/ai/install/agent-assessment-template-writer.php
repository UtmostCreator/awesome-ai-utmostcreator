<?php

declare(strict_types=1);

/**
 * D3b: writes the human-approved `agent_assessment` block (risk_level + decision
 * ONLY — never `rationale`, which is source-only per docs/ai/agent-scores.yaml) from
 * the D3a values source into a canonical agent TEMPLATE's frontmatter.
 *
 * This is the template-authoring counterpart to aiCopilotExtractAssessmentBlock()
 * (copilot-agent-renderer.php), which extracts the SAME block FROM a template's
 * frontmatter INTO the Copilot/Claude rendered surfaces at install time. Templates
 * are the canonical source; this writer keeps them in sync with the approved values
 * source (docs/ai/agent-scores.yaml) so the existing D2 extraction mechanism has a
 * block to extract for every agent, not just the D1 pilot (architect.md).
 *
 * Deliberately reuses aiAavParse()/aiAavTemplateKeys() from
 * validate-agent-assessment-values.php rather than re-implementing the restricted
 * YAML-subset parser (see tools/ai/validate-agent-assessment-values.php).
 */
require_once __DIR__ . '/../validate-agent-assessment-values.php';

/**
 * Builds the normalized `agent_assessment:` YAML block text (2-space indent,
 * matching the D1 pilot block hand-authored in architect.md). Field order is
 * always risk_level then decision, regardless of source field order.
 *
 * @param array{risk_level:string,decision:string} $assessment
 */
function aiAssessmentRenderBlock(array $assessment): string
{
    return "agent_assessment:\n"
        . "  risk_level: {$assessment['risk_level']}\n"
        . "  decision: {$assessment['decision']}\n";
}

/**
 * Replaces an existing `agent_assessment:` block inside a template's frontmatter, or
 * inserts one immediately before the closing `---` fence when absent. Every other
 * frontmatter key and the body are left byte-identical. Idempotent: re-running with
 * the same $assessment against already-updated content returns the same content.
 *
 * @param array{risk_level:string,decision:string} $assessment
 * @throws RuntimeException when $content has no parseable `---` frontmatter fence.
 */
function aiAssessmentInjectIntoTemplate(string $content, array $assessment): string
{
    if (!preg_match('/^---\R/', $content)) {
        throw new RuntimeException('template has no parseable frontmatter fence');
    }
    // Locate the closing fence: a line that is exactly `---` after the opening line.
    $lines = explode("\n", $content);
    $closeIdx = null;
    for ($i = 1; $i < count($lines); $i++) {
        if (rtrim($lines[$i], "\r") === '---') {
            $closeIdx = $i;
            break;
        }
    }
    if ($closeIdx === null) {
        throw new RuntimeException('template has no closing frontmatter fence');
    }

    $block = aiAssessmentRenderBlock($assessment);
    $blockLines = explode("\n", rtrim($block, "\n"));

    // Find an existing `agent_assessment:` top-level key inside the frontmatter
    // (lines 1..closeIdx-1) and its indented value lines, to replace in place.
    $blockStart = null;
    $blockEnd = null;
    for ($i = 1; $i < $closeIdx; $i++) {
        if (preg_match('/^agent_assessment:\s*$/', rtrim($lines[$i], "\r"))) {
            $blockStart = $i;
            $blockEnd = $i + 1;
            while ($blockEnd < $closeIdx && preg_match('/^[ \t]+\S/', $lines[$blockEnd])) {
                $blockEnd++;
            }
            break;
        }
    }

    if ($blockStart !== null && $blockEnd !== null) {
        array_splice($lines, $blockStart, $blockEnd - $blockStart, $blockLines);
    } else {
        array_splice($lines, $closeIdx, 0, $blockLines);
    }

    return implode("\n", $lines);
}

/**
 * Loads the approved values source and returns a map of agent key => categorical
 * assessment (risk_level, decision only). Throws when the source is not approved,
 * so callers cannot accidentally render draft values (D3a/D3b approval gate).
 *
 * @return array<string,array{risk_level:string,decision:string}>
 */
function aiAssessmentLoadApprovedSource(string $root): array
{
    $path = $root . '/docs/ai/agent-scores.yaml';
    if (!is_file($path)) {
        throw new RuntimeException("agent-scores.yaml not found at {$path}");
    }
    $parsed = aiAavParse((string) file_get_contents($path));
    if (($parsed['approved'] ?? null) !== 'true') {
        throw new RuntimeException(
            'docs/ai/agent-scores.yaml is not approved (approved: false); refusing to render'
        );
    }

    $out = [];
    foreach ($parsed['agents'] as $key => $entry) {
        $a = $entry['assessment'];
        if (!isset($a['risk_level'], $a['decision'])) {
            throw new RuntimeException("{$key}: source entry missing risk_level/decision");
        }
        $out[$key] = ['risk_level' => $a['risk_level'], 'decision' => $a['decision']];
    }

    return $out;
}

/**
 * Resolves the absolute template file path for a canonical agent key (core first,
 * then optional), or null when no live template matches.
 */
function aiAssessmentTemplatePathForKey(string $root, string $key): ?string
{
    foreach (['core', 'optional'] as $tier) {
        $candidate = "{$root}/packages/ai-universal-rules/templates/{$tier}/agents/{$key}.md";
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}
