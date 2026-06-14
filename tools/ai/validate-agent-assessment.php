<?php

declare(strict_types=1);

/**
 * Validates the OPTIONAL `agent_assessment` rubric block in agent frontmatter.
 *
 * The rubric is optional and unenforced: an agent file with no `agent_assessment:`
 * block is valid. When the block IS present, each field must satisfy the ranges and
 * enums declared in schemas/ai/agent-assessment.schema.json. Unknown fields are
 * rejected (the schema is additionalProperties:false).
 *
 * Scans agent surfaces:
 *   - packages/ai-universal-rules/templates/**\/agents/*.md (canonical source)
 *   - .opencode/agents/*.md (rendered OpenCode surface)
 *
 * Usage:
 *   php tools/ai/validate-agent-assessment.php [--root=PATH]
 * Exit: 0 when all present blocks are valid, 1 on any violation, 2 on usage/IO error.
 */

/** @return array<string,array{type:string,min?:int,max?:int,enum?:list<string>}> */
function aiAgentAssessmentFields(): array
{
    return [
        'score'                 => ['type' => 'int', 'min' => 0, 'max' => 100],
        'confidence'            => ['type' => 'int', 'min' => 0, 'max' => 100],
        'role_clarity'          => ['type' => 'int', 'min' => 0, 'max' => 15],
        'scope_control'         => ['type' => 'int', 'min' => 0, 'max' => 15],
        'permission_safety'     => ['type' => 'int', 'min' => 0, 'max' => 15],
        'output_contract'       => ['type' => 'int', 'min' => 0, 'max' => 15],
        'evidence_required'     => ['type' => 'int', 'min' => 0, 'max' => 15],
        'verification_strength' => ['type' => 'int', 'min' => 0, 'max' => 15],
        'handoff_quality'       => ['type' => 'int', 'min' => 0, 'max' => 10],
        'risk_level'            => ['type' => 'enum', 'enum' => ['low', 'medium', 'high', 'critical']],
        'decision'              => ['type' => 'enum', 'enum' => ['approve', 'approve_with_minor_fixes', 'needs_refactor', 'block']],
    ];
}

/**
 * Extracts the YAML frontmatter block (between the first two `---` fences) or null.
 */
function aiAgentExtractFrontmatter(string $content): ?string
{
    if (preg_match('/^---\R(.*?)\R---/s', $content, $m)) {
        return $m[1];
    }

    return null;
}

/**
 * Parses an `agent_assessment:` mapping out of frontmatter as key => raw scalar.
 * Returns null when the block is absent. Comment lines and blank lines are ignored.
 *
 * @return array<string,string>|null
 */
function aiAgentParseAssessment(string $frontmatter): ?array
{
    $lines = explode("\n", $frontmatter);
    $inBlock = false;
    $result = [];
    foreach ($lines as $line) {
        $trimmedRight = rtrim($line);
        if (preg_match('/^agent_assessment:\s*$/', trim($line))) {
            $inBlock = true;
            continue;
        }
        if (!$inBlock) {
            continue;
        }
        // A non-indented, non-blank, non-comment line ends the block.
        if ($trimmedRight !== '' && !preg_match('/^\s/', $line) && !preg_match('/^\s*#/', $line)) {
            break;
        }
        if (trim($line) === '' || preg_match('/^\s*#/', $line)) {
            continue;
        }
        if (preg_match('/^\s+([\w-]+):\s*(.+?)\s*$/', $line, $m)) {
            $result[$m[1]] = $m[2];
        }
    }

    return $inBlock ? $result : null;
}

/**
 * @param array<string,string> $assessment
 * @return list<string> validation error messages (empty when valid)
 */
function aiAgentValidateAssessment(array $assessment, string $rel): array
{
    $fields = aiAgentAssessmentFields();
    $errors = [];
    foreach ($assessment as $key => $raw) {
        if (!isset($fields[$key])) {
            $errors[] = "{$rel}: unknown agent_assessment field '{$key}'";
            continue;
        }
        $spec = $fields[$key];
        if ($spec['type'] === 'int') {
            if (!preg_match('/^-?\d+$/', $raw)) {
                $errors[] = "{$rel}: agent_assessment.{$key} must be an integer, got '{$raw}'";
                continue;
            }
            $val = (int) $raw;
            if ($val < $spec['min'] || $val > $spec['max']) {
                $errors[] = "{$rel}: agent_assessment.{$key}={$val} out of range {$spec['min']}-{$spec['max']}";
            }
        } elseif ($spec['type'] === 'enum') {
            if (!in_array($raw, $spec['enum'], true)) {
                $errors[] = "{$rel}: agent_assessment.{$key}='{$raw}' not in [" . implode('|', $spec['enum']) . ']';
            }
        }
    }

    return $errors;
}

/**
 * @param list<string> $argv
 */
function aiAgentAssessmentMain(array $argv): int
{
    // Script lives in tools/ai/, so the repo root is two levels up.
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

    $patterns = [
        $root . '/packages/ai-universal-rules/templates/core/agents/*.md',
        $root . '/packages/ai-universal-rules/templates/optional/agents/*.md',
        $root . '/.opencode/agents/*.md',
    ];
    $files = [];
    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            $files[$file] = true;
        }
    }

    $errors = [];
    $present = 0;
    $scanned = 0;
    foreach (array_keys($files) as $file) {
        $scanned++;
        $content = (string) file_get_contents($file);
        $fm = aiAgentExtractFrontmatter($content);
        if ($fm === null) {
            continue;
        }
        $assessment = aiAgentParseAssessment($fm);
        if ($assessment === null) {
            continue;
        }
        $present++;
        $rel = ltrim(str_replace($root, '', $file), '/\\');
        $errors = array_merge($errors, aiAgentValidateAssessment($assessment, $rel));
    }

    if ($errors === []) {
        fwrite(STDOUT, "OK: agent_assessment rubric valid (scanned {$scanned} agent file(s); {$present} carry a rubric block)\n");
        return 0;
    }

    fwrite(STDERR, "ERROR: agent_assessment rubric violation(s):\n");
    foreach ($errors as $message) {
        fwrite(STDERR, ' - ' . $message . "\n");
    }

    return 1;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiAgentAssessmentMain($argv));
}
