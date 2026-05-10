<?php

declare(strict_types=1);

$manifestArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--manifest=')) {
        $manifestArg = substr($arg, 11);
    }
}

if ($manifestArg === null) {
    fwrite(STDERR, "Usage: php tools/ai/install/verify-manifest.php --manifest=<path>\n");
    exit(1);
}

if (!is_file($manifestArg)) {
    fwrite(STDERR, "ERROR: manifest not found: {$manifestArg}\n");
    exit(1);
}

$decoded = json_decode((string) file_get_contents($manifestArg), true);
if (!is_array($decoded)) {
    fwrite(STDERR, "ERROR: manifest is not valid JSON\n");
    exit(1);
}

foreach (['schema_version', 'installer_version', 'profile', 'package', 'files'] as $required) {
    if (!array_key_exists($required, $decoded)) {
        fwrite(STDERR, "ERROR: manifest missing required field {$required}\n");
        exit(1);
    }
}

if (!is_array($decoded['package']) || !isset($decoded['package']['name'])) {
    fwrite(STDERR, "ERROR: manifest package section is incomplete\n");
    exit(1);
}

if (!is_array($decoded['files'])) {
    fwrite(STDERR, "ERROR: manifest files section is invalid\n");
    exit(1);
}

fwrite(STDOUT, "OK: manifest structure is valid\n");
exit(0);
