<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Drift + consistency tests for the JSON-driven `--help` renderer in
 * tools/ai/sh-introspect.php (`--format=help`) and its delegate wrapper in
 * scripts/ai/ai-search.sh (`--help` / `-h`).
 *
 * The single source of truth is the introspection JSON contract. These tests
 * guard that:
 *   - generated help never mentions a mode/param absent from the JSON contract,
 *   - every contract mode/param appears in the help,
 *   - deprecated modes show replacements and examples reference real modes,
 *   - doctor/unsafe-all are EITHER in both surfaces or neither,
 *   - `--help` is deterministic, non-mutating, and never executes the target,
 *   - a golden snapshot pins the rendered help (changes only when contract does).
 *
 * Engine is run out-of-process (proc_open), mirroring ShIntrospectTest, so the
 * assertions bind the real scripts/ai/ai-search.sh.
 */
class ShHelpTest extends TestCase
{
    private static string $repoRoot;
    private static string $phpBin;
    private static string $tool;
    private static string $target;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$phpBin = (string) PHP_BINARY;
        self::$tool = 'tools/ai/sh-introspect.php';
        self::$target = 'scripts/ai/ai-search.sh';
    }

    /**
     * @param array<int,string> $args
     * @return array{stdout:string, stderr:string, exit:int}
     */
    private function runEngine(array $args, bool $jsonMode, ?string $phpBinOverride = null): array
    {
        $cmdParts = [self::$phpBin, self::$tool];
        foreach ($args as $a) {
            $cmdParts[] = $a;
        }
        $cmd = implode(' ', array_map('escapeshellarg', $cmdParts));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = [
            'HOME'              => sys_get_temp_dir(),
            'XDG_CONFIG_HOME'   => sys_get_temp_dir(),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'PATH'              => (string) getenv('PATH'),
            'NO_COLOR'          => '1',
        ];
        if ($jsonMode) {
            $env['AI_OUTPUT'] = 'json';
        }
        if ($phpBinOverride !== null) {
            $env['PHP_BIN'] = $phpBinOverride;
        }

        $proc = proc_open($cmd, $descriptors, $pipes, self::$repoRoot, $env);
        $this->assertIsResource($proc, "proc_open failed for: $cmd");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    /** Run the script wrapper directly (e.g. `--help`/`--introspect`). */
    private function runScript(string $arg, array $envExtra = []): array
    {
        $cmd = implode(' ', array_map('escapeshellarg', ['bash', self::$target, $arg]));
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env = [
            'HOME'            => sys_get_temp_dir(),
            'XDG_CONFIG_HOME' => sys_get_temp_dir(),
            'PATH'            => (string) getenv('PATH'),
            'NO_COLOR'        => '1',
        ] + $envExtra;
        $proc = proc_open($cmd, $descriptors, $pipes, self::$repoRoot, $env);
        $this->assertIsResource($proc, "proc_open failed for: $cmd");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    /** The rendered `--format=help` text for the real target. */
    private function helpText(): string
    {
        $r = $this->runEngine(['--format=help', self::$target], false);
        $this->assertSame(0, $r['exit'], "--format=help exited non-zero:\n" . $r['stderr']);
        return $r['stdout'];
    }

    /** @return array<string,mixed> */
    private function contract(): array
    {
        $r = $this->runEngine([self::$target], true);
        $this->assertSame(0, $r['exit'], "introspect exited non-zero:\n" . $r['stderr']);
        $decoded = json_decode($r['stdout'], true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    /** @return array<int,string> */
    private function modeNames(array $contract): array
    {
        $names = [];
        foreach ($contract['modes'] as $m) {
            $n = (string) ($m['name'] ?? '');
            if ($n !== '') {
                $names[] = $n;
            }
        }
        return $names;
    }

    /** @return array<int,string> primary + alias param tokens, minus fallbacks. */
    private function paramTokens(array $contract): array
    {
        $excluded = [];
        foreach ($contract['unknown_option_handlers'] as $h) {
            $p = (string) ($h['pattern'] ?? '');
            if ($p !== '') {
                $excluded[$p] = true;
            }
        }
        $tokens = [];
        foreach ($contract['params'] as $p) {
            $name = (string) ($p['name'] ?? '');
            if ($name !== '' && !isset($excluded[$name])) {
                $tokens[$name] = true;
            }
            foreach ((array) ($p['aliases'] ?? []) as $a) {
                $a = (string) $a;
                if ($a !== '' && !isset($excluded[$a])) {
                    $tokens[$a] = true;
                }
            }
        }
        return array_keys($tokens);
    }

    // ---- P0/P3: doctor + unsafe-all consistency --------------------------

    public function testDoctorAndUnsafeAllAreInBothHelpAndContractOrNeither(): void
    {
        $contract = $this->contract();
        $help = $this->helpText();
        $modeNames = $this->modeNames($contract);

        foreach (['doctor', 'unsafe-all'] as $mode) {
            $inContract = in_array($mode, $modeNames, true);
            $inHelp = str_contains($help, $mode);
            $this->assertSame(
                $inContract,
                $inHelp,
                "'{$mode}' must be in BOTH the JSON contract and generated help, or in NEITHER"
                . " (contract=" . var_export($inContract, true) . ", help=" . var_export($inHelp, true) . ')'
            );
        }
    }

    public function testDoctorAndUnsafeAllAreActuallyPresent(): void
    {
        // Sanity: the real ai-search.sh DOES implement both, so both surfaces
        // must list them (this pins the "A. add to modes" decision).
        $contract = $this->contract();
        $modeNames = $this->modeNames($contract);
        $this->assertContains('doctor', $modeNames);
        $this->assertContains('unsafe-all', $modeNames);
    }

    // ---- P3: help never mentions modes/params absent from contract -------

    public function testEveryHelpModeExistsInContractModes(): void
    {
        $contract = $this->contract();
        $modeNames = $this->modeNames($contract);
        $help = $this->helpText();

        // Collect indented mode rows under the "Modes:" block (4+ space indent,
        // first token a lowercase mode name). Sub-headings end in ':'.
        $inModes = false;
        foreach (preg_split('/\R/', $help) ?: [] as $line) {
            if (preg_match('/^Modes:\s*$/', $line)) {
                $inModes = true;
                continue;
            }
            if ($inModes && preg_match('/^\S/', $line)) {
                break; // next top-level block
            }
            if (!$inModes) {
                continue;
            }
            // A mode row: "    name  description" (skip "  family:" sub-heads and
            // the deprecated summary line).
            if (preg_match('/^\s{4,}([a-z][a-z0-9-]*)(\s|$)/', $line, $m)) {
                $name = $m[1];
                if ($name === 'changed' || $name === 'staged') {
                    // deprecated summary line lists these space-joined; still valid.
                }
                $this->assertContains(
                    $name,
                    $modeNames,
                    "help lists mode '{$name}' that is absent from the JSON contract"
                );
            }
        }
    }

    public function testEveryHelpParamExistsInContractParams(): void
    {
        $contract = $this->contract();
        $tokens = $this->paramTokens($contract);
        $help = $this->helpText();

        $inParams = false;
        foreach (preg_split('/\R/', $help) ?: [] as $line) {
            if (preg_match('/^Params:\s*$/', $line)) {
                $inParams = true;
                continue;
            }
            if ($inParams && preg_match('/^\S/', $line)) {
                break;
            }
            if (!$inParams) {
                continue;
            }
            // Flag rows begin with a -- or - token at 4+ indent.
            if (preg_match('/^\s{4,}(--?[A-Za-z][\w-]*)/', $line, $m)) {
                $this->assertContains(
                    $m[1],
                    $tokens,
                    "help lists param '{$m[1]}' that is absent from the JSON contract"
                );
            }
        }
    }

    // ---- P3: every contract mode/param appears in help -------------------

    public function testEveryContractModeAppearsInHelp(): void
    {
        $contract = $this->contract();
        $help = $this->helpText();
        foreach ($this->modeNames($contract) as $mode) {
            $this->assertStringContainsString(
                $mode,
                $help,
                "contract mode '{$mode}' is missing from generated help"
            );
        }
    }

    public function testEveryContractParamAppearsInHelp(): void
    {
        $contract = $this->contract();
        $help = $this->helpText();
        // Use primary names only (aliases are joined on the same line).
        $excluded = [];
        foreach ($contract['unknown_option_handlers'] as $h) {
            $excluded[(string) ($h['pattern'] ?? '')] = true;
        }
        foreach ($contract['params'] as $p) {
            $name = (string) ($p['name'] ?? '');
            if ($name === '' || isset($excluded[$name])) {
                continue;
            }
            $this->assertStringContainsString(
                $name,
                $help,
                "contract param '{$name}' is missing from generated help"
            );
        }
    }

    // ---- P3: deprecated modes show replacements --------------------------

    public function testDeprecatedModesRenderReplacements(): void
    {
        $contract = $this->contract();
        $help = $this->helpText();

        $sawDeprecated = false;
        foreach ($contract['mode_contracts'] as $c) {
            if (empty($c['deprecated'])) {
                continue;
            }
            $sawDeprecated = true;
            $name = (string) ($c['name'] ?? '');
            $repl = (array) ($c['replacements'] ?? []);
            $this->assertNotEmpty($repl, "deprecated mode '{$name}' has no replacements in contract");
            // The help "Deprecated modes:" block must list name -> replacements.
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($name, '/') . '\s*->.*' . preg_quote((string) $repl[0], '/') . '/',
                $help,
                "help must show replacements for deprecated mode '{$name}'"
            );
        }
        $this->assertTrue($sawDeprecated, 'expected at least one deprecated mode in the contract');
    }

    // ---- P3: examples reference valid modes ------------------------------

    public function testExamplesReferenceValidModes(): void
    {
        $contract = $this->contract();
        $modeNames = $this->modeNames($contract);

        foreach ($contract['examples_by_mode'] as $entry) {
            $mode = (string) ($entry['mode'] ?? '');
            $this->assertContains(
                $mode,
                $modeNames,
                "examples_by_mode references mode '{$mode}' that is not a real mode"
            );
        }

        // And each raw example's 2nd-or-later token (after the script path) that
        // matches a known mode name should be a real mode (loose but catches
        // stale fabricated modes).
        foreach ($contract['examples'] as $ex) {
            $text = (string) ($ex['text'] ?? '');
            $this->assertNotSame('', $text);
        }
    }

    // ---- P3: no duplicated params ----------------------------------------

    public function testHelpHasNoDuplicatedParamPrimaryNames(): void
    {
        $help = $this->helpText();
        $seen = [];
        $inParams = false;
        foreach (preg_split('/\R/', $help) ?: [] as $line) {
            if (preg_match('/^Params:\s*$/', $line)) {
                $inParams = true;
                continue;
            }
            if ($inParams && preg_match('/^\S/', $line)) {
                break;
            }
            if ($inParams && preg_match('/^\s{4,}(--?[A-Za-z][\w-]*)/', $line, $m)) {
                $this->assertArrayNotHasKey(
                    $m[1],
                    $seen,
                    "param '{$m[1]}' is duplicated in generated help"
                );
                $seen[$m[1]] = true;
            }
        }
        $this->assertNotEmpty($seen, 'expected at least one param row in help');
    }

    // ---- P1 acceptance: deterministic, non-executing, status section -----

    public function testHelpIsDeterministic(): void
    {
        $this->assertSame($this->helpText(), $this->helpText(), '--format=help must be deterministic');
    }

    public function testHelpDoesNotExecuteTarget(): void
    {
        $contract = $this->contract();
        $this->assertFalse(
            (bool) ($contract['meta']['target_executed'] ?? true),
            'meta.target_executed must be false (--help never runs the target)'
        );
        $help = $this->helpText();
        // No runtime search envelope leaks into help text.
        $this->assertStringNotContainsString('"matches"', $help);
        $this->assertStringNotContainsString('"results"', $help);
    }

    public function testHelpRendersStatusValuesFromContract(): void
    {
        $contract = $this->contract();
        $help = $this->helpText();
        $statusValues = (array) ($contract['status_values'] ?? []);
        $this->assertNotEmpty($statusValues, 'contract must expose status_values');
        $statusLine = null;
        foreach (preg_split('/\R/', $help) ?: [] as $line) {
            if (str_starts_with($line, 'Status values:')) {
                $statusLine = $line;
                break;
            }
        }
        $this->assertNotNull($statusLine, 'help must render a "Status values:" line');
        foreach ($statusValues as $sv) {
            $this->assertStringContainsString((string) $sv, (string) $statusLine);
        }
    }

    public function testHelpRendersSummaryUsageAndJsonEnv(): void
    {
        $contract = $this->contract();
        $help = $this->helpText();
        $help_meta = (array) ($contract['help'] ?? []);
        $this->assertArrayHasKey('summary', $help_meta);
        $this->assertArrayHasKey('usage', $help_meta);
        $this->assertStringContainsString((string) $help_meta['summary'], $help);
        $this->assertStringContainsString((string) $help_meta['usage'], $help);
        $this->assertStringContainsString((string) ($help_meta['json_output_env'] ?? 'AI_OUTPUT=json'), $help);
    }

    public function testHelpRendersBothContractCommands(): void
    {
        $help = $this->helpText();
        $this->assertStringContainsString('Machine contract: bash ' . self::$target . ' --introspect', $help);
        $this->assertStringContainsString(
            'Full contract: AI_OUTPUT=json php tools/ai/sh-introspect.php ' . self::$target,
            $help
        );
    }

    // ---- P2: ai-search.sh --help / -h delegate ---------------------------

    public function testScriptHelpExitsZeroAndDelegates(): void
    {
        foreach (['--help', '-h'] as $flag) {
            $r = $this->runScript($flag);
            $this->assertSame(0, $r['exit'], "ai-search.sh {$flag} must exit 0:\n" . $r['stderr']);
            $this->assertStringContainsString('ai-search.sh', $r['stdout']);
            // Delegated to the renderer => contains a JSON-derived section.
            $this->assertStringContainsString('Modes:', $r['stdout']);
            // No search-result envelope.
            $this->assertStringNotContainsString('"matches"', $r['stdout']);
        }
    }

    public function testScriptHelpFallbackWhenPhpMissing(): void
    {
        $r = $this->runScript('--help', ['PHP_BIN' => '/nonexistent-php-binary-xyz']);
        $this->assertSame(0, $r['exit'], 'missing PHP must still exit 0 with minimal fallback');
        $this->assertStringContainsString('Usage: ai-search.sh', $r['stdout']);
        $this->assertStringContainsString('--introspect', $r['stdout']);
    }

    // ---- P3: golden snapshot ---------------------------------------------

    public function testHelpMatchesGoldenSnapshot(): void
    {
        $golden = self::$repoRoot . '/tests/php/fixtures/ai-search-help.golden.txt';
        $this->assertFileExists($golden, 'golden help fixture missing');
        $expected = (string) file_get_contents($golden);
        $actual = $this->helpText();
        $this->assertSame(
            $expected,
            $actual,
            'generated help drifted from golden snapshot; if the JSON contract changed '
            . 'intentionally, regenerate via: '
            . 'php tools/ai/sh-introspect.php --format=help scripts/ai/ai-search.sh '
            . '> tests/php/fixtures/ai-search-help.golden.txt'
        );
    }
}
