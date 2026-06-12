<?php
declare(strict_types=1);

require_once __DIR__ . '/sh-introspect/constants.php';
require_once __DIR__ . '/sh-introspect/envelope.php';
require_once __DIR__ . '/sh-introspect/shell-scanner.php';
require_once __DIR__ . '/sh-introspect/modes.php';
require_once __DIR__ . '/sh-introspect/mode-contracts.php';
require_once __DIR__ . '/sh-introspect/params.php';
require_once __DIR__ . '/sh-introspect/usage-docs.php';
require_once __DIR__ . '/sh-introspect/json-schema.php';
require_once __DIR__ . '/sh-introspect/sources-env-examples.php';
require_once __DIR__ . '/sh-introspect/dependencies.php';
require_once __DIR__ . '/sh-introspect/commands-risk.php';
require_once __DIR__ . '/sh-introspect/render-text.php';
require_once __DIR__ . '/sh-introspect/render-help.php';
require_once __DIR__ . '/sh-introspect/parse.php';
require_once __DIR__ . '/sh-introspect/index.php';
require_once __DIR__ . '/sh-introspect/cli.php';

exit(shIntrospectMain($argv));
