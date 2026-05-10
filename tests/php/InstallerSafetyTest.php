<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class InstallerSafetyTest extends TestCase
{
    private static string $repoRoot;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
    }

    private function aiCommand(string $args): string
    {
        return escapeshellarg((string) PHP_BINARY) . ' tools/ai/ai.php ' . $args;
    }

    /** @param array<string,string> $envOverride @return array{stdout:string,stderr:string,exit:int} */
    private function runTool(string $command, array $envOverride = []): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $path = $this->buildTestPath();
        putenv('PATH=' . $path);
        $_ENV['PATH'] = $path;
        $_SERVER['PATH'] = $path;

        $env = null;
        if ($envOverride !== []) {
            $env = [
                'HOME' => sys_get_temp_dir(),
                'XDG_CONFIG_HOME' => sys_get_temp_dir(),
                'GIT_CONFIG_GLOBAL' => '/dev/null',
                'PATH' => $path,
            ];
            foreach ($envOverride as $k => $v) {
                $env[$k] = $v;
            }
        } else {
            $env = [
                'HOME' => sys_get_temp_dir(),
                'XDG_CONFIG_HOME' => sys_get_temp_dir(),
                'GIT_CONFIG_GLOBAL' => '/dev/null',
                'PATH' => $path,
            ];
        }

        $process = proc_open($command, $descriptors, $pipes, self::$repoRoot, $env);
        $this->assertIsResource($process, "proc_open failed for: $command");
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    /** @return array<string,mixed> */
    private function readGeneratedArtifact(string $name): array
    {
        $path = self::$repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . $name;
        $decoded = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($decoded, 'artifact should decode as array: ' . $name);
        return $decoded;
    }

    private function removeTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    private function buildTestPath(): string
    {
        $path = (string) getenv('PATH');

        $extras = [
            'C:\\Program Files\\Git\\cmd',
            'C:\\xampp\\php',
        ];

        $userProfile = (string) getenv('USERPROFILE');
        if ($userProfile !== '') {
            $extras[] = $userProfile . '\\AppData\\Local\\Microsoft\\WinGet\\Links';
            $extras[] = $userProfile . '\\AppData\\Roaming\\npm';
        }

        foreach ($extras as $extra) {
            if ($extra === '' || !is_dir($extra)) {
                continue;
            }
            if (stripos($path, $extra) === false) {
                $path .= ';' . $extra;
            }
        }

        return $path;
    }

    public function testRunScriptUnknownIdIsRejected(): void
    {
        $result = $this->runTool($this->aiCommand('run-script unknown-script --dry-run'));
        $this->assertSame(1, $result['exit']);
        $artifact = $this->readGeneratedArtifact('scripts.json');
        $this->assertSame('failed', $artifact['status'] ?? null);
        $this->assertStringContainsString('unknown script id', (string) ($artifact['data']['error'] ?? ''));
    }

    public function testToolchainInstallPlanDoesNotFail(): void
    {
        $result = $this->runTool($this->aiCommand('toolchain --with repomix,scc --install-plan'));
        $this->assertSame(0, $result['exit']);
        $artifact = $this->readGeneratedArtifact('toolchain.json');
        $this->assertTrue((bool) ($artifact['data']['install_plan_requested'] ?? false));
        $this->assertFalse((bool) ($artifact['data']['apply_requested'] ?? true));
    }

    public function testInstallDualDryRunIncludesScriptGovernancePacks(): void
    {
        $result = $this->runTool($this->aiCommand('install --profile dual --dry-run'));
        $this->assertSame(0, $result['exit']);
        $decoded = $this->readGeneratedArtifact('install.json');
        $packs = $decoded['data']['packs'] ?? [];
        $this->assertIsArray($packs);
        $this->assertContains('scripts-pack', $packs);
        $this->assertContains('policy-pack', $packs);
        $this->assertContains('hooks-pack', $packs);
    }

    public function testInstallDualWithScriptsPackDryRunIncludesScriptsPack(): void
    {
        $result = $this->runTool($this->aiCommand('install --profile dual --with scripts-pack --dry-run'));
        $this->assertSame(0, $result['exit']);
        $decoded = $this->readGeneratedArtifact('install.json');
        $packs = $decoded['data']['packs'] ?? [];
        $this->assertIsArray($packs);
        $this->assertContains('scripts-pack', $packs);
    }

    public function testInstallRunAfterInstallWorksWithDualDefaultScriptsPack(): void
    {
        $result = $this->runTool($this->aiCommand('install --profile dual --run-after-install=repomix-context --dry-run'));
        $this->assertSame(0, $result['exit']);
        $decoded = $this->readGeneratedArtifact('install.json');
        $this->assertSame('ok', $decoded['status'] ?? null);
    }

    public function testRunScriptApplyBlockedWhenRequiredToolsMissing(): void
    {
        $result = $this->runTool($this->aiCommand('run-script repomix-context --apply'), ['PATH' => '']);
        $this->assertSame(1, $result['exit']);
        $decoded = $this->readGeneratedArtifact('scripts.json');
        $this->assertSame('failed', $decoded['status'] ?? null);
        $this->assertStringContainsString('missing required tools', (string) ($decoded['data']['error'] ?? ''));
    }

    public function testToolchainApplyReportsUnsafeToolBlockedWhenMissing(): void
    {
        $result = $this->runTool($this->aiCommand('toolchain --with scc --toolchain-apply --yes'), ['PATH' => '']);
        $this->assertSame(0, $result['exit']);
        $decoded = $this->readGeneratedArtifact('toolchain.json');
        $rows = $decoded['data']['apply_results'] ?? [];
        $this->assertIsArray($rows);
        $blocked = false;
        foreach ($rows as $row) {
            if (($row['tool'] ?? '') === 'scc' && ($row['status'] ?? '') === 'blocked') {
                $blocked = true;
            }
        }
        $this->assertTrue($blocked, 'scc should be explicitly blocked for auto-install');
    }

    public function testOpencodeAgentsAreVisibleByDefault(): void
    {
        $agentDir = self::$repoRoot . DIRECTORY_SEPARATOR . '.opencode' . DIRECTORY_SEPARATOR . 'agents';
        if (!is_dir($agentDir)) {
            $this->markTestSkipped('.opencode/agents/ does not exist; run adapter-opencode pack first');
        }
        $files = glob($agentDir . DIRECTORY_SEPARATOR . '*.md') ?: [];
        $this->assertNotEmpty($files, 'expected at least one OpenCode agent file');

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $this->assertMatchesRegularExpression('/\bmode:\s*(subagent|all)\b/', $content, 'agent must declare compatible mode: ' . basename($file));
            $this->assertStringContainsString('hidden: false', $content, 'agent should be visible in listings: ' . basename($file));
        }
    }

    public function testCopilotAgentsHaveAgentExtension(): void
    {
        $agentDir = self::$repoRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'agents';
        if (!is_dir($agentDir)) {
            $this->markTestSkipped('.github/agents/ does not exist; run adapter-copilot pack first');
        }
        $files = glob($agentDir . DIRECTORY_SEPARATOR . '*.agent.md') ?: [];
        $this->assertNotEmpty($files, 'expected at least one Copilot agent file with .agent.md extension');

        $plainMdFiles = glob($agentDir . DIRECTORY_SEPARATOR . '*.md') ?: [];
        $plainMdFiles = array_filter($plainMdFiles, static fn(string $f): bool => !str_ends_with($f, '.agent.md'));
        $this->assertEmpty($plainMdFiles, 'Copilot agents must use .agent.md extension, found plain .md files: ' . implode(', ', array_map('basename', $plainMdFiles)));

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringContainsString('name:', $content, 'agent must declare Copilot name frontmatter: ' . basename($file));
            $this->assertStringContainsString('tools:', $content, 'agent must declare Copilot tools frontmatter: ' . basename($file));
        }
    }

    public function testCoreAgentSourcesAreCanonical(): void
    {
        $srcDir = self::$repoRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'agents';
        $this->assertDirectoryExists($srcDir, 'templates/core/agents/ must exist as the canonical agent source');

        $files = glob($srcDir . DIRECTORY_SEPARATOR . '*.md') ?: [];
        $this->assertNotEmpty($files, 'expected at least one canonical agent source file in templates/core/agents/');

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringContainsString('mode:', $content, 'canonical agent source must declare mode: ' . basename($file));
            $this->assertStringContainsString('id:', $content, 'canonical agent source must declare id: ' . basename($file));
        }
    }

    public function testDirectInstallerBackupArchivesExistingManagedFiles(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_backup_' . uniqid('', true);
        $promptDir = $target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'prompts';
        $outputJson = $target . DIRECTORY_SEPARATOR . 'install-output.json';

        mkdir($promptDir, 0700, true);
        file_put_contents($promptDir . DIRECTORY_SEPARATOR . 'legacy.prompt.md', "# legacy\n");
        file_put_contents($target . DIRECTORY_SEPARATOR . '.ai-install-manifest.json', json_encode(['legacy' => true]));

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--runtime',
                'github-copilot',
                '--profile',
                'copilot',
                '--force',
                '--backup',
                '--output-json',
                escapeshellarg($outputJson),
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);

            $decoded = json_decode((string) file_get_contents($outputJson), true);
            $this->assertIsArray($decoded);
            $backup = $decoded['backup'] ?? null;
            $this->assertIsArray($backup, 'backup metadata should be written to output json');

            $backupDir = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($backup['backup_dir'] ?? ''));
            $this->assertDirectoryExists($backupDir);
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'manifest.json');
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . 'legacy.prompt.md');
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . '.ai-install-manifest.json');

            $installedAgents = glob($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . '*.agent.md') ?: [];
            $installedPrompts = glob($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . '*.prompt.md') ?: [];
            $this->assertNotEmpty($installedAgents, 'copilot install should rename agent files to .agent.md');
            $this->assertNotEmpty($installedPrompts, 'copilot install should rename workflow files to .prompt.md');

            $manifest = json_decode((string) file_get_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json'), true);
            $this->assertIsArray($manifest);
            $paths = array_map(static fn(array $entry): string => (string) ($entry['path'] ?? ''), $manifest['entries'] ?? []);
            $this->assertContains('.github/prompts', $paths);
            $this->assertContains('.ai-install-manifest.json', $paths);
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerCanWriteUpgradeCopiesForExistingTargets(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_upgrade_' . uniqid('', true);
        $docsDir = $target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';

        mkdir($docsDir, 0700, true);
        file_put_contents($docsDir . DIRECTORY_SEPARATOR . 'failure-handling.md', "# local copy\n");

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--runtime',
                'github-copilot',
                '--profile',
                'custom',
                '--with',
                'docs-reference-pack',
                '--upgrade-suffix',
                '-upgrade',
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);
            $this->assertFileExists($docsDir . DIRECTORY_SEPARATOR . 'failure-handling.md');
            $this->assertSame("# local copy\n", (string) file_get_contents($docsDir . DIRECTORY_SEPARATOR . 'failure-handling.md'));
            $this->assertFileExists($docsDir . DIRECTORY_SEPARATOR . 'failure-handling-upgrade.md');
            $this->assertFileExists($docsDir . DIRECTORY_SEPARATOR . 'scripts-reference.md');
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerSkipsUpgradeCopyWhenTargetIsIdentical(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_upgrade_identical_' . uniqid('', true);
        $docsDir = $target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';
        $sourceFile = self::$repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'failure-handling.md';

        mkdir($docsDir, 0700, true);
        copy($sourceFile, $docsDir . DIRECTORY_SEPARATOR . 'failure-handling.md');

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--runtime',
                'github-copilot',
                '--profile',
                'custom',
                '--with',
                'docs-reference-pack',
                '--upgrade-suffix',
                '-upgrade',
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);
            $this->assertFileDoesNotExist($docsDir . DIRECTORY_SEPARATOR . 'failure-handling-upgrade.md');
            $this->assertStringContainsString('skip_identical_existing', $result['stdout']);
        } finally {
            $this->removeTree($target);
        }
    }
}
