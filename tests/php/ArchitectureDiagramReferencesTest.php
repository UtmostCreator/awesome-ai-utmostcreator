<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Forward file-existence check for docs/ai/architecture-diagrams.md
 * (plan-30 / plan-29 P2, AC-08).
 *
 * The diagram doc is hand-authored and names own-code modules under tools/ai/,
 * scripts/ai/, and packages/. This test asserts each such path referenced in a
 * Markdown code span (`...`) resolves to a real file, so the diagram cannot
 * silently name a deleted module.
 *
 * This is a FORWARD reference check (does a named path exist?), which
 * scripts/ai/check-file-refs.sh does NOT provide (it is a reverse orphan
 * finder). Target-only paths that plan-28 will introduce are exempted until it
 * lands; when plan-28 merges, remove the exemptions per the doc's
 * "Planned -> current flip" follow-up.
 */
final class ArchitectureDiagramReferencesTest extends TestCase
{
    private static string $repoRoot;

    /**
     * Paths named in the diagrams as "(planned)" target-state modules that are
     * confirmed absent today (plan-28). Exempted until plan-28 lands.
     *
     * Phase 2's `tools/ai/generate-claude-settings.php` landed and was removed from this
     * list; the Claude capability filter (Phase 3) remains unbuilt.
     */
    private const PLANNED_EXEMPT = [];

    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root from tests/php/');
        }
        self::$repoRoot = $root;
    }

    public function testDiagramDocExists(): void
    {
        self::assertFileExists(self::$repoRoot . '/docs/ai/architecture-diagrams.md');
    }

    public function testOwnCodePathsNamedInDiagramsResolve(): void
    {
        $doc = (string) file_get_contents(self::$repoRoot . '/docs/ai/architecture-diagrams.md');

        // Own-code file paths (with an extension) rooted at the project's own-code
        // roots, wherever they appear in the doc — Markdown backtick code spans OR
        // Mermaid node labels (["..."]). The own-code-root prefix keeps this from
        // matching prose or third-party paths.
        $pattern = '#(?:tools/ai|scripts/ai|packages)/[A-Za-z0-9_\-/.]+\.[A-Za-z0-9]+#';
        preg_match_all($pattern, $doc, $matches);

        $paths = array_unique($matches[0]);
        self::assertNotEmpty($paths, 'expected the diagram doc to reference own-code paths');

        $missing = [];
        foreach ($paths as $relative) {
            if (in_array($relative, self::PLANNED_EXEMPT, true)) {
                continue; // planned target module, not yet built (plan-28)
            }
            if (!is_file(self::$repoRoot . '/' . $relative)) {
                $missing[] = $relative;
            }
        }

        self::assertSame(
            [],
            $missing,
            "architecture-diagrams.md names own-code path(s) that do not resolve:\n" . implode("\n", $missing)
        );
    }

    public function testPlannedExemptPathsAreStillAbsent(): void
    {
        // Guards the "planned -> current flip" follow-up: if a planned module now
        // exists, the exemption (and the "(planned)" markers) must be removed.
        if (self::PLANNED_EXEMPT === []) {
            $this->markTestSkipped(
                'No planned-exempt own-code paths are currently tracked (Phase 2\'s '
                    . 'tools/ai/generate-claude-settings.php exemption was removed once it landed). '
                    . 'Re-populate this list if plan-28 Phase 3 introduces a new not-yet-built file path.'
            );
        }
        foreach (self::PLANNED_EXEMPT as $relative) {
            self::assertFileDoesNotExist(
                self::$repoRoot . '/' . $relative,
                "{$relative} now exists; remove its exemption here and flip the (planned) markers in architecture-diagrams.md (see plan-28 follow-up)."
            );
        }
    }
}
