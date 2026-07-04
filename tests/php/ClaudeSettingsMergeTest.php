<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/tools/ai/install/core.php';

class ClaudeSettingsMergeTest extends TestCase
{
    private function incomingTemplate(): string
    {
        return (string) json_encode([
            'permissions' => [
                'allow' => ['Bash(git status*)', 'Bash(git diff*)'],
                'deny' => ['Bash(rm -rf *)', 'Bash(sudo *)'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function graphifyStyleExisting(): string
    {
        // Mirrors this repo's real .claude/settings.json shape (graphify's own installer output):
        // a PreToolUse hooks block, no permissions key at all.
        return (string) json_encode([
            'hooks' => [
                'PreToolUse' => [
                    [
                        'matcher' => 'Bash',
                        'hooks' => [['type' => 'command', 'command' => 'graphify-check.sh']],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function testNoExistingFileReturnsIncomingContent(): void
    {
        $merged = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), '');
        $decoded = json_decode($merged, true);
        $this->assertSame(['Bash(git status*)', 'Bash(git diff*)'], $decoded['permissions']['allow']);
        $this->assertSame(['Bash(rm -rf *)', 'Bash(sudo *)'], $decoded['permissions']['deny']);
    }

    public function testPreservesPreExistingGraphifyHooksWhileAddingPermissions(): void
    {
        $merged = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $this->graphifyStyleExisting());
        $decoded = json_decode($merged, true);

        // graphify's own hook must survive verbatim.
        $this->assertArrayHasKey('PreToolUse', $decoded['hooks']);
        $this->assertSame('graphify-check.sh', $decoded['hooks']['PreToolUse'][0]['hooks'][0]['command']);

        // the kit's new permissions must also be present.
        $this->assertContains('Bash(git status*)', $decoded['permissions']['allow']);
        $this->assertContains('Bash(rm -rf *)', $decoded['permissions']['deny']);
    }

    public function testMergeIsIdempotentAcrossRepeatedInstalls(): void
    {
        $firstPass = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $this->graphifyStyleExisting());
        $secondPass = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $firstPass);

        $firstDecoded = json_decode($firstPass, true);
        $secondDecoded = json_decode($secondPass, true);

        // Re-running the merge with the same incoming template must not grow the arrays.
        $this->assertSame($firstDecoded['permissions']['allow'], $secondDecoded['permissions']['allow']);
        $this->assertSame($firstDecoded['permissions']['deny'], $secondDecoded['permissions']['deny']);
        $this->assertCount(1, $secondDecoded['hooks']['PreToolUse'], 'hook block must not duplicate on repeated merge');
    }

    public function testUserAddedAllowRuleSurvivesReinstall(): void
    {
        // Simulate a user who manually added an extra allow rule after the first install.
        $existing = $this->incomingTemplate();
        $existingDecoded = json_decode($existing, true);
        $existingDecoded['permissions']['allow'][] = 'Bash(npm run lint)';
        $existingWithUserRule = (string) json_encode($existingDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $merged = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $existingWithUserRule);
        $decoded = json_decode($merged, true);

        $this->assertContains('Bash(npm run lint)', $decoded['permissions']['allow'], 'user-added rule must not be dropped');
    }

    public function testInvalidExistingJsonIsReturnedUnchangedRatherThanCorrupted(): void
    {
        $invalidExisting = '{ this is not valid json';
        $merged = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $invalidExisting);
        $this->assertSame($invalidExisting, $merged, 'must never silently discard or corrupt unparseable existing content');
    }

    public function testMalformedPermissionsBlockDoesNotCorruptOrWarn(): void
    {
        // permissions is a scalar, not an object - a hand-corrupted but still valid-JSON file.
        // Must not trigger PHP "Illegal string offset" behavior or leak stray characters into
        // the merged allow/deny arrays.
        $malformedExisting = (string) json_encode(['permissions' => 'oops']);
        $merged = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $malformedExisting);
        $decoded = json_decode($merged, true);

        $this->assertSame(['Bash(git status*)', 'Bash(git diff*)'], $decoded['permissions']['allow']);
        $this->assertSame(['Bash(rm -rf *)', 'Bash(sudo *)'], $decoded['permissions']['deny']);
    }

    public function testMalformedHooksBlockDoesNotCorruptOrWarn(): void
    {
        // hooks is a scalar, not an object - same corruption class as the permissions test above.
        $malformedExisting = (string) json_encode(['hooks' => 'oops']);
        $merged = aiInstallerMergeClaudeSettingsJson($this->incomingTemplate(), $malformedExisting);
        $decoded = json_decode($merged, true);

        $this->assertSame(['Bash(git status*)', 'Bash(git diff*)'], $decoded['permissions']['allow']);
        $this->assertIsArray($decoded['hooks'] ?? null);
    }

    public function testMergeFileWritesDestinationAndCreatesParentDir(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'claude_settings_merge_' . uniqid('', true);
        $src = $tmp . DIRECTORY_SEPARATOR . 'settings.json';
        $dest = $tmp . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . 'settings.json';

        mkdir($tmp, 0777, true);
        file_put_contents($src, $this->incomingTemplate());

        try {
            aiInstallerMergeClaudeSettingsFile($src, $dest);
            $this->assertFileExists($dest);
            $decoded = json_decode((string) file_get_contents($dest), true);
            $this->assertContains('Bash(git status*)', $decoded['permissions']['allow']);
        } finally {
            $this->removeTree($tmp);
        }
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir($path);
    }
}
