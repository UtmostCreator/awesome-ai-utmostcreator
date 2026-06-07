<?php

declare(strict_types=1);

/**
 * Frozen managed-section markers (invariant 5).
 *
 * These byte sequences are FROZEN API: install/upgrade rely on them to locate the
 * kit-managed block (and the user-owned sub-block) inside otherwise user-owned files.
 * Changing any marker would orphan every previously installed marker on upgrade.
 *
 * Never edit these values. To evolve the marker syntax, bump AI_MARKER_SCHEMA_VERSION
 * and ship a versioned migration that rewrites existing markers in place. The freeze
 * test (tests/php/MarkerFreezeTest.php) pins the exact values and the schema version.
 */

const AI_MARKER_SCHEMA_VERSION = 1;

const AI_MARKER_HTML_BEGIN = '<!-- BEGIN ai-kit -->';
const AI_MARKER_HTML_END = '<!-- END ai-kit -->';

const AI_MARKER_HTML_USER_BEGIN = '<!-- BEGIN ai-kit:user -->';
const AI_MARKER_HTML_USER_END = '<!-- END ai-kit:user -->';

const AI_MARKER_HASH_BEGIN = '# BEGIN ai-kit';
const AI_MARKER_HASH_END = '# END ai-kit';

/**
 * Return the complete frozen marker set.
 *
 * @return array{
 *     html: array{begin:string,end:string},
 *     html_user: array{begin:string,end:string},
 *     hash: array{begin:string,end:string}
 * }
 */
function aiInstallerFrozenMarkers(): array
{
    return [
        'html' => ['begin' => AI_MARKER_HTML_BEGIN, 'end' => AI_MARKER_HTML_END],
        'html_user' => ['begin' => AI_MARKER_HTML_USER_BEGIN, 'end' => AI_MARKER_HTML_USER_END],
        'hash' => ['begin' => AI_MARKER_HASH_BEGIN, 'end' => AI_MARKER_HASH_END],
    ];
}
