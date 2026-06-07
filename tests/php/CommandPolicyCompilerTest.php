<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Phase 7: policy compiler (command-policy.tiers.yaml -> dependency-free compiled sh).
 *
 * Verifies the compiler's pure functions (YAML parse, case-glob translation) and the runtime
 * behaviour of the compiled guard (deny > ask > allow > default), plus that the committed
 * compiled artifact is not stale.
 */
final class CommandPolicyCompilerTest extends TestCase
{
    private static string $repoRoot;
    private static string $compiler;
    private static string $compiled;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$compiler = $root . '/tools/ai/compile-command-policy.php';
        self::$compiled = $root . '/.github/hooks/scripts/command-policy.compiled.sh';
        require_once self::$compiler;
    }

    public function testTiersYamlParserExtractsBuckets(): void
    {
        $yaml = "tiers:\n  tier0:\n    allow:\n      - \"pwd\"\n    ask: []\n    deny: []\n  tier4:\n    allow: []\n    ask: []\n    deny:\n      - \"rm *\"\n";
        $parsed = aiPolicyParseTiersYaml($yaml);

        $this->assertSame(['pwd'], $parsed['allow']);
        $this->assertSame([], $parsed['ask']);
        $this->assertSame(['rm *'], $parsed['deny']);
    }

    public function testPatternToCaseGlobQuotesLiteralsAndKeepsWildcard(): void
    {
        $this->assertSame('"rm "*', aiPolicyPatternToCaseGlob('rm *'));
        $this->assertSame('"git status"*', aiPolicyPatternToCaseGlob('git status*'));
        $this->assertSame('"pwd"', aiPolicyPatternToCaseGlob('pwd'));
        $this->assertSame('*', aiPolicyPatternToCaseGlob('*'));
        // Glob metacharacters in the literal must be quoted, not treated as wildcards.
        $this->assertSame('"a?b["*', aiPolicyPatternToCaseGlob('a?b[*'));
    }

    public function testRenderedShHasDenyBeforeAllowPrecedence(): void
    {
        $sh = aiPolicyRenderCompiledSh([
            'allow' => ['git status*'],
            'ask' => ['git commit*'],
            'deny' => ['rm *'],
        ]);

        $denyPos = strpos($sh, '"rm "*)');
        $askPos = strpos($sh, '"git commit"*)');
        $allowPos = strpos($sh, '"git status"*)');

        $this->assertIsInt($denyPos);
        $this->assertIsInt($askPos);
        $this->assertIsInt($allowPos);
        $this->assertLessThan($askPos, $denyPos, 'deny must precede ask');
        $this->assertLessThan($allowPos, $askPos, 'ask must precede allow');
    }

    public function testCommittedCompiledArtifactIsUpToDate(): void
    {
        $result = $this->runCli(escapeshellarg((string) PHP_BINARY) . ' ' . escapeshellarg(self::$compiler) . ' --check');
        $this->assertSame(0, $result['exit'], "compiled command policy is stale; run: php tools/ai/compile-command-policy.php\n" . $result['stderr']);
    }

    public function testCompiledGuardEnforcesDecisions(): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('compiled guard runtime test requires a POSIX shell');
        }
        $this->assertFileExists(self::$compiled);

        $deny = $this->pipeToGuard('rm -rf build');
        $this->assertSame(1, $deny['exit']);
        $this->assertStringStartsWith('deny:', trim($deny['stdout']));

        $ask = $this->pipeToGuard('git commit -m x');
        $this->assertSame(0, $ask['exit']);
        $this->assertStringStartsWith('ask:', trim($ask['stdout']));

        $allow = $this->pipeToGuard('git status');
        $this->assertSame(0, $allow['exit']);
        $this->assertSame('allow', trim($allow['stdout']));

        $default = $this->pipeToGuard('echo hello world');
        $this->assertSame(0, $default['exit']);
        $this->assertSame('allow', trim($default['stdout']));
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function pipeToGuard(string $command): array
    {
        return $this->runCliStdin('sh ' . escapeshellarg(self::$compiled), $command);
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runCli(string $command): array
    {
        return $this->runCliStdin($command, null);
    }

    /** @return array{stdout:string,stderr:string,exit:int} */
    private function runCliStdin(string $command, ?string $stdin): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = ['PATH' => (string) getenv('PATH'), 'HOME' => sys_get_temp_dir()];
        $process = proc_open($command, $descriptors, $pipes, self::$repoRoot, $env);
        $this->assertIsResource($process);

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }
}
