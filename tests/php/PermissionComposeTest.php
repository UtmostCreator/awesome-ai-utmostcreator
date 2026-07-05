<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/install/permission-layers/compose.php';

final class PermissionComposeTest extends TestCase
{
    /**
     * Slice D de-pollution proof (docs/tickets/arch-todo-complete-permission-composition-
     * migration/plan.md, AC-D3): a composition with no `language_overlays` key gets NONE of
     * the PHP/JS atomic overlay patterns — proving a hypothetical non-PHP/non-JS consumer
     * agent is not polluted with these commands merely by using the 'impl' profile.
     */
    public function testCompositionWithoutLanguageOverlaysGetsNoPhpOrJsPatterns(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'impl',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
        ]);

        foreach (['php -l *', 'vendor/bin/phpunit *', 'composer validate*', 'npm test*', 'pnpm test*'] as $pattern) {
            $key = aiPermissionModelKey('bash', $pattern);
            self::assertArrayNotHasKey($key, $result['model'], "'{$pattern}' must not appear without an explicit language_overlays entry");
        }
    }

    /**
     * Slice D: the 4 new atomic overlay keys grant exactly their intended pattern set and
     * nothing more — proving each is a precise, additive, single-purpose overlay rather than
     * a hidden re-bundling of the old coarse packs.
     */
    public function testAtomicLanguageOverlaysGrantExactlyTheirOwnPatterns(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'language_overlays' => ['php-lint', 'php-phpunit', 'php-composer-validate', 'js-core'],
        ]);

        foreach ([
            'php -l *',
            'vendor/bin/phpunit *', './vendor/bin/phpunit *', 'phpunit *',
            'composer validate*',
            'npm test*', 'npm run test*', 'npm run lint*', 'npm run typecheck*',
            'pnpm test*', 'pnpm run test*', 'pnpm run lint*', 'pnpm run typecheck*',
        ] as $pattern) {
            $key = aiPermissionModelKey('bash', $pattern);
            self::assertSame('allow', $result['model'][$key]['effect'] ?? null, "'{$pattern}' must be granted");
        }

        // Not part of these 4 atomic overlays (yarn/bun/paratest are only in the coarse
        // 'php'/'js-ts' overlays, e.g. implementer's wiring).
        foreach (['yarn test*', 'bun test*', 'paratest *'] as $pattern) {
            $key = aiPermissionModelKey('bash', $pattern);
            self::assertArrayNotHasKey($key, $result['model'], "'{$pattern}' must not leak from the 4 atomic overlays");
        }
    }

    public function testLaterLayerWinsBeforeImmutableFloor(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'git status*', 'effect' => 'ask'],
            ],
        ]);

        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', 'git status*')]['effect']);
        self::assertSame('agent:exceptions', $result['model'][aiPermissionModelKey('bash', 'git status*')]['layer']);
    }

    public function testLaterWinsIsDeterministicForDuplicateExceptions(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'custom-tool *', 'effect' => 'allow'],
                ['permission' => 'bash', 'pattern' => 'custom-tool *', 'effect' => 'ask'],
            ],
        ]);

        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', 'custom-tool *')]['effect']);
    }

    public function testImmutableFloorRejectsHardDenyWeakening(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('weakens immutable floor');

        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'rm -rf *', 'effect' => 'allow'],
            ],
        ]);
    }

    public function testExceptionMayTightenAndMayGrantNewPattern(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'git status*', 'effect' => 'deny'],
                ['permission' => 'bash', 'pattern' => 'project-safe-read *', 'effect' => 'allow'],
            ],
        ]);

        self::assertSame('deny', $result['model'][aiPermissionModelKey('bash', 'git status*')]['effect']);
        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'project-safe-read *')]['effect']);
    }

    public function testBashStarPinsBaselineAndRejectsLoosening(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'shipped_star_baseline' => 'ask',
        ]);

        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', '*')]['effect']);

        $this->expectException(RuntimeException::class);
        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'shipped_star_baseline' => 'deny',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => '*', 'effect' => 'ask'],
            ],
        ]);
    }

    public function testProvenanceMatchesMergeOrder(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'impl',
            'edit_surface' => 'code',
            'verify_tier' => 'verify-focused',
            'language_overlays' => ['php', 'shell'],
        ]);

        self::assertSame([
            'core:safe-read',
            'core:git-read',
            'script-tiers:ai-deny-dangerous',
            'script-tiers:ai-context-ask',
            'script-tiers:ai-read',
            'script-tiers:ai-verify',
            'script-tiers:ai-write',
            'core:git-mutating-ask',
            'core:package-manager-ask',
            'verify-tiers:verify-focused',
            'core:shipped-cli-readonly',
            'language-overlays:php',
            'language-overlays:shell',
            'edit-surfaces:code',
            'agent:exceptions',
            'core:hard-deny',
        ], $result['layers']);
    }

    public function testReadonlyProfileContainsNoGitMutationOrPackageManagerEntries(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
        ]);

        self::assertArrayNotHasKey(aiPermissionModelKey('bash', 'git add*'), $result['model']);
        self::assertArrayNotHasKey(aiPermissionModelKey('bash', 'composer install*'), $result['model']);
    }

    public function testImplProfileContainsGitMutationAndPackageManagerAskEntries(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'impl',
            'edit_surface' => 'code',
            'verify_tier' => 'verify-focused',
        ]);

        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', 'git add*')]['effect']);
        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', 'composer install*')]['effect']);
    }

    public function testDangerousScriptsDenyByDefaultButAreNotOnTheImmutableFloor(): void
    {
        $default = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
        ]);
        self::assertSame('deny', $default['model'][aiPermissionModelKey('bash', 'bash scripts/ai/gh-pr-context.sh *')]['effect']);

        $overridden = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'bash scripts/ai/gh-pr-context.sh *', 'effect' => 'allow'],
            ],
        ]);
        self::assertSame('allow', $overridden['model'][aiPermissionModelKey('bash', 'bash scripts/ai/gh-pr-context.sh *')]['effect']);
    }

    public function testTrulyUniversalDangerousScriptsRemainOnImmutableFloor(): void
    {
        $this->expectException(RuntimeException::class);

        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'bash scripts/ai/ai-task.sh *', 'effect' => 'allow'],
            ],
        ]);
    }

    // --- Slice 10: permission pack tests ---

    public function testAllowPackGrantsItsEntries(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'allow_packs' => ['proof.security'],
        ]);

        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'semgrep *')]['effect']);
        self::assertSame('agent:allow-packs:proof.security', $result['model'][aiPermissionModelKey('bash', 'semgrep *')]['layer']);
    }

    public function testDenyPackTightensACoreDefault(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'deny_packs' => ['core.safe_read.deny_common_generics'],
        ]);

        self::assertSame('deny', $result['model'][aiPermissionModelKey('bash', 'date *')]['effect']);
    }

    public function testAskPackGrantsAskEffect(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'ask_packs' => ['context.packaging'],
        ]);

        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', 'repomix *')]['effect']);
    }

    public function testUnknownPackNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'allow_packs' => ['not-a-real-pack'],
        ]);
    }

    public function testExceptionsOverridePacksForTheSamePattern(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'allow_packs' => ['proof.security'],
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'semgrep *', 'effect' => 'ask'],
            ],
        ]);

        // Finest-grained override (exceptions) applies after packs in merge order.
        self::assertSame('ask', $result['model'][aiPermissionModelKey('bash', 'semgrep *')]['effect']);
        self::assertSame('agent:exceptions', $result['model'][aiPermissionModelKey('bash', 'semgrep *')]['layer']);
    }

    public function testPacksAreEachEffectHomogeneous(): void
    {
        foreach (aiPermissionPacks() as $packName => $entries) {
            $effects = array_unique(array_map(static fn (array $e): string => $e['effect'], $entries));
            self::assertCount(1, $effects, "pack '{$packName}' must be effect-homogeneous, found: " . implode(',', $effects));
        }
    }

    /**
     * AC-8 (docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md): every
     * agent in the single agent->profile map (aiInstallerAgentProfiles(), script-registry.php) must
     * have a permission composition. Both previously-documented intentional exclusions
     * (release-auditor, architecture-plan-writer) were migrated in Slices A and C of
     * docs/tickets/arch-todo-complete-permission-composition-migration/plan.md — the exclusion
     * list is now empty. Kept as an explicit empty array (not deleted) so a future agent
     * needing a temporary, documented exclusion has an obvious place to add one, and so this
     * test's intent (compositions.php key set tracks aiInstallerAgentProfiles() 1:1, modulo any
     * documented exclusion) stays self-explanatory.
     *
     * Scope note (docs/tickets/arch-todo-optional-agent-permission-composition-
     * 20260705T221434Z/plan.md, Design Fork F1, LOCKED): the 11 optional agents under
     * `packages/ai-universal-rules/templates/optional/agents/` are deliberately NOT added to
     * `aiInstallerAgentProfiles()` (that map stays scoped to tool-gateway visibility for the
     * 15 core agents only) even though `compositions.php` now also composes some of them.
     * This test therefore excludes `aiPermissionOptionalAgentKeys()` before comparing, so it
     * stays a pure 1:1 check against the 15-key core map — see
     * `testEveryOptionalAgentKeyHasACompositionEntry()` below for the optional-agent coverage
     * assertion (kept as a separate, additive invariant per the locked design fork).
     */
    public function testCompositionsKeySetMatchesAgentProfilesExceptDocumentedExclusions(): void
    {
        require_once __DIR__ . '/../../tools/ai/install/script-registry.php';

        $intentionalExclusions = [];

        $compositionKeys = array_diff(array_keys(aiPermissionAgentCompositions()), aiPermissionOptionalAgentKeys());
        $profileKeys = array_keys(aiInstallerAgentProfiles());

        $expectedCompositionKeys = array_values(array_diff($profileKeys, $intentionalExclusions));
        $compositionKeys = array_values($compositionKeys);
        sort($expectedCompositionKeys);
        sort($compositionKeys);

        self::assertSame(
            $expectedCompositionKeys,
            $compositionKeys,
            'compositions.php core-agent key set (minus optional-agent keys) must match '
                . 'aiInstallerAgentProfiles() minus the documented exclusions'
        );
    }

    /**
     * Design Fork F1 (docs/tickets/arch-todo-optional-agent-permission-composition-
     * 20260705T221434Z/plan.md, LOCKED): the 11 optional agents are composed for
     * `permission:` rendering only — they are never added to `aiInstallerAgentProfiles()`
     * (Option F2, widening tool-gateway visibility, was explicitly rejected). This is the
     * dedicated coverage assertion for that composition, kept separate from the 15-key
     * equality test above per the locked decision. Not every optional-agent key needs a
     * composition entry yet (this plan migrates them incrementally); this test only asserts
     * that every key WHICH DOES have a composition entry is drawn from the known 11-name
     * list — i.e. no optional-agent composition can silently exist under an unrecognized
     * name outside this canonical list.
     */
    public function testEveryComposedOptionalAgentKeyIsAKnownOptionalAgent(): void
    {
        require_once __DIR__ . '/../../tools/ai/install/script-registry.php';

        $coreProfileKeys = array_keys(aiInstallerAgentProfiles());
        $optionalAgentKeys = aiPermissionOptionalAgentKeys();

        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            if (in_array($agent, $coreProfileKeys, true)) {
                continue;
            }
            self::assertContains(
                $agent,
                $optionalAgentKeys,
                "composed agent '{$agent}' is neither a core aiInstallerAgentProfiles() key nor a "
                    . 'known optional-agent key (aiPermissionOptionalAgentKeys()) — add it to one of '
                    . 'the two canonical lists instead of leaving it unrecognized'
            );
        }
    }



    /**
     * N-8 (docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md): a
     * `['permission' => 'bash'/'edit', 'pattern' => ...]` rule appearing in TWO OR MORE
     * agents' `exceptions` must live in a named pack instead. This formalizes the manual
     * sweep performed during the permission-packs-handoff continuation session as a
     * permanent, automated failing-fixture test (was previously only a one-off `rg`/PHP
     * scan) — see that plan's "N-8 sweep" entries for the packs it produced.
     */
    public function testNoExceptionPatternDuplicatedAcrossTwoOrMoreAgents(): void
    {
        $seen = [];
        foreach (aiPermissionAgentCompositions() as $agent => $composition) {
            foreach (($composition['compose_spec']['exceptions'] ?? []) as $rule) {
                if (!in_array($rule['permission'], ['bash', 'edit'], true)) {
                    continue;
                }
                $key = $rule['permission'] . '|' . $rule['pattern'] . '|' . $rule['effect'];
                $seen[$key][] = $agent;
            }
        }

        $duplicates = [];
        foreach ($seen as $key => $agents) {
            $uniqueAgents = array_unique($agents);
            if (count($uniqueAgents) >= 2) {
                $duplicates[$key] = $uniqueAgents;
            }
        }

        self::assertSame(
            [],
            $duplicates,
            'These exception rules are duplicated across 2+ agents and must move into a '
                . 'named pack (packs.php) instead: ' . json_encode($duplicates, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Shell-composition operators (pipe, `&&`, `;`, command substitution) must never resolve
     * to `allow` for any composed agent, regardless of profile or exceptions — chaining is a
     * structural bypass of every other pattern-based rule in this system.
     */
    public function testShellCompositionOperatorsAreNeverAllowedForAnyComposedAgent(): void
    {
        $dangerousPatterns = [
            AI_BASH_PATTERN_PIPE,
            AI_BASH_PATTERN_AND_CHAIN,
            AI_BASH_PATTERN_SEMICOLON_CHAIN,
            AI_BASH_PATTERN_COMMAND_SUBSTITUTION,
        ];

        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $composed = aiPermissionCompose($agent);
            foreach ($dangerousPatterns as $pattern) {
                $key = aiPermissionModelKey('bash', $pattern);
                if (!isset($composed['model'][$key])) {
                    continue;
                }
                self::assertNotSame(
                    'allow',
                    $composed['model'][$key]['effect'],
                    "agent '{$agent}' must never allow shell-composition pattern '{$pattern}'"
                );
            }
        }
    }

    /**
     * Registry <-> generated-permission drift test (absorbs rethink-ticket phase-2 P2c —
     * docs/tickets/arch-todo-agent-permission-rethink-20260613T154104Z/plan-phase2-scripts-migration.md:202
     * — per docs/tickets/arch-todo-permission-layer-composition-20260705T004618Z/plan.md,
     * Slice 6 / N-6 reconciliation table). Every `scripts/ai/<name>.sh` reference in any
     * composed agent's bash patterns must correspond to a real, registered
     * `aiInstallerScriptRegistry()` entry's `installed_path` — catches a typo'd or
     * since-renamed script path that would otherwise silently render as an inert allow/ask
     * rule matching nothing.
     */
    public function testEveryComposedScriptReferenceIsRegistered(): void
    {
        require_once __DIR__ . '/../../tools/ai/install/script-registry.php';

        $knownPaths = [];
        foreach (aiInstallerScriptRegistry() as $entry) {
            $knownPaths[$entry['installed_path']] = true;
        }

        $unknown = [];
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $composed = aiPermissionCompose($agent);
            foreach ($composed['model'] as $entry) {
                if ($entry['permission'] !== 'bash') {
                    continue;
                }
                if (!preg_match('#scripts/ai/([A-Za-z0-9_-]+\.sh)#', $entry['pattern'], $m)) {
                    continue;
                }
                $path = 'scripts/ai/' . $m[1];
                if (!isset($knownPaths[$path])) {
                    $unknown[$path][] = "{$agent}:{$entry['pattern']}";
                }
            }
        }

        self::assertSame(
            [],
            $unknown,
            'Composed agent permissions reference scripts/ai/*.sh paths not present in '
                . 'aiInstallerScriptRegistry(): ' . json_encode($unknown, JSON_PRETTY_PRINT)
        );
    }

    // --- Slice 9: dangerous-gap validator checks (docs/tickets/
    // arch-todo-permission-layer-composition-20260613T154104Z/plan.md, "Slice 9 — Validator
    // Gaps"). Run against the composed model, not raw frontmatter, per that slice's own
    // requirement. All seven checks are enforced (verified clean against every currently
    // composed agent before landing, per the "advisory-first, never silently flip green to
    // red" discipline). Check 3 (raw read tools in write-profile agents) originally landed as
    // a ratchet test — it trips on every impl-profile agent by design via core:safe-read's
    // default grants — but a follow-up policy decision (continuation session) ask-gated
    // rg/bat/jq/yq/head/tail/sed-n via the new 'core.safe_read.raw_read_ask_gate' pack for
    // all 5 impl-profile agents (post-install keeps its already-stricter deny on
    // head/tail/sed-n instead of loosening to ask), closing the exposure. Check 3 is now a
    // hard zero-tolerance check like the other six.

    private const AI_PERMISSION_PINNED_STAR_ALLOW_AGENTS = ['super-implementer'];

    /**
     * Check 1: no composed agent's `edit '*'` may resolve to `allow` except the one
     * documented, pinned power-agent exception (super-implementer, N-3). Agents that grant
     * specific-path edit allows without any explicit `'*'` key (the standard denyTail-only
     * shape used by every other write-profile agent) are unaffected — this only catches a
     * truly wide-open wildcard grant.
     */
    public function testEditStarNeverResolvesToAllowExceptThePinnedException(): void
    {
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $model = aiPermissionCompose($agent)['model'];
            $effect = $model[aiPermissionModelKey('edit', '*')]['effect'] ?? null;
            if ($effect !== 'allow') {
                continue;
            }
            self::assertContains(
                $agent,
                self::AI_PERMISSION_PINNED_STAR_ALLOW_AGENTS,
                "agent '{$agent}' has edit '*': allow but is not a documented pinned exception"
            );
        }
    }

    /**
     * Failing-fixture proof for check 1: composes a spec that resolves `edit '*'` to
     * `allow` (via the same `edit-surfaces:unrestricted` surface super-implementer uses) but
     * simulates it belonging to an agent name NOT on the pinned exception list, then runs the
     * exact assertion `testEditStarNeverResolvesToAllowExceptThePinnedException()` uses. This
     * proves the check's own logic — not just the constant list — actually fails on a bad case.
     */
    public function testEditStarAllowCheckCatchesAnUndocumentedAgent(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'impl',
            'edit_surface' => 'unrestricted',
            'verify_tier' => 'verify-none',
        ]);
        self::assertSame('allow', $result['model'][aiPermissionModelKey('edit', '*')]['effect']);

        $hypotheticalAgent = 'undocumented-fixture-agent';
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        self::assertContains($hypotheticalAgent, self::AI_PERMISSION_PINNED_STAR_ALLOW_AGENTS);
    }

    /**
     * Check 2: no composed agent's `bash '*'` may resolve to `allow` except the one
     * documented, pinned power-agent exception (super-implementer, N-3) — the same
     * `shipped_star_baseline` pin `aiPermissionHardDenyWithStarBaseline()` already applies,
     * asserted here directly against the composed model rather than the compose_spec input.
     */
    public function testBashStarNeverResolvesToAllowExceptThePinnedException(): void
    {
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $model = aiPermissionCompose($agent)['model'];
            $effect = $model[aiPermissionModelKey('bash', '*')]['effect'] ?? null;
            if ($effect !== 'allow') {
                continue;
            }
            self::assertContains(
                $agent,
                self::AI_PERMISSION_PINNED_STAR_ALLOW_AGENTS,
                "agent '{$agent}' has bash '*': allow but is not a documented pinned exception"
            );
        }
    }

    /** @return list<string> */
    private static function aiPermissionDependencyManagerAllowPatterns(): array
    {
        return [
            'composer install*', 'composer update*', 'composer require*',
            'npm install*', 'npm ci*',
            'pnpm install*', 'pnpm add*',
            'yarn install*', 'yarn add*',
            'bun install*', 'bun add*',
        ];
    }

    /**
     * Check 4: dependency-manager mutation commands must never be `allow` for any composed
     * agent — `ask` (or `deny`) only.
     */
    public function testDependencyManagerMutationsAreNeverAllowed(): void
    {
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $model = aiPermissionCompose($agent)['model'];
            foreach (self::aiPermissionDependencyManagerAllowPatterns() as $pattern) {
                $effect = $model[aiPermissionModelKey('bash', $pattern)]['effect'] ?? null;
                self::assertNotSame(
                    'allow',
                    $effect,
                    "agent '{$agent}' must never allow dependency-manager mutation '{$pattern}'"
                );
            }
        }
    }

    /**
     * Failing-fixture proof for check 4: composes a spec that deliberately allows
     * `npm install*` (something no real agent does), then runs the exact assertion
     * `testDependencyManagerMutationsAreNeverAllowed()` uses against it. This proves the
     * check's own assertion — not just the fixture's shape — actually fails on a bad case.
     */
    public function testDependencyManagerCheckCatchesAnAllowedInstall(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'impl',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                aiPermissionBashAllow('npm install*'),
            ],
        ]);
        $effect = $result['model'][aiPermissionModelKey('bash', 'npm install*')]['effect'];

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        self::assertNotSame('allow', $effect, 'this fixture must trip the real check assertion');
    }

    /** @return list<string> */
    private static function aiPermissionMutatingVcsAllowPatterns(): array
    {
        return [
            'git add*', 'git commit*', 'git reset*', 'git restore *',
            'git checkout*', 'git switch*', 'git stash push*', 'git stash pop*',
            'git stash apply*', 'git stash drop*',
        ];
    }

    /**
     * Check 5: mutating VCS commands must never be `allow` for any composed agent — `ask`
     * (or `deny`) only. Read-only git (status/diff/log/show/ls-files/blame/branch/rev-parse)
     * is a separate, safe category and is not checked here.
     */
    public function testMutatingVcsCommandsAreNeverAllowed(): void
    {
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $model = aiPermissionCompose($agent)['model'];
            foreach (self::aiPermissionMutatingVcsAllowPatterns() as $pattern) {
                $effect = $model[aiPermissionModelKey('bash', $pattern)]['effect'] ?? null;
                self::assertNotSame(
                    'allow',
                    $effect,
                    "agent '{$agent}' must never allow mutating VCS command '{$pattern}'"
                );
            }
        }
    }

    /**
     * Check 6: `ai-edit.sh` / `ai-rollback.sh` must never be `allow` for any composed
     * agent — `ask` or `deny` only (these bypass the native path-scoped `edit:` permission).
     */
    public function testGuardedMutationScriptsAreNeverAllowed(): void
    {
        $patterns = [aiPatternAiScript('ai-edit.sh'), aiPatternAiScript('ai-rollback.sh')];
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $model = aiPermissionCompose($agent)['model'];
            foreach ($patterns as $pattern) {
                $effect = $model[aiPermissionModelKey('bash', $pattern)]['effect'] ?? null;
                self::assertNotSame(
                    'allow',
                    $effect,
                    "agent '{$agent}' must never allow guarded mutation script '{$pattern}'"
                );
            }
        }
    }

    /** @return list<string> */
    private static function aiPermissionContextPackagingAllowPatterns(): array
    {
        return [
            'repomix *', 'files-to-prompt *', 'code2prompt *',
            aiPatternAiScript('pack-context.sh'),
            aiPatternAiScript('run-repomix-context.sh'),
            aiPatternAiScript('repomix-context-tree.sh'),
            aiPatternAiScript('repomix-scc-router.sh'),
        ];
    }

    /**
     * Check 7: broad context-packaging/repomix commands must never be `allow` for any
     * composed agent — `ask` only (cost/context-window gated, not a security boundary, but
     * still never a silent `allow`).
     */
    public function testContextPackagingCommandsAreNeverAllowed(): void
    {
        foreach (array_keys(aiPermissionAgentCompositions()) as $agent) {
            $model = aiPermissionCompose($agent)['model'];
            foreach (self::aiPermissionContextPackagingAllowPatterns() as $pattern) {
                $effect = $model[aiPermissionModelKey('bash', $pattern)]['effect'] ?? null;
                self::assertNotSame(
                    'allow',
                    $effect,
                    "agent '{$agent}' must never allow context-packaging command '{$pattern}'"
                );
            }
        }
    }

    /**
     * Check 3 (now hard zero-tolerance — see the class-level note above): raw read tools
     * (`rg`, `bat`, `jq`, `yq`, `head`, `tail`, `sed -n`) must never be `allow` for any
     * impl-profile agent. Prefer the `preview-file.sh`/`rg-code.sh`/`fd-files.sh` wrappers;
     * `ask` (via `core.safe_read.raw_read_ask_gate`) is the approval fallback.
     */
    public function testRawReadToolsAreNeverAllowedInWriteProfileAgents(): void
    {
        $rawToolPatterns = ['rg *', 'bat *', 'jq *', 'yq *', 'head *', 'tail *', 'sed -n *'];
        foreach (aiPermissionAgentCompositions() as $agent => $composition) {
            if ($composition['compose_spec']['profile'] !== 'impl') {
                continue;
            }
            $model = aiPermissionCompose($agent)['model'];
            foreach ($rawToolPatterns as $pattern) {
                $effect = $model[aiPermissionModelKey('bash', $pattern)]['effect'] ?? null;
                self::assertNotSame(
                    'allow',
                    $effect,
                    "agent '{$agent}' must never allow raw read tool '{$pattern}' in a write profile"
                );
            }
        }
    }

    /**
     * Failing-fixture proof for check 3: composes an impl-profile spec that deliberately
     * allows `sed -n *`, then runs the exact assertion
     * `testRawReadToolsAreNeverAllowedInWriteProfileAgents()` uses against it.
     */
    public function testRawReadToolCheckCatchesAnAllowedRawTool(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'impl',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'exceptions' => [
                aiPermissionBashAllow('sed -n *'),
            ],
        ]);
        $effect = $result['model'][aiPermissionModelKey('bash', 'sed -n *')]['effect'];

        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
        self::assertNotSame('allow', $effect, 'this fixture must trip the real check assertion');
    }
}
