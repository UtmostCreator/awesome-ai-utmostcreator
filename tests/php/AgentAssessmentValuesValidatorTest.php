<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the D3a agent_assessment VALUES SOURCE file
 * (docs/ai/agent-scores.yaml) and its validator
 * tools/ai/validate-agent-assessment-values.php.
 */
class AgentAssessmentValuesValidatorTest extends TestCase
{
    private static function root(): string
    {
        $root = realpath(dirname(__DIR__, 2));
        self::assertNotFalse($root);

        return $root;
    }

    public function testSourceSchemaIsWellFormed(): void
    {
        $path = self::root() . '/schemas/ai/agent-assessment-values.schema.json';
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('$id', $decoded);
        $this->assertSame(false, $decoded['additionalProperties'] ?? null);
        $this->assertSame('ai.agent_assessment.values/v1', $decoded['properties']['schema']['const'] ?? null);
        $this->assertContains('approved', $decoded['required'] ?? []);
    }

    public function testLiveSourceFilePassesAndIsApproved(): void
    {
        // D3a -> D3b gate: the human reviewed the categorical values (including the
        // bootstrapper/ui-builder REVIEW-NEEDED entries) and flipped `approved: true`
        // on 2026-07-05 (see docs/ai/agent-scores.yaml header comment and
        // docs/tickets/arch-todo-agent-score-frontmatter-20260614-104816/D3-plan.md).
        // The D3b renderer now consumes this file; this assertion pins that gate.
        [$code, $out] = $this->runValidator(self::root() . '/docs/ai/agent-scores.yaml', self::root());
        $this->assertSame(0, $code, "live source should validate:\n" . $out);
        $this->assertStringContainsString('1:1 with live templates', $out);
        $this->assertStringContainsString('APPROVED', $out, 'live source must be APPROVED (approved: true) now that D3b has landed');
    }

    public function testLiveSourceCoversEveryTemplateExactlyOnce(): void
    {
        $root = self::root();
        $keys = [];
        foreach (['core', 'optional'] as $tier) {
            foreach (glob($root . "/packages/ai-universal-rules/templates/{$tier}/agents/*.md") ?: [] as $f) {
                $keys[basename($f, '.md')] = true;
            }
        }
        $source = (string) file_get_contents($root . '/docs/ai/agent-scores.yaml');
        foreach (array_keys($keys) as $key) {
            $this->assertMatchesRegularExpression(
                '/^  ' . preg_quote($key, '/') . ':\s*$/m',
                $source,
                "agent-scores.yaml missing entry for template '{$key}'"
            );
        }
    }

    public function testMissingEntryFails(): void
    {
        $dir = $this->fixtureTree(
            ['architect', 'reviewer'],
            "schema: ai.agent_assessment.values/v1\napproved: false\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: high\n      decision: approve\n    rationale: \"x\"\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString("missing source entry for live agent template 'reviewer'", $out);
    }

    public function testStaleKeyFails(): void
    {
        $dir = $this->fixtureTree(
            ['architect'],
            "schema: ai.agent_assessment.values/v1\napproved: false\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: high\n      decision: approve\n    rationale: \"x\"\n"
            . "  Architect-DisplayName:\n    agent_assessment:\n      risk_level: low\n      decision: approve\n    rationale: \"y\"\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('stale/unknown source key', $out);
    }

    public function testNumericFieldRejectedInV1(): void
    {
        $dir = $this->fixtureTree(
            ['architect'],
            "schema: ai.agent_assessment.values/v1\napproved: false\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: high\n      decision: approve\n      score: 80\n    rationale: \"x\"\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString("numeric rubric field 'score' is not allowed in v1", $out);
    }

    public function testMissingRationaleFails(): void
    {
        $dir = $this->fixtureTree(
            ['architect'],
            "schema: ai.agent_assessment.values/v1\napproved: false\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: high\n      decision: approve\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('missing non-empty rationale', $out);
    }

    public function testApprovedStateIsReportedAndPasses(): void
    {
        $dir = $this->fixtureTree(
            ['architect'],
            "schema: ai.agent_assessment.values/v1\napproved: true\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: high\n      decision: approve\n    rationale: \"x\"\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('APPROVED (renderers may consume)', $out);
    }

    public function testInlineCommentOnApprovedIsTolerated(): void
    {
        $dir = $this->fixtureTree(
            ['architect'],
            "schema: ai.agent_assessment.values/v1\napproved: false  # draft, do not consume\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: high\n      decision: approve\n    rationale: \"x\"\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('DRAFT', $out);
    }

    public function testDefaultRootResolvesToRepoRoot(): void
    {
        // Bare invocation (no --root, no path) must resolve the live repo source,
        // proving the script derives the repo root correctly from tools/ai/.
        $php = escapeshellarg((string) PHP_BINARY);
        $script = escapeshellarg(self::root() . '/tools/ai/validate-agent-assessment-values.php');
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open("{$php} {$script}", $descriptors, $pipes, self::root());
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        $this->assertSame(0, $code, $stdout . $stderr);
        $this->assertStringContainsString('1:1 with live templates', $stdout . $stderr);
    }

    public function testBadEnumFails(): void
    {
        $dir = $this->fixtureTree(
            ['architect'],
            "schema: ai.agent_assessment.values/v1\napproved: false\nagents:\n"
            . "  architect:\n    agent_assessment:\n      risk_level: extreme\n      decision: ship-it\n    rationale: \"x\"\n"
        );
        [$code, $out] = $this->runValidator($dir . '/docs/ai/agent-scores.yaml', $dir);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('not in [low|medium|high|critical]', $out);
        $this->assertStringContainsString('not in [approve|approve_with_minor_fixes|needs_refactor|block]', $out);
    }

    /**
     * @param list<string> $templateKeys
     */
    private function fixtureTree(array $templateKeys, string $sourceYaml): string
    {
        $base = sys_get_temp_dir() . '/agent-scores-' . uniqid('', true);
        $coreDir = $base . '/packages/ai-universal-rules/templates/core/agents';
        $docsDir = $base . '/docs/ai';
        mkdir($coreDir, 0777, true);
        mkdir($docsDir, 0777, true);
        foreach ($templateKeys as $key) {
            file_put_contents($coreDir . '/' . $key . '.md', "---\nid: {$key}\n---\nbody\n");
        }
        file_put_contents($docsDir . '/agent-scores.yaml', $sourceYaml);

        return $base;
    }

    /** @return array{0:int,1:string} */
    private function runValidator(string $path, string $root): array
    {
        $php = escapeshellarg((string) PHP_BINARY);
        $script = escapeshellarg(self::root() . '/tools/ai/validate-agent-assessment-values.php');
        $pathArg = escapeshellarg($path);
        $rootArg = escapeshellarg('--root=' . $root);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open("{$php} {$script} {$pathArg} {$rootArg}", $descriptors, $pipes);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$code, $stdout . $stderr];
    }
}
