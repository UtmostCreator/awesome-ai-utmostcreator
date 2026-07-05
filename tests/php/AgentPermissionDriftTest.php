<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/ai/install/permission-layers/compositions.php';

/**
 * Proves the generated permission block for every managed agent
 * (aiPermissionAgentCompositions()) matches what is currently shipped, both in
 * the template source and the installed .opencode copy. This is the CI parity
 * gate for `php tools/ai/generate-agent-permissions.php --check` (AC-9).
 */
final class AgentPermissionDriftTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
    }

    public function testManagedAgentsHaveNoDrift(): void
    {
        $result = shell_exec('php ' . escapeshellarg(self::$repoRoot . '/tools/ai/generate-agent-permissions.php') . ' --check 2>&1');
        self::assertIsString($result);
        self::assertStringContainsString('in sync', (string) $result, "Generator check failed:\n{$result}");
    }

    public function testEveryManagedAgentComposesWithoutError(): void
    {
        foreach (aiPermissionAgentCompositions() as $agent => $composition) {
            $result = aiPermissionComposeFromSpec($composition['compose_spec']);
            self::assertNotEmpty($result['model'], "agent '{$agent}' composed to an empty model");
            self::assertArrayHasKey('*', array_flip(array_map(
                static fn (array $e): string => $e['pattern'],
                array_filter($result['model'], static fn (array $e): bool => $e['permission'] === 'bash')
            )), "agent '{$agent}' composed model is missing the bash '*' floor entry");
        }
    }

    public function testResearcherEditSurfaceCoversResearchSessionsAndTickets(): void
    {
        $composition = aiPermissionAgentCompositions()['researcher'];
        $result = aiPermissionComposeFromSpec($composition['compose_spec']);

        $editPatterns = array_map(
            static fn (array $e): string => $e['pattern'],
            array_filter($result['model'], static fn (array $e): bool => $e['permission'] === 'edit' && $e['effect'] === 'allow')
        );

        self::assertContains('.opencode/research-sessions/**', $editPatterns);
        self::assertContains('docs/tickets/**', $editPatterns);
    }

    public function testResearcherNoLongerGrantsShellAppendWritePatterns(): void
    {
        foreach (['.opencode/agents/researcher.md', 'packages/ai-universal-rules/templates/core/agents/researcher.md'] as $relative) {
            $content = (string) file_get_contents(self::$repoRoot . '/' . $relative);
            foreach (['mkdir -p .opencode/research-sessions', 'mkdir -p docs/tickets', 'printf * >> .opencode/research-sessions', 'printf * >> docs/tickets', 'cat >> .opencode/research-sessions', 'cat >> docs/tickets'] as $needle) {
                self::assertStringNotContainsString($needle, $content, "{$relative} still contains shell-append pattern: {$needle}");
            }
        }
    }
}
