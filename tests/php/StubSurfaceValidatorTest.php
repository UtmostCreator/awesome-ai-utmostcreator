<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 10: StubValidator.
 *
 * Verifies the content-based stub detection (markdown body, shell statements) and that the
 * repository itself is free of phantom stub surfaces.
 */
final class StubSurfaceValidatorTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        require_once self::$repoRoot . '/tools/ai/validate-stub-surfaces.php';
    }

    public function testMarkdownBodyDetection(): void
    {
        // Heading-only / comment-only / empty -> stub.
        $this->assertTrue(aiStubIsMarkdownStub("# Title\n"));
        $this->assertTrue(aiStubIsMarkdownStub("# Title\n\n<!-- nothing -->\n"));
        $this->assertTrue(aiStubIsMarkdownStub(""));

        // Real body -> not a stub.
        $this->assertFalse(aiStubIsMarkdownStub("# Title\n\nA real paragraph.\n"));
        $this->assertFalse(aiStubIsMarkdownStub("# Title\n\n- a list item\n"));
        $this->assertFalse(aiStubIsMarkdownStub("# Title\n\n> a quote\n"));
    }

    public function testMarkdownStubMarkerLines(): void
    {
        // A line that is *only* a stub marker counts as a stub...
        $this->assertTrue(aiStubIsMarkdownStub("# Title\n\nTODO\n"));
        $this->assertTrue(aiStubIsMarkdownStub("# Title\n\n_(stub)_\n"));

        // ...but prose that merely mentions the word must NOT be a stub.
        $this->assertFalse(aiStubIsMarkdownStub("# Placeholders\n\nReplace each placeholder token before use.\n"));
        $this->assertFalse(aiStubIsMarkdownStub("# Tasks\n\nThe TODO list is tracked in the issue tracker.\n"));
    }

    public function testShellStubDetection(): void
    {
        // Shebang + set + comments only -> stub.
        $this->assertTrue(aiStubIsShellStub("#!/usr/bin/env bash\nset -euo pipefail\n# coming soon\n"));
        $this->assertTrue(aiStubIsShellStub("#!/usr/bin/env bash\n"));

        // Has a real statement -> not a stub (thin wrappers are legitimate).
        $this->assertFalse(aiStubIsShellStub("#!/usr/bin/env bash\nset -e\nexec php x.php \"\$@\"\n"));
        $this->assertFalse(aiStubIsShellStub("#!/usr/bin/env bash\nset -euo pipefail\ngit ls-files | wc -l\n"));
    }

    public function testExclusionRules(): void
    {
        $this->assertTrue(aiStubIsExcluded('docs/ai/generated/x.md'));
        $this->assertTrue(aiStubIsExcluded('vendor/pkg/y.sh'));
        $this->assertTrue(aiStubIsExcluded('tests/fixtures/php/frontmatter-empty.md'));
        $this->assertFalse(aiStubIsExcluded('docs/ai/project-context.md'));
    }

    public function testRepositoryHasNoPhantomStubs(): void
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = escapeshellarg((string) PHP_BINARY) . ' '
            . escapeshellarg(self::$repoRoot . '/tools/ai/validate-stub-surfaces.php')
            . ' --root=' . escapeshellarg(self::$repoRoot);
        $process = proc_open($cmd, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        $this->assertSame(0, $exit, "repository must be free of phantom stub surfaces:\n" . $stderr . $stdout);
    }
}
