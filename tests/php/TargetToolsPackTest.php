<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class TargetToolsPackTest extends TestCase
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
    }

    public function testSourceRepoInstallCheckBatchesDoNotShipToTargets(): void
    {
        $registry = aiInstallerPackRegistry();
        $this->assertArrayHasKey('target-tools-pack', $registry);

        foreach ($registry['target-tools-pack'] as $item) {
            $source = str_replace('\\', '/', (string) ($item['source'] ?? ''));
            $target = str_replace('\\', '/', (string) ($item['target'] ?? ''));

            $this->assertNotSame('tools/ai/install', $source, 'target-tools-pack must not ship the whole install dir');
            $this->assertFalse(str_starts_with($source, 'tools/ai/install/checks/'), "source-repo check batch must not ship: {$source}");
            $this->assertFalse(str_starts_with($target, 'tools/ai/install/checks/'), "source-repo check batch must not install: {$target}");
        }

        $sources = array_map(static fn(array $item): string => (string) ($item['source'] ?? ''), $registry['target-tools-pack']);
        $this->assertContains('tools/ai/install/core.php', $sources, 'installer core must still ship');
        $this->assertContains('tools/ai/install/base.sh', $sources, 'installer shell runtime must still ship');
    }
}
