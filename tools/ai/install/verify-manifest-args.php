<?php

declare(strict_types=1);

/**
 * Shared --manifest=<path> CLI arg parsing + existence check for the small standalone
 * verify-*.php scripts in this directory. Parses argv, prints a usage/not-found error and
 * exits(1) on failure (matching each script's original standalone behavior), or returns the
 * validated manifest file path on success. JSON decoding and manifest-shape validation stay in
 * each script, since those differ in exact checks and error messages.
 *
 * @param list<string> $argv
 */
function aiInstallerVerifyResolveManifestArg(array $argv, string $scriptName): string
{
    $manifestArg = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--manifest=')) {
            $manifestArg = substr($arg, 11);
        }
    }

    if ($manifestArg === null) {
        fwrite(STDERR, "Usage: php tools/ai/install/{$scriptName} --manifest=<path>\n");
        exit(1);
    }

    if (!is_file($manifestArg)) {
        fwrite(STDERR, "ERROR: manifest not found: {$manifestArg}\n");
        exit(1);
    }

    return $manifestArg;
}
