<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * P2 / invariant 5: marker syntax is frozen API.
 *
 * The managed-section markers (`# BEGIN ai-kit`, `<!-- BEGIN ai-kit -->`,
 * `<!-- BEGIN ai-kit:user -->` and their END pairs) must never change without a
 * versioned migration. These tests pin the exact byte values and the marker
 * schema version so any silent change fails CI.
 */
final class MarkerFreezeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = realpath(dirname(__DIR__, 2));
        if ($root === false) {
            throw new \RuntimeException('Could not resolve repo root');
        }
        require_once $root . '/tools/ai/install/markers.php';
    }

    public function testMarkerSchemaVersionIsPinned(): void
    {
        $this->assertSame(1, AI_MARKER_SCHEMA_VERSION, 'bumping the marker schema version requires a versioned migration');
    }

    public function testHtmlMarkersAreFrozen(): void
    {
        $markers = aiInstallerFrozenMarkers();
        $this->assertSame('<!-- BEGIN ai-kit -->', $markers['html']['begin']);
        $this->assertSame('<!-- END ai-kit -->', $markers['html']['end']);
    }

    public function testHtmlUserMarkersAreFrozen(): void
    {
        $markers = aiInstallerFrozenMarkers();
        $this->assertSame('<!-- BEGIN ai-kit:user -->', $markers['html_user']['begin']);
        $this->assertSame('<!-- END ai-kit:user -->', $markers['html_user']['end']);
    }

    public function testHashMarkersAreFrozen(): void
    {
        $markers = aiInstallerFrozenMarkers();
        $this->assertSame('# BEGIN ai-kit', $markers['hash']['begin']);
        $this->assertSame('# END ai-kit', $markers['hash']['end']);
    }

    public function testConstantsMatchTheFrozenSet(): void
    {
        $markers = aiInstallerFrozenMarkers();
        $this->assertSame(AI_MARKER_HTML_BEGIN, $markers['html']['begin']);
        $this->assertSame(AI_MARKER_HTML_END, $markers['html']['end']);
        $this->assertSame(AI_MARKER_HTML_USER_BEGIN, $markers['html_user']['begin']);
        $this->assertSame(AI_MARKER_HTML_USER_END, $markers['html_user']['end']);
        $this->assertSame(AI_MARKER_HASH_BEGIN, $markers['hash']['begin']);
        $this->assertSame(AI_MARKER_HASH_END, $markers['hash']['end']);
    }
}
