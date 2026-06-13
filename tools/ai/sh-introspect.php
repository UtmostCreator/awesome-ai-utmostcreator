<?php

declare(strict_types=1);

/**
 * sh-introspect.php — universal shell-script introspector (static parser).
 *
 * Statically parses a Bash script and reports its callable surface:
 * functions, modes, per-mode call contracts, flags/params, positionals,
 * internal case labels, emitted JSON keys, environment inputs, sourced files,
 * dependencies, unknown-option handlers, side effects, a coarse risk summary,
 * and usage examples. The target script is NEVER executed; this is a text-only
 * parse (`meta.target_executed` is always false).
 *
 * Usage:
 *   php tools/ai/sh-introspect.php FILE.sh
 *       Human-readable grouped text (default).
 *
 *   AI_OUTPUT=json php tools/ai/sh-introspect.php FILE.sh
 *   php tools/ai/sh-introspect.php --format=json FILE.sh
 *       JSON envelope (schema ai.sh-introspect/v1).
 *
 *   php tools/ai/sh-introspect.php --format=help FILE.sh
 *       Compact, human-friendly contract summary (modes + param:type lines)
 *       suitable for embedding in another script's --help.
 *
 *   php tools/ai/sh-introspect.php --help | -h
 *       Usage text, exit 0.
 *
 * Exit codes:
 *   0 - parsed successfully (status=ok)
 *   2 - path/validation error (status=error)
 *
 * Style mirrors other standalone tool scripts in tools/ai/ (top-level
 * functions, no classes; envelope rendered with json_encode + the
 * JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES flags).
 *
 * This entrypoint is a loader only. Implementation lives in numbered,
 * load-ordered files under tools/ai/sh-introspect/. The numeric prefixes
 * make the require_once pipeline self-documenting without classes/autoload.
 */

require_once __DIR__ . '/sh-introspect/00-constants.php';
require_once __DIR__ . '/sh-introspect/05-envelope.php';
require_once __DIR__ . '/sh-introspect/15-index.php';
require_once __DIR__ . '/sh-introspect/25-shell-scanner.php';
require_once __DIR__ . '/sh-introspect/30-modes.php';
require_once __DIR__ . '/sh-introspect/35-mode-contracts.php';
require_once __DIR__ . '/sh-introspect/40-params.php';
require_once __DIR__ . '/sh-introspect/45-usage-docs.php';
require_once __DIR__ . '/sh-introspect/50-json-schema.php';
require_once __DIR__ . '/sh-introspect/55-env-sources-examples.php';
require_once __DIR__ . '/sh-introspect/60-dependencies.php';
require_once __DIR__ . '/sh-introspect/65-commands-risk.php';
require_once __DIR__ . '/sh-introspect/70-render-text.php';
require_once __DIR__ . '/sh-introspect/75-render-help.php';
require_once __DIR__ . '/sh-introspect/22-source-inline.php';
require_once __DIR__ . '/sh-introspect/20-parse.php';
require_once __DIR__ . '/sh-introspect/10-cli.php';

exit(shIntrospectMain($argv));
