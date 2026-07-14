<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P5-a: ship docs/ai/project/ with exactly three user-owned templates.
 *
 * The kit ships README.md, project-interaction.md, and conventions.md under
 * docs/ai/project/ as the `template` ownership class (skip-if-exists), so they are
 * installed once and then user-owned (never overwritten on upgrade).
 */
final class ProjectTemplatesTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        self::$repoRoot = $root;
        require_once $root . '/tools/ai/install/registry.php';
        require_once $root . '/tools/ai/install/manifest.php';
    }

    /** @return list<string> */
    private function projectTemplateTargets(): array
    {
        return [
            'docs/ai/project/README.md',
            'docs/ai/project/project-interaction.md',
            'docs/ai/project/conventions.md',
        ];
    }

    public function testThreeProjectTemplateSourceFilesShip(): void
    {
        foreach ([
            'packages/ai-universal-rules/templates/core/project/README.md',
            'packages/ai-universal-rules/templates/core/project/project-interaction.md',
            'packages/ai-universal-rules/templates/core/project/conventions.md',
        ] as $rel) {
            $this->assertFileExists(self::$repoRoot . '/' . $rel, "missing project template source: {$rel}");
        }
    }

    public function testProjectTemplatesAreRegisteredAsTemplateClass(): void
    {
        $registry = aiInstallerPackRegistry();
        $byTarget = [];
        foreach ($registry as $items) {
            foreach ($items as $item) {
                $byTarget[(string) ($item['target'] ?? '')] = $item;
            }
        }

        foreach ($this->projectTemplateTargets() as $target) {
            $this->assertArrayHasKey($target, $byTarget, "project template not registered: {$target}");
            $item = $byTarget[$target];
            $this->assertSame('skip-if-exists', $item['merge_strategy'] ?? null, "{$target} must be skip-if-exists (template class)");
            $this->assertSame('template', aiInstallerResolveOwnership($item, (string) $item['merge_strategy']), "{$target} must resolve to template ownership");
        }
    }

    public function testExactlyThreeProjectTemplatesAreShipped(): void
    {
        $registry = aiInstallerPackRegistry();
        $projectTargets = [];
        foreach ($registry as $items) {
            foreach ($items as $item) {
                $target = (string) ($item['target'] ?? '');
                if (str_starts_with($target, 'docs/ai/project/')) {
                    $projectTargets[$target] = true;
                }
            }
        }

        $this->assertCount(3, $projectTargets, 'exactly three docs/ai/project/ templates must ship: ' . implode(', ', array_keys($projectTargets)));
    }
}
