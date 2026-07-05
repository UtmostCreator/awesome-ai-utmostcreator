<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../tools/ai/install/permission-layers/compose.php';

/**
 * Slice 3 of docs/tickets/arch-todo-dynamic-stack-permission-selection-20260705T011906Z/plan.md:
 * proves stack_overlays integrate with aiPermissionComposeFromSpec() without
 * weakening the immutable hard-deny floor, and that an unknown stack/overlay
 * reference fails loudly instead of silently granting nothing.
 */
final class StackPermissionComposeTest extends TestCase
{
    public function testPhpStackOverlayGrantsPhpLanguageOverlayCommands(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'stack_overlays' => ['php'],
        ]);

        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'php -l *')]['effect']);
        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'composer validate*')]['effect']);
    }

    public function testNoStackSelectedKeepsMinimalBaseline(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
        ]);

        self::assertArrayNotHasKey(aiPermissionModelKey('bash', 'php -l *'), $result['model']);
        self::assertArrayNotHasKey(aiPermissionModelKey('bash', 'npm test*'), $result['model']);
    }

    public function testMultipleStacksComposeTogether(): void
    {
        $result = aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'stack_overlays' => ['php', 'js-ts', 'markdown'],
        ]);

        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'php -l *')]['effect']);
        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'npm test*')]['effect']);
        self::assertSame('allow', $result['model'][aiPermissionModelKey('bash', 'markdownlint-cli2 *')]['effect']);
    }

    public function testUnknownStackIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'stack_overlays' => ['not-a-real-stack'],
        ]);
    }

    public function testStackOverlayCannotWeakenHardDenyFloor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('weakens immutable floor');

        // A stack descriptor could reference an overlay that (incorrectly) tries to
        // allow a hard-deny pattern; simulate via a fixture registry pointing at a
        // custom overlay layer added directly to the model as an exception instead,
        // proving the floor check applies regardless of which layer attempts it.
        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'stack_overlays' => ['php'],
            'exceptions' => [
                ['permission' => 'bash', 'pattern' => 'rm -rf *', 'effect' => 'allow'],
            ],
        ]);
    }

    public function testStackReferencingUnknownLanguageOverlayFailsLoudly(): void
    {
        $fixtureRegistry = [
            'broken-stack' => [
                'id' => 'broken-stack',
                'permission_overlays' => ['language_overlays' => ['does-not-exist']],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("references unknown language overlay 'does-not-exist'");

        aiPermissionComposeFromSpec([
            'profile' => 'readonly',
            'edit_surface' => 'none',
            'verify_tier' => 'verify-none',
            'stack_overlays' => ['broken-stack'],
            'stack_registry' => $fixtureRegistry,
        ]);
    }
}
