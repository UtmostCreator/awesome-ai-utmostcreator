<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Bidirectional ship-reference integrity.
 *
 * Direction A — referenced-but-not-shipped: every repo-relative doc path in the shipped
 *   opencode.json instructions[] must be installed by some pack.
 * Direction B — dangling shipped link: every relative docs/ai/** link inside a shipped
 *   docs/ai/** markdown must itself be an installed target.
 *
 * Regression guard for the bug where opencode.jsonc pointed at docs/ai/tools/{ai-search,tool-map}.md
 * and docs/ai/tools/actions/search-evidence.md while the installer shipped only preview-file.md.
 */
final class ShipReferenceIntegrityTest extends TestCase
{
    private static string $repoRoot;
    private static string $validator;
    private static string $opencodeTemplate;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$validator = $root . '/tools/ai/validate-install-surface.php';
        self::$opencodeTemplate = $root . '/packages/ai-universal-rules/templates/core/opencode.json';
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runValidator(): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = escapeshellarg((string) PHP_BINARY) . ' ' . escapeshellarg(self::$validator) . ' --strict';
        $process = proc_open($cmd, $descriptors, $pipes, self::$repoRoot);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    public function testShippedSurfaceHasNoBrokenDocReferences(): void
    {
        $result = $this->runValidator();
        $this->assertSame(0, $result['exit'], "ship-reference integrity failed:\n" . $result['stderr']);
    }

    public function testEveryOpencodeInstructionIsShipped(): void
    {
        $raw = (string) file_get_contents(self::$opencodeTemplate);
        $stripped = preg_replace('~^\s*//.*$~m', '', $raw) ?? $raw;
        $decoded = json_decode($stripped, true);
        $this->assertIsArray($decoded, 'opencode.json template must parse as JSON after comment stripping');
        $this->assertArrayHasKey('instructions', $decoded);

        // The three docs that were previously referenced-but-not-shipped must now be present.
        $required = [
            'docs/ai/tools/ai-search.md',
            'docs/ai/tools/tool-map.md',
            'docs/ai/tools/actions/search-evidence.md',
            'docs/ai/tools/actions/preview-file.md',
        ];
        foreach ($required as $path) {
            $this->assertContains($path, $decoded['instructions'], "opencode.json must list {$path}");
        }
    }

    public function testValidatorDetectsReferencedButNotShippedInstruction(): void
    {
        $original = (string) file_get_contents(self::$opencodeTemplate);
        // Insert a bogus instruction path that no pack ships.
        $mutated = str_replace(
            '"AGENTS.md",',
            '"AGENTS.md",' . "\n    \"docs/ai/tools/this-doc-is-not-shipped.md\",",
            $original
        );
        $this->assertNotSame($original, $mutated, 'precondition: AGENTS.md anchor must exist in template');

        file_put_contents(self::$opencodeTemplate, $mutated);
        try {
            $result = $this->runValidator();
            $this->assertSame(1, $result['exit'], 'validator must reject an unshipped instruction reference');
            $this->assertStringContainsString('not shipped by any pack', $result['stderr']);
            $this->assertStringContainsString('this-doc-is-not-shipped.md', $result['stderr']);
        } finally {
            file_put_contents(self::$opencodeTemplate, $original);
        }

        // Confirm restoration leaves the surface clean again.
        $this->assertSame(0, $this->runValidator()['exit']);
    }

    public function testValidatorDetectsDanglingShippedDocLink(): void
    {
        $toolMap = self::$repoRoot . '/packages/ai-universal-rules/templates/docs/ai/tools/tool-map.md';
        $this->assertFileExists($toolMap);
        $original = (string) file_get_contents($toolMap);
        // Append a See-Also link to a docs/ai path that is not shipped.
        $mutated = $original . "\n- `docs/ai/tools/actions/nonexistent-action.md`\n";

        file_put_contents($toolMap, $mutated);
        try {
            $result = $this->runValidator();
            $this->assertSame(1, $result['exit'], 'validator must reject a dangling shipped-doc link');
            $this->assertStringContainsString('references an unshipped path', $result['stderr']);
            $this->assertStringContainsString('nonexistent-action.md', $result['stderr']);
        } finally {
            file_put_contents($toolMap, $original);
        }

        $this->assertSame(0, $this->runValidator()['exit']);
    }
}
