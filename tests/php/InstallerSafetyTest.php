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

    /**
     * Skip a test when external tools the installer hard-requires are absent.
     * Uses the installer's own detector so the check matches what the install
     * subprocess will see. Tests still run in CI where the toolchain is present.
     */
    private function skipIfToolchainMissing(array $tools): void
    {
        require_once self::$repoRoot . '/tools/ai/install/toolchain.php';
        $missing = [];
        foreach ($tools as $tool) {
            if (!aiInstallerCommandExists($tool)) {
                $missing[] = $tool;
            }
        }
        if ($missing !== []) {
            $this->markTestSkipped('required toolchain not installed: ' . implode(', ', $missing));
        }
    }

    /** @return list<string> */
    private function relativeGlob(string $pattern): array
    {
        $files = glob(self::$repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pattern)) ?: [];
        sort($files);

        return array_map(
            static fn(string $path): string => str_replace(self::$repoRoot . DIRECTORY_SEPARATOR, '', $path),
            $files
        );
    }

    /** @return list<string> */
    private function targetGlob(string $target, string $pattern): array
    {
        $files = glob($target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pattern)) ?: [];
        sort($files);

        return array_map(
            static fn(string $path): string => str_replace($target . DIRECTORY_SEPARATOR, '', $path),
            $files
        );
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
            // Hidden agents are internal-only (e.g. bootstrapper); allow hidden: true for those.
            if (!preg_match('/^---\R(.*?)\R---/s', $content, $fm) || !preg_match('/^hidden:\s*true\s*$/m', $fm[1])) {
                $this->assertStringContainsString('hidden: false', $content, 'agent should be visible in listings: ' . basename($file));
            }
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
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

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
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'before' . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . 'legacy.prompt.md');
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'before' . DIRECTORY_SEPARATOR . '.ai-install-manifest.json');

            $installedAgents = glob($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . '*.agent.md') ?: [];
            $installedPrompts = glob($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'prompts' . DIRECTORY_SEPARATOR . '*.prompt.md') ?: [];
            $this->assertNotEmpty($installedAgents, 'copilot install should rename agent files to .agent.md');
            $this->assertNotEmpty($installedPrompts, 'copilot install should rename workflow files to .prompt.md');

            $manifest = json_decode((string) file_get_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json'), true);
            $this->assertIsArray($manifest);
            $paths = array_map(static fn(array $entry): string => (string) ($entry['path'] ?? ''), $manifest['entries'] ?? []);
            $this->assertContains('.github/prompts/legacy.prompt.md', $paths);
            $this->assertContains('.ai-install-manifest.json', $paths);
        } finally {
            $this->removeTree($target);
        }
    }

    public function testCanonicalBackupRollbackSupportsSpecificCreatedFile(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/backup.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_specific_rollback_' . uniqid('', true);
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_specific_source_' . uniqid('', true);

        try {
            mkdir($target . DIRECTORY_SEPARATOR . 'docs', 0700, true);
            mkdir($source . DIRECTORY_SEPARATOR . 'templates', 0700, true);
            file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'existing.md', "before\n");
            file_put_contents($source . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'existing.md', "after\n");
            file_put_contents($source . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'created.md', "created\n");

            $plan = [[
                'type' => 'dir',
                'source' => 'templates',
                'target' => 'docs',
                'action' => 'OVERWRITE_MANAGED',
            ]];

            $backup = aiInstallBackupCreate($target, $plan, $source, 'test');
            $backupId = (string) $backup['backup_id'];

            file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'existing.md', "after\n");
            file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'created.md', "created\n");
            aiInstallBackupRecordAfter($target, $backupId, $plan, $source, 'applied');

            $dryRun = aiInstallBackupRollback($target, $backupId, false, false, ['docs/created.md']);
            $this->assertSame('ok', $dryRun['status']);
            $this->assertSame(['docs/created.md'], $dryRun['delete']);
            $this->assertSame([], $dryRun['restore']);

            $applied = aiInstallBackupRollback($target, $backupId, true, false, ['docs/created.md']);
            $this->assertSame('ok', $applied['status']);
            $this->assertFileDoesNotExist($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'created.md');
            $this->assertSame("after\n", file_get_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'existing.md'));
        } finally {
            $this->removeTree($target);
            $this->removeTree($source);
        }
    }

    public function testCanonicalBackupRollbackBlocksUserEditedCreatedFile(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/backup.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_conflict_rollback_' . uniqid('', true);
        $source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_conflict_source_' . uniqid('', true);

        try {
            mkdir($target . DIRECTORY_SEPARATOR . 'docs', 0700, true);
            mkdir($source . DIRECTORY_SEPARATOR . 'templates', 0700, true);
            file_put_contents($source . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'created.md', "created\n");

            $plan = [[
                'type' => 'dir',
                'source' => 'templates',
                'target' => 'docs',
                'action' => 'CREATE',
            ]];

            $backup = aiInstallBackupCreate($target, $plan, $source, 'test');
            $backupId = (string) $backup['backup_id'];
            file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'created.md', "created\n");
            aiInstallBackupRecordAfter($target, $backupId, $plan, $source, 'applied');

            file_put_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'created.md', "user edit\n");

            $blocked = aiInstallBackupRollback($target, $backupId, true, false, []);
            $this->assertSame('blocked', $blocked['status']);
            $this->assertNotEmpty($blocked['conflicts']);
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'created.md');

            $forced = aiInstallBackupRollback($target, $backupId, true, true, []);
            $this->assertSame('ok', $forced['status']);
            $this->assertFileDoesNotExist($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'created.md');
        } finally {
            $this->removeTree($target);
            $this->removeTree($source);
        }
    }

    public function testDirectInstallerCopilotInstallMakesInstalledSurfaceVisible(): void
    {
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_copilot_visible_' . uniqid('', true);
        mkdir($target, 0700, true);

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
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);

            $sourceAgents = array_values(array_filter(
                $this->relativeGlob('packages/ai-universal-rules/templates/core/agents/*.md'),
                function (string $relPath): bool {
                    $content = (string) file_get_contents(self::$repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
                    return !preg_match('/^---\R(.*?)\R---/s', $content, $fm) || !preg_match('/^hidden:\s*true\s*$/m', $fm[1]);
                }
            ));
            $sourceWorkflows = $this->relativeGlob('packages/ai-universal-rules/templates/workflows/*.md');
            $installedAgents = $this->targetGlob($target, '.github/agents/*.agent.md');
            $installedPrompts = $this->targetGlob($target, '.github/prompts/*.prompt.md');
            $installedSkills = $this->targetGlob($target, '.github/skills/*/SKILL.md');

            $this->assertCount(count($sourceAgents), $installedAgents, 'Copilot install should expose every non-hidden canonical agent as a visible .agent.md file');
            $this->assertCount(count($sourceWorkflows), $installedPrompts, 'Copilot install should expose every workflow as a visible prompt');
            $this->assertCount(count($sourceWorkflows), $installedSkills, 'Copilot install should expose every workflow as a visible skill');

            $this->assertFileExists($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'copilot-instructions.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated-artifacts.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'instructions' . DIRECTORY_SEPARATOR . 'generated-artifacts.instructions.md');

            foreach (glob($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . '*.agent.md') ?: [] as $file) {
                $content = (string) file_get_contents($file);
                $this->assertStringContainsString('name:', $content, 'Copilot agent must declare name frontmatter: ' . basename($file));
                $this->assertStringContainsString('tools:', $content, 'Copilot agent must declare tools frontmatter: ' . basename($file));
            }
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerOpenCodeInstallMakesInstalledSurfaceVisible(): void
    {
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_opencode_visible_' . uniqid('', true);
        mkdir($target, 0700, true);

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--runtime',
                'opencode',
                '--profile',
                'opencode',
                '--force',
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);

            $sourceAgents = array_values(array_filter(
                $this->relativeGlob('packages/ai-universal-rules/templates/core/agents/*.md'),
                function (string $relPath): bool {
                    $content = (string) file_get_contents(self::$repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath));
                    return !preg_match('/^---\R(.*?)\R---/s', $content, $fm) || !preg_match('/^hidden:\s*true\s*$/m', $fm[1]);
                }
            ));
            $sourceWorkflows = $this->relativeGlob('packages/ai-universal-rules/templates/workflows/*.md');
            $sourceCommands = $this->relativeGlob('packages/ai-universal-rules/templates/commands/*.md');
            $expectedCommandNames = array_values(array_unique(array_map('basename', array_merge($sourceWorkflows, $sourceCommands))));
            sort($expectedCommandNames);
            $installedAgents = $this->targetGlob($target, '.opencode/agents/*.md');
            $installedCommands = $this->targetGlob($target, '.opencode/commands/*.md');
            $installedSkills = $this->targetGlob($target, '.opencode/skills/*/SKILL.md');
            $installedCommandNames = array_values(array_map('basename', $installedCommands));

            $this->assertCount(count($sourceAgents), $installedAgents, 'OpenCode install should expose every non-hidden canonical agent as a visible .md file');
            $this->assertSame($expectedCommandNames, $installedCommandNames, 'OpenCode install should expose every workflow and command under .opencode/commands/');
            $this->assertGreaterThanOrEqual(count($sourceWorkflows), count($installedSkills), 'OpenCode install should expose workflow skills');

            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'opencode.jsonc');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'AGENTS.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated-artifacts.md');

            foreach (glob($target . DIRECTORY_SEPARATOR . '.opencode' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR . '*.md') ?: [] as $file) {
                $content = (string) file_get_contents($file);
                $this->assertMatchesRegularExpression('/\bmode:\s*(subagent|all)\b/', $content, 'OpenCode agent must declare compatible mode: ' . basename($file));
                // Hidden agents are internal-only; they must not appear in shipped installs.
                $this->assertStringNotContainsString('hidden: true', $content, 'Internal-only agents must not be shipped to installed projects: ' . basename($file));
                $this->assertStringContainsString('hidden: false', $content, 'OpenCode agent should be visible in listings: ' . basename($file));
            }
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerFullGovernanceBackupInstallShipsAllCoreSurfaces(): void
    {
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_full_governance_' . uniqid('', true);
        $outputJson = $target . DIRECTORY_SEPARATOR . 'install-output.json';
        $docsDir = $target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';

        mkdir($docsDir, 0700, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'README.md', "# existing\n");
        file_put_contents($docsDir . DIRECTORY_SEPARATOR . 'failure-handling.md', "# existing local copy\n");

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--profile',
                'full-governance',
                '--runtime',
                'both',
                '--force',
                '--backup',
                '--output-json',
                escapeshellarg($outputJson),
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);

            $decoded = json_decode((string) file_get_contents($outputJson), true);
            $this->assertIsArray($decoded);
            $this->assertSame('ok', $decoded['status'] ?? null);
            $this->assertSame('full-governance', $decoded['profile'] ?? null);
            $this->assertSame('both', $decoded['runtime'] ?? null);

            $packs = $decoded['selected_packs'] ?? [];
            $this->assertIsArray($packs);
            foreach (['base', 'adapter-copilot', 'adapter-opencode', 'scripts-pack', 'policy-pack', 'hooks-pack', 'ci-pack', 'capabilities-governance'] as $pack) {
                $this->assertContains($pack, $packs, 'full-governance install should include pack ' . $pack);
            }

            $backup = $decoded['backup'] ?? null;
            $this->assertIsArray($backup, 'backup metadata should be present for full-governance install');
            $backupDir = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($backup['backup_dir'] ?? ''));
            $this->assertDirectoryExists($backupDir);
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'manifest.json');
            $this->assertFileExists($backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'before' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'failure-handling.md');
            $manifest = json_decode((string) file_get_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json'), true);
            $this->assertIsArray($manifest);
            $paths = array_map(static fn(array $entry): string => (string) ($entry['path'] ?? ''), $manifest['entries'] ?? []);
            $this->assertContains('docs/ai/failure-handling.md', $paths);

            $placeholders = $decoded['placeholders'] ?? [];
            $this->assertIsArray($placeholders);
            $this->assertSame([], $placeholders['unresolved_required'] ?? null, 'shipping install should not leave required placeholders unresolved');

            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'AGENTS.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'generated-artifacts.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'copilot-instructions.md');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'tool-policy.json');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'validate-ai-surface.yml');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'opencode.jsonc');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'pre-commit.sh');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'commit-msg.sh');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'ai-file-freshness.sh');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'ai-install-coverage.sh');

            $copilotAgents = $this->targetGlob($target, '.github/agents/*.agent.md');
            $copilotPrompts = $this->targetGlob($target, '.github/prompts/*.prompt.md');
            $copilotSkills = $this->targetGlob($target, '.github/skills/*/SKILL.md');
            $opencodeAgents = $this->targetGlob($target, '.opencode/agents/*.md');
            $opencodeCommands = $this->targetGlob($target, '.opencode/commands/*.md');
            $opencodeSkills = $this->targetGlob($target, '.opencode/skills/*/SKILL.md');

            $this->assertNotEmpty($copilotAgents);
            $this->assertNotEmpty($copilotPrompts);
            $this->assertNotEmpty($copilotSkills);
            $this->assertNotEmpty($opencodeAgents);
            $this->assertNotEmpty($opencodeCommands);
            $this->assertNotEmpty($opencodeSkills);

            $postInstall = (string) file_get_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'POST-INSTALL.md');
            $this->assertStringContainsString('post-install-setup', $postInstall, 'generated post-install checklist should advertise the shipped setup helper');
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerReinstallBackupCapturesAllPreviouslyManagedFiles(): void
    {
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_reinstall_backup_' . uniqid('', true);
        $firstOutputJson = $target . DIRECTORY_SEPARATOR . 'install-first.json';
        $secondOutputJson = $target . DIRECTORY_SEPARATOR . 'install-second.json';

        mkdir($target, 0700, true);

        try {
            $firstCommand = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--profile',
                'full-governance',
                '--runtime',
                'both',
                '--force',
                '--backup',
                '--output-json',
                escapeshellarg($firstOutputJson),
            ]);

            $firstResult = $this->runTool($firstCommand);
            $this->assertSame(0, $firstResult['exit'], $firstResult['stderr']);

            $firstManifestPath = $target . DIRECTORY_SEPARATOR . '.ai-install-manifest.json';
            $firstManifest = json_decode((string) file_get_contents($firstManifestPath), true);
            $this->assertIsArray($firstManifest);

            $managedPaths = array_keys((array) ($firstManifest['files'] ?? []));
            sort($managedPaths);
            $this->assertNotEmpty($managedPaths, 'first install should record managed files');

            $secondCommand = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--profile',
                'full-governance',
                '--runtime',
                'both',
                '--force',
                '--backup',
                '--output-json',
                escapeshellarg($secondOutputJson),
            ]);

            $secondResult = $this->runTool($secondCommand);
            $this->assertSame(0, $secondResult['exit'], $secondResult['stderr']);

            $secondDecoded = json_decode((string) file_get_contents($secondOutputJson), true);
            $this->assertIsArray($secondDecoded);
            $backup = $secondDecoded['backup'] ?? null;
            $this->assertIsArray($backup, 'reinstall should emit backup metadata');

            $backupDir = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($backup['backup_dir'] ?? ''));
            $this->assertDirectoryExists($backupDir);

            $backupManifest = json_decode((string) file_get_contents($backupDir . DIRECTORY_SEPARATOR . 'manifest.json'), true);
            $this->assertIsArray($backupManifest);

            $backupPaths = array_map(static fn(array $entry): string => (string) ($entry['path'] ?? ''), $backupManifest['entries'] ?? []);
            sort($backupPaths);

            $missingPaths = array_values(array_diff($managedPaths, $backupPaths));
            $this->assertSame([], $missingPaths, 'reinstall backup must capture every previously managed installed path');

            foreach ($managedPaths as $path) {
                $snapshotPath = $backupDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'before' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
                $this->assertFileExists($snapshotPath, 'backup snapshot missing managed path: ' . $path);
            }
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerFullGovernanceOpencodeOnlyValidatesAsInstalledTarget(): void
    {
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_full_governance_opencode_' . uniqid('', true);

        mkdir($target, 0700, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'README.md', "# existing\n");

        $git = $this->runTool('git init ' . escapeshellarg($target));
        $this->assertSame(0, $git['exit'], $git['stderr']);
        $gitAdd = $this->runTool('git -C ' . escapeshellarg($target) . ' add README.md');
        $this->assertSame(0, $gitAdd['exit'], $gitAdd['stderr']);

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--profile',
                'full-governance',
                '--runtime',
                'opencode',
                '--without',
                'optional-agents-copilot-pack',
                '--project-name',
                'app-configs',
                '--backup',
                '--verify-after',
                '--non-interactive',
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stderr']);

            $manifest = json_decode((string) file_get_contents($target . DIRECTORY_SEPARATOR . '.ai-install-manifest.json'), true);
            $this->assertIsArray($manifest);
            $this->assertContains('adapter-opencode', $manifest['packs'] ?? []);
            $this->assertNotContains('adapter-copilot', $manifest['packs'] ?? []);
            $this->assertNotContains('optional-agents-copilot-pack', $manifest['packs'] ?? []);

            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'opencode.jsonc');
            $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'verify-install-placeholders.php');
            $this->assertFileDoesNotExist($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'copilot-instructions.md');
            $this->assertDirectoryDoesNotExist($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'instructions');
            $this->assertDirectoryDoesNotExist($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'prompts');
            $this->assertDirectoryDoesNotExist($target . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'agents');

            foreach ([
                'php tools/ai/validate-ai-config.php',
                'php tools/ai/validate-install-surface.php --strict',
                'php tools/ai/validate-ai-catalog.php',
            ] as $targetCommand) {
                $validate = $this->runTool('cd ' . escapeshellarg($target) . ' && ' . $targetCommand);
                $this->assertSame(0, $validate['exit'], $targetCommand . "\n" . $validate['stdout'] . $validate['stderr']);
            }
        } finally {
            $this->removeTree($target);
        }
    }

    public function testDirectInstallerResolvesRequiredPlaceholdersOnSkipIdenticalExisting(): void
    {
        $this->skipIfToolchainMissing(['fd', 'ast-grep', 'scc']);

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_skip_identical_placeholders_' . uniqid('', true);
        $docsDir = $target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';

        mkdir($docsDir, 0700, true);
        copy(
            self::$repoRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . 'ai-universal-rules' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'project-context.template.md',
            $docsDir . DIRECTORY_SEPARATOR . 'project-context.md'
        );

        try {
            $command = implode(' ', [
                escapeshellarg((string) PHP_BINARY),
                'tools/ai/install-ai-kit.php',
                '--target',
                escapeshellarg($target),
                '--profile',
                'full-governance',
                '--runtime',
                'both',
                '--project-name',
                'app-configs',
            ]);

            $result = $this->runTool($command);
            $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);

            $manifest = json_decode((string) file_get_contents($target . DIRECTORY_SEPARATOR . '.ai-install-manifest.json'), true);
            $this->assertIsArray($manifest);
            $placeholders = $manifest['placeholders'] ?? null;
            $this->assertIsArray($placeholders);
            $this->assertSame([], $placeholders['unresolved_required'] ?? null, 'strict install should resolve required placeholders even when matching files were skipped as identical');

            $projectContext = (string) file_get_contents($target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'project-context.md');
            $this->assertStringNotContainsString('<PRIMARY_STACK>', $projectContext);
            $this->assertStringContainsString('Primary stack: `unknown`', $projectContext);
        } finally {
            $this->removeTree($target);
        }
    }

    public function testVerifyInstallPlaceholdersIgnoresGeneratedAndReferenceDocs(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_placeholder_skip_' . uniqid('', true);
        $docsDir = $target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';
        $generatedDir = $docsDir . DIRECTORY_SEPARATOR . 'generated';

        mkdir($generatedDir, 0700, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'AGENTS.md', "# AGENTS\n");
        file_put_contents($docsDir . DIRECTORY_SEPARATOR . 'project-context.md', "# Project Context\n\n- Primary stack: `unknown`\n");
        copy(
            self::$repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'project-context-placeholders.md',
            $docsDir . DIRECTORY_SEPARATOR . 'project-context-placeholders.md'
        );
        file_put_contents($generatedDir . DIRECTORY_SEPARATOR . 'advisor-context.md', "# Generated\n\n<PROJECT_NAME>\n<PRIMARY_STACK>\n");

        try {
            $result = $this->runTool(
                'php tools/ai/verify-install-placeholders.php --target=' . escapeshellarg($target)
            );

            $this->assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
            $this->assertStringContainsString('OK: no required placeholders remain', $result['stdout']);
        } finally {
            $this->removeTree($target);
        }
    }

    public function testSelfTargetPlaceholderRewriteCanProcessSkippedUnmanagedFiles(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/core.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_self_target_placeholders_' . uniqid('', true);
        $docsDir = $target . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai';

        mkdir($docsDir, 0700, true);
        file_put_contents(
            $docsDir . DIRECTORY_SEPARATOR . 'project-context.md',
            "# Project Context\n\n- Primary stack: `<PRIMARY_STACK>`\n- Install: `<INSTALL_COMMAND>`\n"
        );

        try {
            aiInstallerApplyPlaceholders(
                $target,
                'app-configs',
                [[
                    'target' => 'docs/ai/project-context.md',
                    'action' => 'SKIP_EXISTING_UNMANAGED',
                ]],
                true
            );

            $projectContext = (string) file_get_contents($docsDir . DIRECTORY_SEPARATOR . 'project-context.md');
            $this->assertStringNotContainsString('<PRIMARY_STACK>', $projectContext);
            $this->assertStringNotContainsString('<INSTALL_COMMAND>', $projectContext);
            $this->assertStringContainsString('Primary stack: `unknown`', $projectContext);
            $this->assertStringContainsString('Install: `unknown`', $projectContext);
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

    public function testEnsureGitignoreEntriesCreatesFileWhenMissing(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/core.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_gitignore_create_' . uniqid('', true);
        mkdir($target, 0700, true);

        try {
            aiInstallerEnsureGitignoreEntries($target, ['.ai-backups/', '.ai-logs/', '.repomix-context/']);

            $gitignore = $target . DIRECTORY_SEPARATOR . '.gitignore';
            $this->assertFileExists($gitignore);
            $content = (string) file_get_contents($gitignore);
            $this->assertStringContainsString('# AI workflow runtime/generated files', $content);
            $this->assertStringContainsString('.ai-backups/', $content);
            $this->assertStringContainsString('.ai-logs/', $content);
            $this->assertStringContainsString('.repomix-context/', $content);
            $this->assertStringEndsWith("\n", $content);
        } finally {
            $this->removeTree($target);
        }
    }

    public function testEnsureGitignoreEntriesIsIdempotent(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/core.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_gitignore_idem_' . uniqid('', true);
        mkdir($target, 0700, true);

        try {
            $entries = ['.ai-backups/', '.ai-logs/', '.repomix-context/'];
            aiInstallerEnsureGitignoreEntries($target, $entries);
            $first = (string) file_get_contents($target . DIRECTORY_SEPARATOR . '.gitignore');
            aiInstallerEnsureGitignoreEntries($target, $entries);
            $second = (string) file_get_contents($target . DIRECTORY_SEPARATOR . '.gitignore');

            $this->assertSame($first, $second, 'second run must not change .gitignore');
            $this->assertSame(1, substr_count($second, '.ai-logs/'), 'no duplicate .ai-logs entry');
        } finally {
            $this->removeTree($target);
        }
    }

    public function testEnsureGitignoreEntriesPreservesContentAndDedupesVariants(): void
    {
        require_once self::$repoRoot . '/tools/ai/install/core.php';

        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'install_ai_gitignore_variants_' . uniqid('', true);
        mkdir($target, 0700, true);

        try {
            // Pre-existing user rules including tolerant variants of two roots.
            $original = "node_modules/\n.ai-logs\n.repomix-context/**\n";
            file_put_contents($target . DIRECTORY_SEPARATOR . '.gitignore', $original);

            aiInstallerEnsureGitignoreEntries($target, ['.ai-backups/', '.ai-logs/', '.repomix-context/']);
            $content = (string) file_get_contents($target . DIRECTORY_SEPARATOR . '.gitignore');

            // Existing content preserved verbatim at the top.
            $this->assertStringStartsWith($original, $content);
            $this->assertStringContainsString("node_modules/\n", $content);

            // Variant-covered roots are not re-added.
            $this->assertSame(1, substr_count($content, '.ai-logs'), 'existing .ai-logs variant not duplicated');
            $this->assertSame(1, substr_count($content, '.repomix-context'), 'existing .repomix-context variant not duplicated');

            // Only the genuinely-missing entry is appended.
            $this->assertStringContainsString('.ai-backups/', $content);
        } finally {
            $this->removeTree($target);
        }
    }
}
