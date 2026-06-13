<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P0.5 — Registry invariant / contract tests.
 *
 * Pure-derivation tests over aiInstallerScriptRegistry() that lock the
 * structural and fail-closed contract every entry must honour. These guard
 * later coverage slices (P1-P4) from silently introducing drift as new tool
 * entries are added.
 *
 * Scope note: only fields the code uses *unconditionally* are asserted as
 * universal. Tier-1+ metadata (tier/mutates_state/...) is intentionally
 * optional on simple tier-0 wrappers, so it is validated only when present.
 */
class ScriptRegistryInvariantTest extends TestCase
{
    private static string $repoRoot;

    /** @var array<string,array<string,mixed>> */
    private static array $registry;

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
        require_once $root . '/tools/ai/install/core.php';
        require_once $root . '/tools/ai/commands/helpers.php';
        require_once $root . '/tools/ai/commands/install_commands.php';
        self::$registry = aiInstallerScriptRegistry();
    }

    public function testRegistryIsNonEmpty(): void
    {
        $this->assertNotEmpty(self::$registry, 'script registry must not be empty');
    }

    /**
     * Every entry must carry the base fields the installer/gateway use
     * unconditionally, with a non-empty required_tools core.
     */
    public function testEveryEntryHasRequiredBaseFields(): void
    {
        foreach (self::$registry as $id => $entry) {
            foreach (['label', 'source_path', 'installed_path', 'pack', 'required_tools', 'risk'] as $field) {
                $this->assertArrayHasKey($field, $entry, "tool '$id' is missing base field '$field'");
            }
            $this->assertIsArray($entry['required_tools'], "tool '$id' required_tools must be an array");
            $this->assertNotEmpty($entry['required_tools'], "tool '$id' must declare a non-empty required_tools core");
            $this->assertContains('bash', $entry['required_tools'], "tool '$id' required_tools should include bash");
        }
    }

    public function testEveryEntryRiskIsKnown(): void
    {
        foreach (self::$registry as $id => $entry) {
            $this->assertContains(
                (string) $entry['risk'],
                ['read-only', 'mutating'],
                "tool '$id' has unknown risk '" . (string) $entry['risk'] . "'"
            );
        }
    }

    public function testTierWhenPresentIsKnown(): void
    {
        $validTiers = array_keys(aiInstallerCommandPolicyRiskTiers());
        foreach (self::$registry as $id => $entry) {
            if (!array_key_exists('tier', $entry)) {
                continue;
            }
            $this->assertContains(
                (string) $entry['tier'],
                $validTiers,
                "tool '$id' has unknown tier '" . (string) $entry['tier'] . "'"
            );
        }
    }

    /**
     * Each entry must resolve to a real script file via source_path or
     * installed_path, so the gateway can launch it.
     */
    public function testEveryEntryResolvesToARealScriptFile(): void
    {
        foreach (self::$registry as $id => $entry) {
            $path = aiInstallerResolveScriptPath(self::$repoRoot, $entry);
            $this->assertNotNull($path, "tool '$id' has no resolvable script file (source_path/installed_path)");
        }
    }

    // --- fail-closed profile contract -------------------------------------

    public function testEveryEntryResolvesToAtLeastOneProfile(): void
    {
        foreach (self::$registry as $id => $entry) {
            $this->assertNotEmpty(
                aiInstallerScriptProfiles($entry),
                "tool '$id' resolves to no profile"
            );
        }
    }

    public function testApprovalOrMutatingToolsAreNeverVisibleToReadonly(): void
    {
        foreach (self::$registry as $id => $entry) {
            if (!aiInstallerScriptRequiresApproval($entry)) {
                continue;
            }
            $this->assertNotContains(
                'readonly',
                aiInstallerScriptProfiles($entry),
                "approval/mutating tool '$id' must not be visible to the readonly profile"
            );
        }
    }

    public function testMutatingToolsRequireApproval(): void
    {
        foreach (self::$registry as $id => $entry) {
            if ((string) ($entry['risk'] ?? '') === 'mutating') {
                $this->assertTrue(
                    aiInstallerScriptRequiresApproval($entry),
                    "mutating tool '$id' must require approval"
                );
            }
        }
    }

    /**
     * P0 invariant: mode-specific optional_tools must be disjoint from the
     * always-required core, so a tool is never both required and optional.
     */
    public function testOptionalToolsAreDisjointFromRequiredTools(): void
    {
        foreach (self::$registry as $id => $entry) {
            if (!array_key_exists('optional_tools', $entry)) {
                continue;
            }
            $this->assertIsArray($entry['optional_tools'], "tool '$id' optional_tools must be an array");
            $overlap = array_intersect(
                (array) $entry['optional_tools'],
                (array) ($entry['required_tools'] ?? [])
            );
            $this->assertSame(
                [],
                array_values($overlap),
                "tool '$id' lists tools in both required_tools and optional_tools: " . implode(', ', $overlap)
            );
        }
    }
}
