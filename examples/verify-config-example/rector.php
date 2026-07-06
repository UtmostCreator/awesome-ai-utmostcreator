<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// Minimal, safe rule set for the copy-paste example. Only PHP-version-upgrade
// rules are enabled (non-mutating in check mode: run with --dry-run, never
// `rector process` (apply), per docs/tickets/arch-todo-safe-language-verify-scripts-20260706-003959 §5).
return RectorConfig::configure()
    ->withPaths([__DIR__.'/src'])
    ->withPhpSets(php82: true);
