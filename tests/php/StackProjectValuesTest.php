<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Slice 4 of docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md:
 * write-through of selected/detected stacks into .ai/project.yml scalar summaries and
 * the informational .ai/stack-detection.json evidence file.
 */
final class StackProjectValuesTest extends TestCase
{
    private static string $repoRoot;
    /** @var list<string> */
    private array $tmpDirs = [];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new RuntimeException('Could not resolve repo root.');
        }
        self::$repoRoot = $root;
        require_once $root . '/tools/ai/install/markers.php';
        require_once $root . '/tools/ai/install/core.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            $this->removeTree($dir);
        }
        $this->tmpDirs = [];
    }

    public function testWriteThroughFillsUnknownScalarFieldsFromResolvedStacks(): void
    {
        $root = $this->makeTempRoot();
        \aiInstallerEnsureProjectValuesFile($root, 'demo');

        $resolved = [
            'selected' => ['php', 'markdown'],
            'detected' => [
                'php' => ['id' => 'php', 'confidence' => 95, 'signals' => ['composer.json']],
                'markdown' => ['id' => 'markdown', 'confidence' => 60, 'signals' => ['README.md']],
            ],
            'versions' => [
                'php' => ['id' => 'php', 'tool' => 'php', 'available' => true, 'output' => 'PHP 8.4.0', 'error' => '', 'required' => false],
            ],
        ];

        \aiInstallerApplyStackSelectionToProjectValues($root, $resolved);
        $values = \aiInstallerLoadProjectValues($root, 'demo');

        self::assertSame('php,markdown', $values['selectedStacks']);
        self::assertSame('php:95,markdown:60', $values['detectedStacks']);
        self::assertSame('php', $values['primaryStack']);
        self::assertStringContainsString('php=', $values['stackToolVersions']);
    }

    public function testPrimaryStackPicksHighestConfidenceNotAlphabeticalFirst(): void
    {
        // 'markdown' sorts before 'php' alphabetically but has lower confidence;
        // primaryStack must pick 'php'.
        $primary = \aiInstallerPrimaryStack([
            'selected' => ['markdown', 'php', 'shell'],
            'detected' => [
                'markdown' => ['id' => 'markdown', 'confidence' => 25, 'signals' => ['implied:php']],
                'php' => ['id' => 'php', 'confidence' => 95, 'signals' => ['composer.json']],
                'shell' => ['id' => 'shell', 'confidence' => 25, 'signals' => ['implied:php']],
            ],
        ]);

        self::assertSame('php', $primary);
    }

    public function testPrimaryStackTiesBreakAlphabetically(): void
    {
        $primary = \aiInstallerPrimaryStack([
            'selected' => ['rust', 'go'],
            'detected' => [
                'rust' => ['id' => 'rust', 'confidence' => 95, 'signals' => ['Cargo.toml']],
                'go' => ['id' => 'go', 'confidence' => 95, 'signals' => ['go.mod']],
            ],
        ]);

        self::assertSame('go', $primary);
    }

    public function testWriteThroughNeverOverwritesExplicitUserValue(): void
    {
        $root = $this->makeTempRoot();
        \aiInstallerEnsureProjectValuesFile($root, 'demo');

        // Simulate a user-set explicit value by writing it directly.
        $path = $root . '/.ai/project.yml';
        file_put_contents($path, file_get_contents($path) . "\nselectedStacks: \"go\"\n");

        \aiInstallerApplyStackSelectionToProjectValues($root, [
            'selected' => ['php'],
            'detected' => ['php' => ['id' => 'php', 'confidence' => 95, 'signals' => ['composer.json']]],
            'versions' => [],
        ]);

        $values = \aiInstallerLoadProjectValues($root, 'demo');
        self::assertSame('go', $values['selectedStacks'], 'explicit user value must not be overwritten');
    }

    public function testNoDetectedOrSelectedStacksLeavesFileUntouched(): void
    {
        $root = $this->makeTempRoot();
        \aiInstallerEnsureProjectValuesFile($root, 'demo');
        $before = file_get_contents($root . '/.ai/project.yml');

        \aiInstallerApplyStackSelectionToProjectValues($root, ['selected' => [], 'detected' => [], 'versions' => []]);

        self::assertSame($before, file_get_contents($root . '/.ai/project.yml'));
    }

    public function testWriteStackDetectionEvidenceProducesInformationalJson(): void
    {
        $root = $this->makeTempRoot();
        \aiInstallerWriteStackDetectionEvidence($root, [
            'selected' => ['php'],
            'detected' => ['php' => ['id' => 'php', 'confidence' => 95, 'signals' => ['composer.json']]],
            'versions' => [],
        ]);

        $path = $root . '/.ai/stack-detection.json';
        self::assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($decoded);
        self::assertTrue($decoded['informational']);
        self::assertTrue($decoded['not_a_write_allowlist']);
        self::assertSame(['php'], $decoded['selected']);
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/ai-stack-project-values-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
