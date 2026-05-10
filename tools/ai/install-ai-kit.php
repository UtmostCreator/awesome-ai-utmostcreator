<?php

declare(strict_types=1);

require_once __DIR__ . '/install/core.php';

try {
    exit(aiInstallerRun($argv));
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
