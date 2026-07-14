<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for `validateAdapterScriptReferences()` in
 * `tools/ai/validate-install-surface.php` (see
 * docs/tickets/IDEAS/plan-script-reference-validator-fix.md).
 *
 * The validator file runs a CLI at include time with no include guard, so the function cannot
 * be unit-called directly; these tests drive it end-to-end via `proc_open` (same pattern as
 * `ShipReferenceIntegrityTest`) using a throwaway fixture placed into a scanned directory and
 * removed in `finally` (same mutate-and-restore pattern as `AdapterRenderDriftTest`).
 *
 * What this adds over `ShipReferenceIntegrityTest::testShippedSurfaceHasNoBrokenDocReferences`
 * (which only asserts the real tree validates clean): it proves the two behaviors the fix
 * restores — (a) files containing 2+ script references are actually scanned (the guard bug
 * skipped them), and (b) `.claude/agents/*.md` is part of the scan set. A single multi-reference
 * fixture under `.claude/agents/` exercises both: if either regresses, the planted unregistered
 * reference goes unreported and the assertion fails.
 */
final class InstallSurfaceScriptReferenceTest extends TestCase
{
    private static string $repoRoot;
    private static string $validator;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$validator = $root . '/tools/ai/validate-install-surface.php';
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

    public function testMultiReferenceClaudeAgentFileWithUnregisteredScriptIsCaught(): void
    {
        // A rendered Claude agent body that lists several scripts, one of them unregistered.
        // Two references means the pre-fix `preg_match_all(...) !== 1` guard would skip the file
        // entirely; `.claude/agents` also has to be in the scan set for it to be seen at all.
        $fixture = self::$repoRoot . '/.claude/agents/__script-ref-regression-fixture__.md';
        $unregistered = 'scripts/ai/__parity-regression-unregistered__.sh';
        $body = "# Fixture\n\n"
            . "- `bash scripts/ai/ai-search.sh text x . --fixed` — a registered script.\n"
            . "- `bash {$unregistered} x` — deliberately not in the registry.\n";
        file_put_contents($fixture, $body);

        try {
            $result = $this->runValidator();
            $this->assertStringContainsString(
                $unregistered,
                $result['stderr'],
                "validator must scan multi-reference .claude/agents bodies and flag the "
                . "unregistered script; if this fails, the guard or the .claude/agents scan-set "
                . "regressed.\nstdout:\n{$result['stdout']}\nstderr:\n{$result['stderr']}"
            );
            $this->assertNotSame(0, $result['exit'], 'an unregistered reference must fail validation');
        } finally {
            @unlink($fixture);
        }
    }

    public function testTestsPathScriptReferenceIsNotFalseFlagged(): void
    {
        // `tests/scripts/ai/<name>.sh` is a legitimate test script, NOT a public agent script.
        // The anchored regex must not match the `scripts/ai/...` substring inside that path.
        $fixture = self::$repoRoot . '/.claude/agents/__script-ref-lookbehind-fixture__.md';
        file_put_contents(
            $fixture,
            "# Fixture\n\n- `bash tests/scripts/ai/__lookbehind-probe__.sh` — a test script path.\n"
        );

        try {
            $result = $this->runValidator();
            $this->assertStringNotContainsString(
                'scripts/ai/__lookbehind-probe__.sh',
                $result['stderr'],
                "a tests/scripts/ai/* reference must not be false-flagged as an unregistered "
                . "scripts/ai/* script (regex lookbehind).\nstderr:\n{$result['stderr']}"
            );
        } finally {
            @unlink($fixture);
        }
    }
}
