<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Characterization guard for the packs.php normalizer refactor.
 *
 * aiInstallerPackRegistry() is built from aiInstallerRegistryEntry() plus
 * domain-specific registry functions merged by aiInstallerMergePackRegistries().
 * During the refactor a frozen aiInstallerLegacyPackRegistry() baseline proved
 * the new output was content-identical (per pack, per entry, key-order
 * insensitive). That legacy baseline has since been removed; these tests pin the
 * observable invariants so future edits to the registry stay honest:
 *
 * - the merge aggregator rejects duplicate pack names,
 * - every normalized entry carries the full required field set,
 * - the string shorthand defaults target to source,
 * - the .opencode/commands clear-first entry order is preserved.
 */
final class PackRegistryRefactorParityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        require_once $root . '/tools/ai/install/packs.php';
    }

    public function testShorthandUsesSourceAsDefaultTarget(): void
    {
        self::assertSame(
            [
                'type' => 'file',
                'source' => 'docs/ai/index.md',
                'target' => 'docs/ai/index.md',
                'core' => false,
                'merge_strategy' => 'replace',
                'required' => true,
            ],
            aiInstallerRegistryEntry('docs/ai/index.md'),
        );
    }

    public function testDefaultsAndOverridesCompose(): void
    {
        $entry = aiInstallerRegistryEntry(
            ['source' => 'a/b', 'never_auto_merge' => true],
            ['type' => 'dir', 'required' => false],
        );

        self::assertSame(
            [
                'type' => 'dir',
                'source' => 'a/b',
                'target' => 'a/b',
                'core' => false,
                'merge_strategy' => 'replace',
                'required' => false,
                'never_auto_merge' => true,
            ],
            $entry,
        );
    }

    public function testInvalidTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        aiInstallerRegistryEntry(['source' => 'x', 'type' => 'symlink']);
    }

    public function testInvalidMergeStrategyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        aiInstallerRegistryEntry(['source' => 'x', 'merge_strategy' => 'overwrite']);
    }

    public function testMergeRejectsDuplicatePackNames(): void
    {
        $this->expectException(\LogicException::class);
        aiInstallerMergePackRegistries(
            ['dup' => []],
            ['dup' => []],
        );
    }

    public function testEveryEntryHasTheRequiredFieldSet(): void
    {
        foreach (aiInstallerPackRegistry() as $packName => $entries) {
            foreach ($entries as $index => $entry) {
                foreach (['type', 'source', 'target', 'core', 'merge_strategy', 'required'] as $field) {
                    self::assertArrayHasKey(
                        $field,
                        $entry,
                        "Pack {$packName} entry {$index} is missing {$field}.",
                    );
                }
                self::assertContains($entry['type'], ['file', 'dir']);
                self::assertContains($entry['merge_strategy'], ['replace', 'skip-if-exists']);
            }
        }
    }

    public function testOpencodeCommandsClearFirstOrderIsPreserved(): void
    {
        $registry = aiInstallerPackRegistry();
        $commandTargets = [];
        foreach ($registry['adapter-opencode'] as $entry) {
            if (($entry['target'] ?? '') === '.opencode/commands') {
                $commandTargets[] = (string) $entry['source'];
            }
        }

        // The workflows source must clear .opencode/commands first, then the
        // commands source merges into it without clearing (see executor.php).
        self::assertSame(
            [
                'packages/ai-universal-rules/templates/workflows',
                'packages/ai-universal-rules/templates/commands',
            ],
            $commandTargets,
        );
    }
}
