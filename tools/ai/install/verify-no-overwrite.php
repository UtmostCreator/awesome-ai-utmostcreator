<?php

declare(strict_types=1);

$manifestArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--manifest=')) {
        $manifestArg = substr($arg, 11);
    }
}

if ($manifestArg === null) {
    fwrite(STDERR, "Usage: php tools/ai/install/verify-no-overwrite.php --manifest=<path>\n");
    exit(1);
}

if (!is_file($manifestArg)) {
    fwrite(STDERR, "ERROR: manifest not found: {$manifestArg}\n");
    exit(1);
}

$decoded = json_decode((string) file_get_contents($manifestArg), true);
if (!is_array($decoded) || !is_array($decoded['files'] ?? null)) {
    fwrite(STDERR, "ERROR: invalid manifest format\n");
    exit(1);
}

foreach ($decoded['files'] as $path => $meta) {
    if (!is_array($meta)) {
        fwrite(STDERR, "ERROR: invalid file metadata for {$path}\n");
        exit(1);
    }
    if (($meta['merge_strategy'] ?? '') === 'replace' && ($meta['managed'] ?? false) !== true) {
        fwrite(STDERR, "ERROR: unmanaged replace detected for {$path}\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK: no unmanaged overwrite markers detected in manifest\n");
exit(0);
