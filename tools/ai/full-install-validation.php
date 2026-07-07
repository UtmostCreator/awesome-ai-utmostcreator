<?php

declare(strict_types=1);

require_once __DIR__ . '/validation/config-loader.php';
require_once __DIR__ . '/validation/watchdog-runner.php';
require_once __DIR__ . '/validation/repo-inventory.php';
require_once __DIR__ . '/validation/surface-lint.php';
require_once __DIR__ . '/validation/full-install-suite.php';

exit(aiRunFullInstallValidation($argv));
