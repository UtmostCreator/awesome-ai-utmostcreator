<?php

declare(strict_types=1);

$resultArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--result=')) {
        $resultArg = substr($arg, 9);
    }
}

if ($resultArg === null) {
    fwrite(STDERR, "Usage: php tools/ai/install/verify-install-result.php --result=<path>\n");
    exit(1);
}

if (!is_file($resultArg)) {
    fwrite(STDERR, "ERROR: install result not found: {$resultArg}\n");
    exit(1);
}

$decoded = json_decode((string) file_get_contents($resultArg), true);
if (!is_array($decoded)) {
    fwrite(STDERR, "ERROR: install result is not valid JSON\n");
    exit(1);
}

foreach (['status', 'profile', 'runtime', 'target', 'source', 'selected_packs'] as $required) {
    if (!array_key_exists($required, $decoded)) {
        fwrite(STDERR, "ERROR: install result missing {$required}\n");
        exit(1);
    }
}

if (($decoded['status'] ?? '') !== 'ok') {
    fwrite(STDERR, "ERROR: install result status is not ok\n");
    exit(1);
}

if (!is_array($decoded['selected_packs'])) {
    fwrite(STDERR, "ERROR: selected_packs must be an array\n");
    exit(1);
}

fwrite(STDOUT, "OK: install result structure is valid\n");
exit(0);
