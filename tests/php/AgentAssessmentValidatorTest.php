<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Covers the OPTIONAL agent_assessment rubric: schema existence/shape, and the
 * validator's behavior for present-valid, present-malformed, and absent blocks.
 */
class AgentAssessmentValidatorTest extends TestCase
{
    private static function root(): string
    {
        $root = realpath(dirname(__DIR__, 2));
        self::assertNotFalse($root);

        return $root;
    }

    public function testSchemaFileIsWellFormedAndAddressable(): void
    {
        $path = self::root() . '/schemas/ai/agent-assessment.schema.json';
        $this->assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('$schema', $decoded);
        $this->assertArrayHasKey('$id', $decoded);
        $this->assertArrayHasKey('title', $decoded);
        $this->assertSame(false, $decoded['additionalProperties'] ?? null);
        $this->assertArrayHasKey('risk_level', $decoded['properties']);
        $this->assertSame(['low', 'medium', 'high', 'critical'], $decoded['properties']['risk_level']['enum']);
    }

    public function testValidatorPassesOnLiveTree(): void
    {
        // The pilot block on the architect template must validate; absence elsewhere is fine.
        [$code, $out] = $this->runValidator(self::root());
        $this->assertSame(0, $code, "validator should pass on live tree:\n" . $out);
        $this->assertStringContainsString('agent_assessment rubric valid', $out);
    }

    public function testPresentValidBlockPasses(): void
    {
        $dir = $this->fixtureWithAgent("---\nid: x\nagent_assessment:\n  risk_level: high\n  score: 80\n  handoff_quality: 7\n---\nbody\n");
        [$code] = $this->runValidator($dir);
        $this->assertSame(0, $code);
    }

    public function testMalformedBlockFails(): void
    {
        // score out of range + unknown field + bad enum.
        $dir = $this->fixtureWithAgent("---\nid: x\nagent_assessment:\n  score: 250\n  bogus: 1\n  risk_level: extreme\n---\nbody\n");
        [$code, $out] = $this->runValidator($dir);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('out of range', $out);
        $this->assertStringContainsString("unknown agent_assessment field 'bogus'", $out);
        $this->assertStringContainsString('not in [low|medium|high|critical]', $out);
    }

    public function testAbsentBlockPasses(): void
    {
        $dir = $this->fixtureWithAgent("---\nid: x\nmode: all\n---\nbody\n");
        [$code, $out] = $this->runValidator($dir);
        $this->assertSame(0, $code, $out);
    }

    private function fixtureWithAgent(string $agentContent): string
    {
        $base = sys_get_temp_dir() . '/agent-assessment-' . uniqid('', true);
        $agentsDir = $base . '/.opencode/agents';
        mkdir($agentsDir, 0777, true);
        file_put_contents($agentsDir . '/sample.md', $agentContent);

        return $base;
    }

    /** @return array{0:int,1:string} */
    private function runValidator(string $root): array
    {
        $php = escapeshellarg((string) PHP_BINARY);
        $script = escapeshellarg(self::root() . '/tools/ai/validate-agent-assessment.php');
        $rootArg = escapeshellarg('--root=' . $root);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open("{$php} {$script} {$rootArg}", $descriptors, $pipes);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [$code, $stdout . $stderr];
    }
}
