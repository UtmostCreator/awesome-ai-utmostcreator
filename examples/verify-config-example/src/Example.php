<?php

declare(strict_types=1);

namespace VerifyConfigExample;

/**
 * Trivial, clean sample class — passes pint, phpstan (level 5), psalm,
 * and rector --dry-run (PHP 8.2 set) with default config.
 */
final class Example
{
    public function greet(string $name): string
    {
        return sprintf('Hello, %s!', $name);
    }
}
