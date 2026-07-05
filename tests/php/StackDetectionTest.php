<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/install/stack-detection.php';

final class StackDetectionTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new RuntimeException('Could not resolve repo root.');
        }
        self::$repoRoot = $root;
    }

    public function testDetectsComposerJsonAsPhpStack(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/composer.json', '{}');
        $detected = aiStackDetect($root, aiStackLoadRegistry(null, self::$repoRoot));

        self::assertArrayHasKey('php', $detected);
        self::assertGreaterThanOrEqual(90, $detected['php']['confidence']);
        self::assertContains('composer.json', $detected['php']['signals']);
        self::assertArrayHasKey('markdown', $detected, 'PHP descriptor implies markdown.');
        self::assertArrayHasKey('shell', $detected, 'PHP descriptor implies shell.');
    }

    public function testDetectsPackageJsonAsJsTsStack(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/package.json', '{}');
        $detected = aiStackDetect($root, aiStackLoadRegistry(null, self::$repoRoot));

        self::assertArrayHasKey('js-ts', $detected);
        self::assertGreaterThanOrEqual(90, $detected['js-ts']['confidence']);
    }

    public function testDetectsGithubActionsWorkflowGlob(): void
    {
        $root = $this->makeTempRoot();
        mkdir($root . '/.github/workflows', 0777, true);
        file_put_contents($root . '/.github/workflows/ci.yml', 'name: CI');
        $detected = aiStackDetect($root, aiStackLoadRegistry(null, self::$repoRoot));

        self::assertArrayHasKey('github-actions', $detected);
        self::assertContains('.github/workflows/*.yml', $detected['github-actions']['signals']);
    }

    public function testMissingSccDoesNotFailDetection(): void
    {
        $root = $this->makeTempRoot();
        file_put_contents($root . '/README.md', '# Test');
        $detected = aiStackDetect($root, aiStackLoadRegistry(null, self::$repoRoot), ['use_scc' => true]);

        self::assertArrayHasKey('markdown', $detected);
    }

    public function testMergeSelectionsAddsSelectedAndRemovesDisabled(): void
    {
        $merged = aiStackMergeSelections(['php' => ['id' => 'php', 'confidence' => 95, 'signals' => ['composer.json']]], ['js-ts'], ['php']);

        self::assertSame(['js-ts'], $merged);
    }

    public function testVersionChecksUseStructuredSafeCommands(): void
    {
        $results = aiStackRunVersionChecks(self::$repoRoot, [[
            'version_checks' => [
                ['id' => 'php', 'tool' => 'php', 'args' => ['-v'], 'required' => false],
            ],
        ]]);

        self::assertArrayHasKey('php', $results);
        self::assertSame('php', $results['php']['tool']);
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-stack-detection-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        return $dir;
    }
}
