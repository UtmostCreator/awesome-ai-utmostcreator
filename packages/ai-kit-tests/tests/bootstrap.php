<?php

declare(strict_types=1);

// Autoloader for this package (paratest/phpunit live in packages/ai-kit-tests/vendor/).
require_once __DIR__ . '/../vendor/autoload.php';
// Shared kit library lives in the repo root tools/ai/ tree (three levels up from tests/).
require_once dirname(__DIR__, 3) . '/tools/ai/ai_catalog_lib.php';
