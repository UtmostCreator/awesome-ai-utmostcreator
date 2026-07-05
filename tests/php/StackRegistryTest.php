<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/install/stack-registry.php';

final class StackRegistryTest extends TestCase
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

    public function testLoadsInitialShippedStackDescriptors(): void
    {
        $registry = aiStackLoadRegistry(null, self::$repoRoot);

        foreach (['php', 'js-ts', 'shell', 'markdown', 'github-actions'] as $id) {
            self::assertArrayHasKey($id, $registry);
            self::assertSame($id, $registry[$id]['id']);
            self::assertSame('shipped', $registry[$id]['_source']);
        }
    }

    public function testLoadsAllTwelveShippedStackDescriptors(): void
    {
        $registry = aiStackLoadRegistry(null, self::$repoRoot);

        $expected = ['php', 'js-ts', 'python', 'go', 'rust', 'java', 'dotnet', 'ruby', 'shell', 'markdown', 'github-actions', 'make'];
        foreach ($expected as $id) {
            self::assertArrayHasKey($id, $registry, "expected shipped stack '{$id}' to load");
        }
        self::assertCount(count($expected), $registry);
    }

    public function testGeneratedStackRegistryProjectionIsUpToDate(): void
    {
        $result = shell_exec('php ' . escapeshellarg(self::$repoRoot . '/tools/ai/generate-stack-registry.php') . ' --check 2>&1');
        self::assertIsString($result);
        self::assertStringContainsString('up to date', (string) $result, "Generator check failed:\n{$result}");
    }

    public function testDescriptorValidationRejectsUnsafeVersionCommandArgs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsafe version check args');

        aiStackNormalizeDescriptor([
            'schema_version' => 1,
            'id' => 'unsafe',
            'label' => 'Unsafe',
            'detection' => [],
            'permission_overlays' => ['language_overlays' => []],
            'project_context' => [],
            'version_checks' => [
                ['id' => 'bad', 'tool' => 'npm', 'args' => ['run', 'build'], 'required' => false],
            ],
        ]);
    }

    public function testLocalDuplicateRequiresExplicitOverride(): void
    {
        $root = $this->makeTempRoot();
        mkdir($root . '/.ai/stacks', 0777, true);
        file_put_contents($root . '/.ai/stacks/php.json', json_encode([
            'schema_version' => 1,
            'id' => 'php',
            'label' => 'Local PHP',
            'detection' => ['files' => ['composer.json']],
            'permission_overlays' => ['language_overlays' => ['php']],
            'project_context' => ['primaryLanguage' => 'PHP'],
        ], JSON_PRETTY_PRINT));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate stack id without explicit local override');

        aiStackLoadRegistry($root, self::$repoRoot);
    }

    public function testLocalDescriptorCanExplicitlyOverrideShippedDescriptor(): void
    {
        $root = $this->makeTempRoot();
        mkdir($root . '/.ai/stacks', 0777, true);
        file_put_contents($root . '/.ai/stacks/php.json', json_encode([
            'schema_version' => 1,
            'id' => 'php',
            'label' => 'Local PHP',
            'override' => true,
            'detection' => ['files' => ['composer.json']],
            'permission_overlays' => ['language_overlays' => ['php']],
            'project_context' => ['primaryLanguage' => 'PHP Local'],
        ], JSON_PRETTY_PRINT));

        $registry = aiStackLoadRegistry($root, self::$repoRoot);

        self::assertSame('Local PHP', $registry['php']['label']);
        self::assertSame('local', $registry['php']['_source']);
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-stack-registry-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        return $dir;
    }
}
