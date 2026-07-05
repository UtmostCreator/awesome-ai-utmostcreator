<?php

declare(strict_types=1);

// Split into focused modules — this file is now a facade.
require_once __DIR__ . '/install_paths.php';
require_once __DIR__ . '/install_preflight.php';
require_once __DIR__ . '/install_workflow.php';
require_once __DIR__ . '/install_extras.php';
require_once __DIR__ . '/project_values_sync.php';
