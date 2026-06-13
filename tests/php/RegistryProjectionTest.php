<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P3.3 — Registry projection parity + invariants.
 *
 * Proves that docs/ai/script-registry.json is a faithful generated projection
 * of the canonical PHP registry (aiInstallerScriptRegistry) via the single
 * normalizer, and that the projected, derived metadata honours the fail-closed
 * contract. These tests are the unit-level drift gate behind
 * `php tools/ai/ai.php registry:export --check`.
 */
class RegistryProjectionTest extends TestCase
{
    private static string $repoRoot;

    /** @var array<string,array<string,mixed>> */
    private static array $normalized;

    /** @var array<string,array<string,mixed>> */
    private static array $jsonScripts;

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

        self::$normalized = aiInstallerNormalizedScriptRegistry();

        $jsonPath = $root . '/docs/ai/script-registry.json';
        $decoded = json_decode((string) file_get_contents($jsonPath), true);
        self::assertIsArray($decoded, 'docs/ai/script-registry.json must be valid JSON');
        self::$jsonScripts = $decoded['scripts'] ?? [];
    }

    // --- parity: committed JSON == generated projection --------------------

    public function testCommittedJsonMatchesGeneratedProjection(): void
    {
        $expected = aiInstallerRenderScriptRegistryJson();
        $actual = (string) file_get_contents(self::$repoRoot . '/docs/ai/script-registry.json');
        $this->assertSame(
            $expected,
            $actual,
            'docs/ai/script-registry.json is out of date. Run: php tools/ai/ai.php registry:export --output docs/ai/script-registry.json'
        );
    }

    public function testEveryPhpIdAppearsInJsonAndViceVersa(): void
    {
        $phpIds = array_keys(self::$normalized);
        $jsonIds = array_keys(self::$jsonScripts);
        sort($phpIds);
        sort($jsonIds);
        $this->assertSame($phpIds, $jsonIds, 'PHP registry ids and generated JSON ids must match exactly');
    }

    public function testRenderedJsonIsDeterministic(): void
    {
        $this->assertSame(
            aiInstallerRenderScriptRegistryJson(),
            aiInstallerRenderScriptRegistryJson(),
            'registry projection must be byte-stable across runs'
        );
    }

    // --- projection invariants --------------------------------------------

    public function testEveryNormalizedEntryHasKeyEqualToId(): void
    {
        foreach (self::$normalized as $id => $entry) {
            $this->assertSame($id, $entry['id'] ?? null, "normalized entry key must equal its id field for '$id'");
        }
    }

    public function testEveryEntryHasProfilesAndAutonomyLevel(): void
    {
        foreach (self::$normalized as $id => $entry) {
            $this->assertNotEmpty($entry['profiles'] ?? [], "entry '$id' must project a non-empty profiles list");
            $this->assertNotEmpty($entry['autonomy_level'] ?? '', "entry '$id' must project an autonomy_level");
            foreach ((array) $entry['profiles'] as $profile) {
                $this->assertContains($profile, ['readonly', 'verify', 'impl'], "entry '$id' has unknown profile '$profile'");
            }
        }
    }

    public function testMutatingEntriesRequireApprovalAndAreImplOnly(): void
    {
        foreach (self::$normalized as $id => $entry) {
            if (($entry['risk'] ?? '') !== 'mutating') {
                continue;
            }
            $this->assertTrue($entry['requires_approval'] ?? false, "mutating entry '$id' must require approval");
            $this->assertNotContains('readonly', (array) $entry['profiles'], "mutating entry '$id' must not be readonly-visible");
        }
    }

    public function testReadOnlyEntriesDoNotMutateState(): void
    {
        foreach (self::$normalized as $id => $entry) {
            if (($entry['risk'] ?? '') !== 'read-only') {
                continue;
            }
            $this->assertFalse($entry['mutates_state'] ?? false, "read-only entry '$id' must not declare mutates_state=true");
        }
    }

    public function testActWithApprovalEntriesRequireApproval(): void
    {
        foreach (self::$normalized as $id => $entry) {
            if (($entry['autonomy_level'] ?? '') === 'act_with_approval') {
                $this->assertTrue($entry['requires_approval'] ?? false, "act_with_approval entry '$id' must require approval");
            }
        }
    }

    public function testTierWhenPresentIsKnown(): void
    {
        $validTiers = array_keys(aiInstallerCommandPolicyRiskTiers());
        foreach (self::$normalized as $id => $entry) {
            if (!array_key_exists('tier', $entry)) {
                continue;
            }
            $this->assertContains((string) $entry['tier'], $validTiers, "entry '$id' has unknown tier '" . (string) $entry['tier'] . "'");
        }
    }

    public function testRiskIsRestrictedToMutationClasses(): void
    {
        foreach (self::$normalized as $id => $entry) {
            $this->assertContains((string) ($entry['risk'] ?? ''), ['read-only', 'mutating'], "entry '$id' has non-mutation-class risk");
        }
    }
}
