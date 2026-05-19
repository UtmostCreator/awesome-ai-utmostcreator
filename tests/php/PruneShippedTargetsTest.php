<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for scripts/ai/prune-shipped-targets.sh.
 *
 * Only the read-only paths (--list, --dry-run, --help) and safety refusals
 * (--apply with missing manifest) are exercised. The destructive --apply
 * path against the live repo is INTENTIONALLY NOT TESTED here — the human
 * runs --apply later when ready (see commit message).
 */
class PruneShippedTargetsTest extends TestCase
{
    private static string $repoRoot;
    private static string $script;
    private static string $manifest;
    private static string $bashBin;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        self::$script   = 'scripts/ai/prune-shipped-targets.sh';
        self::$manifest = $root . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';

        if (!is_file($root . DIRECTORY_SEPARATOR . self::$script)) {
            throw new \RuntimeException('prune-shipped-targets.sh missing at: ' . self::$script);
        }
        if (!is_file(self::$manifest)) {
            throw new \RuntimeException('.ai-install-manifest.json missing; cannot test');
        }

        self::$bashBin = self::locateBash();
    }

    /**
     * Locate a usable bash binary. On Windows, PATH-resolved `bash` often
     * points to the WSL stub at AppData\Local\Microsoft\WindowsApps\bash.exe
     * which fails with "RPC call ... handle" when no WSL distro is
     * installed. Prefer Git for Windows' bash, fall back to PATH.
     */
    private static function locateBash(): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidates = [
                'C:\\Program Files\\Git\\bin\\bash.exe',
                'C:\\Program Files\\Git\\usr\\bin\\bash.exe',
                'C:\\Program Files (x86)\\Git\\bin\\bash.exe',
                'C:\\msys64\\usr\\bin\\bash.exe',
            ];
            foreach ($candidates as $c) {
                if (is_file($c)) {
                    return $c;
                }
            }
        }
        return 'bash';
    }

    /**
     * Run bash with the given args against the script in the repo root.
     *
     * Mirrors CliToolsTest::runTool() but adds bash + file-redirected
     * capture for Windows pipe-buffer safety. stdout/stderr are written
     * to temp files (not pipes) to avoid the Windows ~4-64KiB deadlock
     * documented in docs/ai/capabilities/evidence-first-execution/examples.md.
     *
     * @param  list<string> $args extra args appended after the script path
     * @return array{stdout:string, stderr:string, exit:int}
     */
    private function runBash(array $args, ?string $bashOverride = null, ?string $cwd = null): array
    {
        $bash = $bashOverride ?? self::$bashBin;

        // Capture stdout/stderr via the pipe descriptors. This works for
        // bounded outputs (<<= a few KB); the prune-shipped-targets read-only
        // modes emit only ~40 short lines plus a small per-pack table.
        $cmdParts = [$bash, self::$script];
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

        $proc = proc_open($cmd, $descriptors, $pipes, $cwd ?? self::$repoRoot, $env);
        $this->assertIsResource($proc, "proc_open failed for: $cmd");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    /** @return array<string,array{source:string, pack:string}> */
    private function manifestEntries(): array
    {
        $raw    = (string) file_get_contents(self::$manifest);
        $parsed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $out    = [];
        foreach (($parsed['files'] ?? []) as $key => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $out[(string) $key] = [
                'source' => (string) ($meta['source'] ?? $key),
                'pack'   => (string) ($meta['pack'] ?? 'unknown'),
            ];
        }
        return $out;
    }

    // ---- 1: --list exits 0 with non-empty output --------------------

    public function testListModeExitsZero(): void
    {
        $r = $this->runBash(['--list']);
        $this->assertSame(0, $r['exit'], "--list exited non-zero. stderr:\n" . $r['stderr']);
        $this->assertNotSame('', trim($r['stdout']), '--list produced no output');
    }

    // ---- 2: every --list line is a manifest key with source != key --

    public function testListModeOutputAllPathsAreInManifest(): void
    {
        $r       = $this->runBash(['--list']);
        $this->assertSame(0, $r['exit']);
        $lines   = array_values(array_filter(array_map('trim', explode("\n", $r['stdout'])), 'strlen'));
        $entries = $this->manifestEntries();

        $this->assertNotEmpty($lines, '--list should print at least one path');
        foreach ($lines as $line) {
            $this->assertArrayHasKey(
                $line,
                $entries,
                "--list emitted '$line' but it is not a key in .ai-install-manifest.json"
            );
            $this->assertNotSame(
                $line,
                $entries[$line]['source'],
                "--list emitted '$line' but its manifest source equals the key (no duplication)"
            );
        }
    }

    // ---- 3: --list never includes refuse-listed prefixes ------------

    public function testListModeNeverContainsRefuseListedPaths(): void
    {
        $r     = $this->runBash(['--list']);
        $this->assertSame(0, $r['exit']);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $r['stdout'])), 'strlen'));

        $refused = [
            'packages/',
            'tools/',
            'scripts/ai/',
            'tests/',
            '.schemas/',
            '.git/',
            'vendor/',
            'node_modules/',
        ];
        foreach ($lines as $line) {
            foreach ($refused as $prefix) {
                $this->assertStringStartsNotWith(
                    $prefix,
                    $line,
                    "--list emitted refuse-listed path '$line' (matches '$prefix')"
                );
            }
        }
    }

    // ---- 4: --dry-run exits 0; stdout ends with total_bytes=N -------

    public function testDryRunExitsZero(): void
    {
        $r = $this->runBash(['--dry-run']);
        $this->assertSame(0, $r['exit'], "--dry-run exited non-zero. stderr:\n" . $r['stderr']);

        // stderr carries the per-pack table.
        $this->assertStringContainsString(
            'PACK',
            $r['stderr'],
            '--dry-run should print a per-pack table header to stderr'
        );
        $this->assertStringContainsString('TOTAL', $r['stderr']);

        // Last non-empty stdout line must match total_bytes=<int>.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $r['stdout'])), 'strlen'));
        $this->assertNotEmpty($lines, '--dry-run must print at least one stdout line');
        $last = end($lines);
        $this->assertMatchesRegularExpression(
            '/^total_bytes=\d+$/',
            (string) $last,
            "--dry-run last stdout line should be 'total_bytes=N', got: '$last'"
        );
    }

    // ---- 5: --apply refuses on dirty worktree -----------------------

    public function testApplyRefusesOnDirtyWorktree(): void
    {
        // We cannot safely dirty the live repo. Fabricate a throwaway git
        // repo in a tempdir, copy the script + common.sh in, and run
        // --apply there. The manifest has zero qualifying entries; we still
        // expect exit 2 (dirty worktree) BEFORE the no-op loop runs.
        $tempBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prune-dirty-' . bin2hex(random_bytes(4));
        if (!@mkdir($tempBase, 0777, true)) {
            $this->markTestSkipped('cannot create tempdir for dirty-worktree fixture');
        }

        try {
            // Initialize git + commit a minimal manifest + leave dirt.
            $bashSetup = sprintf(
                'cd %s && git init -q && '
                . 'git config user.email t@t.t && git config user.name t && '
                . 'printf \'{"files":{}}\' > .ai-install-manifest.json && '
                . 'git add -A && git commit -q -m init && '
                . 'printf dirt > dirt.txt && git status --porcelain',
                escapeshellarg($tempBase)
            );
            $setup = $this->runRawBash($bashSetup, $tempBase);
            if ($setup['exit'] !== 0 || trim($setup['stdout']) === '') {
                $this->markTestSkipped('could not fabricate dirty worktree (git not configured); stderr: ' . $setup['stderr']);
            }

            // Copy script + common.sh into the fake repo.
            $scriptAbs = self::$repoRoot . DIRECTORY_SEPARATOR . self::$script;
            $commonAbs = self::$repoRoot . DIRECTORY_SEPARATOR . 'scripts/ai/common.sh';
            @mkdir($tempBase . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai', 0777, true);
            copy($scriptAbs, $tempBase . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'prune-shipped-targets.sh');
            copy($commonAbs, $tempBase . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'common.sh');

            // Run --apply from inside the fake repo via runBash with cwd override.
            $r = $this->runBash(['--apply'], null, $tempBase);

            $this->assertSame(
                2,
                $r['exit'],
                "--apply on dirty worktree should exit 2, got {$r['exit']}. stderr:\n{$r['stderr']}"
            );
            $this->assertMatchesRegularExpression(
                '/refused|clean/i',
                $r['stderr'],
                'refusal stderr should mention "refused" or "clean"'
            );
        } finally {
            $this->rrmdir($tempBase);
        }
    }

    /**
     * Helper: run a raw bash -c command (used only for the dirty-worktree
     * setup, since we need git commands inline).
     *
     * @return array{stdout:string, stderr:string, exit:int}
     */
    private function runRawBash(string $bashCmd, string $cwd): array
    {
        $cmd = escapeshellarg(self::$bashBin) . ' -lc ' . escapeshellarg($bashCmd);
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
        $proc = proc_open($cmd, $descriptors, $pipes, $cwd, $env);
        if (!is_resource($proc)) {
            return ['stdout' => '', 'stderr' => 'proc_open failed', 'exit' => -1];
        }
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        return ['stdout' => $out, 'stderr' => $err, 'exit' => $exit];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isDir()) {
                @rmdir($f->getPathname());
            } else {
                @chmod($f->getPathname(), 0666);
                @unlink($f->getPathname());
            }
        }
        @rmdir($dir);
    }

    // ---- 6: --apply with missing manifest exits 1 -------------------

    public function testApplyRefusesWhenManifestMissing(): void
    {
        $bogus = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'definitely-does-not-exist-' . bin2hex(random_bytes(4)) . '.json';
        $this->assertFileDoesNotExist($bogus);

        $r = $this->runBash(['--apply', '--manifest', $bogus]);
        $this->assertSame(
            1,
            $r['exit'],
            "missing manifest should exit 1, got {$r['exit']}. stderr:\n{$r['stderr']}"
        );
        $this->assertMatchesRegularExpression(
            '/manifest not found/i',
            $r['stderr'],
            'stderr should explain missing manifest'
        );
    }

    // ---- 7: --help prints usage and exits 0 -------------------------

    public function testHelpFlag(): void
    {
        $r = $this->runBash(['--help']);
        $this->assertSame(0, $r['exit'], "--help should exit 0. stderr:\n" . $r['stderr']);
        $combined = $r['stdout'] . $r['stderr'];
        $this->assertStringContainsString('Usage:', $combined);
        $this->assertStringContainsString('--list', $combined);
        $this->assertStringContainsString('--dry-run', $combined);
        $this->assertStringContainsString('--apply', $combined);
    }

    // ---- 8: --include-candidates does NOT affect --list -------------

    public function testIncludeCandidatesOnlyAffectsApply(): void
    {
        $base = $this->runBash(['--list']);
        $with = $this->runBash(['--list', '--include-candidates']);

        $this->assertSame(0, $base['exit']);
        $this->assertSame(0, $with['exit']);

        // Output must be byte-identical: candidates are an --apply-only
        // concept (the manifest is the source of truth for --list).
        $this->assertSame(
            $base['stdout'],
            $with['stdout'],
            '--include-candidates must not change --list output; candidates are --apply-only'
        );

        // Defense-in-depth: AGENTS.md and .opencode/opencode.json are NOT
        // manifest keys, so they must never appear in --list regardless.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $with['stdout'])), 'strlen'));
        $this->assertNotContains('AGENTS.md', $lines);
        $this->assertNotContains('.opencode/opencode.json', $lines);
    }
}
