<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the machine-readable placeholder registry
 * (packages/ai-universal-rules/placeholders.json) and the registry-driven
 * `placeholders --apply` substitution path.
 */
class PlaceholderRegistryTest extends TestCase
{
    private static string $repoRoot;

    /** @var array<string,mixed> */
    private static array $registry;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;

        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/commands/install_extras.php';

        $registryPath = $root . '/packages/ai-universal-rules/placeholders.json';
        self::assertFileExists($registryPath, 'placeholders.json registry must ship with the package');
        $decoded = json_decode((string) file_get_contents($registryPath), true);
        self::assertIsArray($decoded, 'placeholders.json must be valid JSON');
        self::$registry = $decoded;
    }

    public function testRegistryEnvelopeShape(): void
    {
        $this->assertSame('ai.placeholders-registry.v1', self::$registry['schema'] ?? null);
        $this->assertIsArray(self::$registry['tokens'] ?? null);
        $this->assertNotEmpty(self::$registry['tokens']);

        $seen = [];
        foreach (self::$registry['tokens'] as $entry) {
            $this->assertIsArray($entry);
            $token = $entry['token'] ?? null;
            $this->assertIsString($token);
            $this->assertMatchesRegularExpression('/^<[A-Z0-9_]+>$/', $token);
            $this->assertArrayNotHasKey($token, $seen, "duplicate registry token {$token}");
            $seen[$token] = true;
            $this->assertIsBool($entry['required'] ?? null, "{$token} must declare required");
            $this->assertIsBool($entry['substitute'] ?? null, "{$token} must declare substitute");
            $this->assertArrayHasKey('projectYmlKey', $entry, "{$token} must declare projectYmlKey (nullable)");
        }
    }

    public function testRegistryTokensMatchPlaceholdersDoc(): void
    {
        $doc = (string) file_get_contents(self::$repoRoot . '/packages/ai-universal-rules/PLACEHOLDERS.md');
        preg_match_all('/`(<[A-Z0-9_]+>)`/', $doc, $m);
        $documented = array_values(array_unique($m[1]));

        $registryTokens = array_map(
            static fn(array $entry): string => (string) $entry['token'],
            self::$registry['tokens']
        );

        sort($documented);
        sort($registryTokens);
        $this->assertSame($documented, $registryTokens, 'placeholders.json and PLACEHOLDERS.md must document the same token set');
    }

    public function testRequiredTokensMatchInstallerContract(): void
    {
        $registryRequired = [];
        foreach (self::$registry['tokens'] as $entry) {
            if (($entry['required'] ?? false) === true) {
                $registryRequired[] = (string) $entry['token'];
            }
        }

        $installerRequired = \aiInstallerRequiredPlaceholderTokens(self::$repoRoot);
        sort($registryRequired);
        sort($installerRequired);
        $this->assertSame($registryRequired, $installerRequired, 'installer required-token gate must come from the registry');

        // Pin the strict-gate contract so accidental registry edits fail loudly.
        $expected = [
            '<BUILD_COMMAND>', '<CI_COMMANDS>', '<EDITORCONFIG_PATH>', '<FILE_PLACEMENT_RULES>',
            '<FORMATTER_CONFIG_FILES>', '<FORMAT_COMMAND>', '<GENERATED_FILES>', '<GOLDEN_EXAMPLES>',
            '<IGNORE_FILES>', '<INSTALL_COMMAND>', '<LINTER_CONFIG_FILES>', '<LINT_COMMAND>',
            '<NAMING_RULES>', '<PACKAGE_MANAGER>', '<PRIMARY_LANGUAGE>', '<PRIMARY_RUNTIME>',
            '<PRIMARY_STACK>', '<PROJECT_NAME>', '<PROJECT_TYPE>', '<PROTECTED_FILES>',
            '<PROTECTED_PATHS>', '<SOURCE_DIRS>', '<TEST_COMMAND>', '<TEST_DIRS>',
        ];
        $this->assertSame($expected, $registryRequired, 'strict-gate required token set changed; update verify-install-placeholders.php fallback and this pin together');
    }

    public function testProjectYmlKeysExistInInstallerDefaults(): void
    {
        $emptyTarget = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-registry-defaults-' . bin2hex(random_bytes(4));
        mkdir($emptyTarget, 0777, true);
        try {
            $defaults = \aiInstallerLoadProjectValues($emptyTarget, 'registry-test');
            foreach (self::$registry['tokens'] as $entry) {
                $key = $entry['projectYmlKey'] ?? null;
                if ($key === null) {
                    continue;
                }
                $this->assertArrayHasKey((string) $key, $defaults, "registry projectYmlKey '{$key}' must exist in installer project.yml defaults");
            }
        } finally {
            rmdir($emptyTarget);
        }
    }

    public function testApplySubstitutesMappedTokensAndSkipsFormatSlots(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-registry-apply-' . bin2hex(random_bytes(4));
        mkdir($target . '/.ai', 0777, true);
        mkdir($target . '/docs/ai/generated', 0777, true);

        try {
            copy(
                self::$repoRoot . '/packages/ai-universal-rules/placeholders.json',
                $target . '/.ai/placeholders.json'
            );
            file_put_contents(
                $target . '/.ai/project.yml',
                "projectName: \"acme-portal\"\ntestCommand: \"composer test\"\n"
            );
            file_put_contents(
                $target . '/docs/ai/sample.md',
                "# <PROJECT_NAME>\nRun <TEST_COMMAND>.\nBlocked by unknown: <UNKNOWN>\nLanguage: <PRIMARY_LANGUAGE>\n"
            );
            file_put_contents(
                $target . '/docs/ai/generated/skip.md',
                "generated <PROJECT_NAME>\n"
            );

            $result = \aiPlaceholderApplyFromProjectValues($target, null);

            $this->assertTrue($result['registry_found']);
            $this->assertContains('<PROJECT_NAME>', $result['tokens_with_values']);
            $this->assertContains('<TEST_COMMAND>', $result['tokens_with_values']);

            $sample = (string) file_get_contents($target . '/docs/ai/sample.md');
            $this->assertStringContainsString('# acme-portal', $sample);
            $this->assertStringContainsString('Run composer test.', $sample);
            $this->assertStringContainsString('<UNKNOWN>', $sample, 'format slots must never be auto-replaced');
            $this->assertStringContainsString('<PRIMARY_LANGUAGE>', $sample, "tokens whose project.yml value is 'unknown' must stay unresolved");

            $generated = (string) file_get_contents($target . '/docs/ai/generated/skip.md');
            $this->assertStringContainsString('<PROJECT_NAME>', $generated, 'generated paths must be skipped');
        } finally {
            @unlink($target . '/.ai/placeholders.json');
            @unlink($target . '/.ai/project.yml');
            @unlink($target . '/docs/ai/sample.md');
            @unlink($target . '/docs/ai/generated/skip.md');
            @rmdir($target . '/docs/ai/generated');
            @rmdir($target . '/docs/ai');
            @rmdir($target . '/docs');
            @rmdir($target . '/.ai');
            @rmdir($target);
        }
    }

    public function testExplicitFilesScopeLimitsApply(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-registry-scope-' . bin2hex(random_bytes(4));
        mkdir($target . '/.ai', 0777, true);
        mkdir($target . '/docs/ai', 0777, true);

        try {
            copy(
                self::$repoRoot . '/packages/ai-universal-rules/placeholders.json',
                $target . '/.ai/placeholders.json'
            );
            file_put_contents($target . '/.ai/project.yml', "projectName: \"acme-portal\"\n");
            file_put_contents($target . '/docs/ai/in-scope.md', "<PROJECT_NAME>\n");
            file_put_contents($target . '/docs/ai/out-of-scope.md', "<PROJECT_NAME>\n");

            $result = \aiPlaceholderApplyFromProjectValues($target, 'docs/ai/in-scope.md');

            $this->assertSame(1, $result['files_changed_count']);
            $this->assertStringContainsString('acme-portal', (string) file_get_contents($target . '/docs/ai/in-scope.md'));
            $this->assertStringContainsString('<PROJECT_NAME>', (string) file_get_contents($target . '/docs/ai/out-of-scope.md'));
        } finally {
            @unlink($target . '/.ai/placeholders.json');
            @unlink($target . '/.ai/project.yml');
            @unlink($target . '/docs/ai/in-scope.md');
            @unlink($target . '/docs/ai/out-of-scope.md');
            @rmdir($target . '/docs/ai');
            @rmdir($target . '/docs');
            @rmdir($target . '/.ai');
            @rmdir($target);
        }
    }

    public function testExplicitFilesScopeRejectsPathTraversal(): void
    {
        $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-registry-traversal-' . bin2hex(random_bytes(4));
        mkdir($target . '/.ai', 0777, true);

        try {
            copy(
                self::$repoRoot . '/packages/ai-universal-rules/placeholders.json',
                $target . '/.ai/placeholders.json'
            );
            file_put_contents($target . '/.ai/project.yml', "projectName: \"acme-portal\"\n");

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('path traversal');

            \aiPlaceholderApplyFromProjectValues($target, '../outside.md');
        } finally {
            @unlink($target . '/.ai/placeholders.json');
            @unlink($target . '/.ai/project.yml');
            @rmdir($target . '/.ai');
            @rmdir($target);
        }
    }
}
