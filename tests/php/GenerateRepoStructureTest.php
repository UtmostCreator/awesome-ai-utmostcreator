<?php

declare(strict_types=1);

namespace Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class GenerateRepoStructureTest extends TestCase
{
    private string $tmpDir;
    private string $repoRoot;
    private string $phpBin;

    protected function setUp(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new RuntimeException('Could not resolve repo root');
        }

        $this->repoRoot = $root;
        $this->phpBin = (string) PHP_BINARY;
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'repo_structure_test_' . uniqid('', true);

        if (!mkdir($this->tmpDir, 0700, true) && !is_dir($this->tmpDir)) {
            throw new RuntimeException("Could not create temp directory: {$this->tmpDir}");
        }
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmpDir);
    }

    public function testValidMetadataPasses(): void
    {
        $fixture = $this->createFixtureRepo();
        $metadataPath = $this->writeMetadata($fixture, $this->baseDirectories());

        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertFileExists($fixture . '/out/repo-structure.json');
        $this->assertFileExists($fixture . '/out/repo-structure.csv');
        $this->assertFileExists($fixture . '/out/repo-structure.md');
        $this->assertFileExists($fixture . '/out/repo-structure.log');
    }

    public function testUnsupportedSchemaVersionFails(): void
    {
        $fixture = $this->createFixtureRepo();
        $metadataPath = $this->writeMetadata($fixture, $this->baseDirectories(), 99);

        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('unsupported metadata schema_version', $result['stderr']);
    }

    public function testDuplicateMetadataPathFails(): void
    {
        $fixture = $this->createFixtureRepo();
        $directories = $this->baseDirectories();
        $directories[] = $directories[0];
        $metadataPath = $this->writeMetadata($fixture, $directories);

        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('duplicate metadata path', $result['stderr']);
    }

    public function testMissingRequiredFieldFails(): void
    {
        $fixture = $this->createFixtureRepo();
        $directories = $this->baseDirectories();
        unset($directories[1]['purpose']);
        $metadataPath = $this->writeMetadata($fixture, $directories);

        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString("missing required field 'purpose'", $result['stderr']);
    }

    public function testMissingRootMetadataFailsWhenRootFilesExist(): void
    {
        $fixture = $this->createFixtureRepo();
        $directories = array_values(array_filter(
            $this->baseDirectories(),
            static fn(array $entry): bool => $entry['path'] !== '.'
        ));
        $metadataPath = $this->writeMetadata($fixture, $directories);

        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString("metadata entry for '.' is required", $result['stderr']);
    }

    public function testBadReferencePathFails(): void
    {
        $fixture = $this->createFixtureRepo();
        $directories = $this->baseDirectories();
        $directories[1]['install_guide'] = 'docs/ai/missing.md';
        $metadataPath = $this->writeMetadata($fixture, $directories);

        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString("metadata reference 'install_guide' points to missing file", $result['stderr']);
    }

    public function testMissingTopLevelMetadataFails(): void
    {
        $fixture = $this->createFixtureRepo();

        if (!mkdir($fixture . '/scripts', 0700, true) && !is_dir($fixture . '/scripts')) {
            throw new RuntimeException('Could not create scripts fixture directory');
        }

        file_put_contents($fixture . '/scripts/run.sh', "#!/usr/bin/env bash\n");
        $this->git($fixture, 'git add scripts/run.sh');

        $metadataPath = $this->writeMetadata($fixture, $this->baseDirectories());
        $result = $this->runGenerator($fixture, $metadataPath);

        $this->assertNotSame(0, $result['exit']);
        $this->assertStringContainsString('missing metadata for top-level paths: scripts', $result['stderr']);
    }

    private function createFixtureRepo(): string
    {
        $fixture = $this->tmpDir . DIRECTORY_SEPARATOR . 'fixture';

        if (!mkdir($fixture, 0700, true) && !is_dir($fixture)) {
            throw new RuntimeException("Could not create fixture directory: {$fixture}");
        }

        $this->git($fixture, 'git init');

        if (!mkdir($fixture . '/docs/ai', 0700, true) && !is_dir($fixture . '/docs/ai')) {
            throw new RuntimeException('Could not create docs/ai fixture directory');
        }

        if (!mkdir($fixture . '/tools/ai', 0700, true) && !is_dir($fixture . '/tools/ai')) {
            throw new RuntimeException('Could not create tools/ai fixture directory');
        }

        file_put_contents($fixture . '/README.md', "# Fixture\n");
        file_put_contents($fixture . '/docs/ai/external-repo-install.md', "# Install\n");
        file_put_contents($fixture . '/docs/ai/context-packing.md', "# Context\n");
        file_put_contents($fixture . '/tools/ai/install-copilot-kit.sh', "#!/usr/bin/env bash\n");

        $this->git($fixture, 'git add README.md docs/ai/external-repo-install.md docs/ai/context-packing.md tools/ai/install-copilot-kit.sh');

        return $fixture;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function baseDirectories(): array
    {
        return [
            [
                'path' => '.',
                'purpose' => 'Root files',
                'designed_for' => 'Humans and tools',
                'install_guide' => 'docs/ai/external-repo-install.md',
                'install_script' => 'none',
                'ai_entrypoint' => 'README.md',
                'notes' => 'Root metadata',
            ],
            [
                'path' => 'docs',
                'purpose' => 'Docs',
                'designed_for' => 'Humans and agents',
                'install_guide' => 'docs/ai/external-repo-install.md',
                'install_script' => 'tools/ai/install-copilot-kit.sh',
                'ai_entrypoint' => 'docs/ai/context-packing.md',
                'notes' => 'Docs metadata',
            ],
            [
                'path' => 'tools',
                'purpose' => 'Tools',
                'designed_for' => 'Maintainers',
                'install_guide' => 'docs/ai/external-repo-install.md',
                'install_script' => 'tools/ai/install-copilot-kit.sh',
                'ai_entrypoint' => 'tools/ai/install-copilot-kit.sh',
                'notes' => 'Tools metadata',
            ],
        ];
    }

    /**
     * @param array<int, array<string, string>> $directories
     */
    private function writeMetadata(string $fixture, array $directories, int $schemaVersion = 1): string
    {
        $payload = [
            'schema_version' => $schemaVersion,
            'directories' => $directories,
            'metadata_exemptions' => [],
        ];

        $path = $fixture . '/metadata.json';
        file_put_contents($path, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runGenerator(string $fixture, string $metadataPath): array
    {
        $command = sprintf(
            '%s %s --root=. --output-dir=out --metadata=%s',
            escapeshellarg($this->phpBin),
            escapeshellarg($this->repoRoot . '/tools/ai/generate-repo-structure.php'),
            escapeshellarg($metadataPath)
        );

        return $this->runCommand($command, $fixture);
    }

    private function git(string $cwd, string $command): void
    {
        $result = $this->runCommand($command, $cwd);
        $this->assertSame(0, $result['exit'], $result['stderr']);
    }

    /**
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private function runCommand(string $command, string $cwd): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $path = $this->buildTestPath();
        putenv('PATH=' . $path);
        $_ENV['PATH'] = $path;
        $_SERVER['PATH'] = $path;

        $env = [
            'HOME' => sys_get_temp_dir(),
            'XDG_CONFIG_HOME' => sys_get_temp_dir(),
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'PATH' => $path,
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);

        $this->assertIsResource($process, "proc_open failed for: {$command}");

        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit = proc_close($process);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }

    private function removeTree(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            $this->removeFileWithRetry($path);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();

            if ($item->isDir() && !$item->isLink()) {
                $this->removeDirectoryWithRetry($itemPath);
                continue;
            }

            $this->removeFileWithRetry($itemPath);
        }

        $this->removeDirectoryWithRetry($path);
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

    private function removeFileWithRetry(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            @chmod($path, 0600);

            if (@unlink($path)) {
                return;
            }

            clearstatcache(true, $path);

            if (!file_exists($path) && !is_link($path)) {
                return;
            }

            usleep(50_000 * $attempt);
        }

        throw new RuntimeException("Unable to delete file: {$path}");
    }

    private function removeDirectoryWithRetry(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            @chmod($path, 0700);

            if (@rmdir($path)) {
                return;
            }

            clearstatcache(true, $path);

            if (!is_dir($path)) {
                return;
            }

            usleep(50_000 * $attempt);
        }

        throw new RuntimeException("Unable to delete directory: {$path}");
    }
}
