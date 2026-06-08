<?php

declare(strict_types=1);

/**
 * Schema integrity check.
 *
 * Every JSON Schema under schemas/ai/ is a shipped contract. A schema with no $id,
 * no title, or malformed JSON is an unwired stub that cannot serve as a contract and
 * tends to drift silently. This standalone, side-effect-free check fails when any
 * shipped schema is not a well-formed, addressable JSON Schema document.
 *
 * Each schema must:
 *   - parse as a JSON object,
 *   - declare "$schema" (the JSON Schema draft it follows),
 *   - declare "$id" (a stable identifier so consumers can reference it),
 *   - declare a non-empty "title".
 *
 * Usage:
 *   php tools/ai/validate-schemas.php [--root=PATH]
 * Exit: 0 when all schemas are well-formed, 1 on any violation, 2 on usage/IO error.
 */

function aiSchemasMain(array $argv): int
{
    $root = getcwd() ?: '.';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--root=')) {
            $root = substr($arg, 7);
        }
    }
    $root = rtrim(str_replace('\\', '/', (string) (realpath($root) ?: $root)), '/');

    $dir = $root . '/schemas/ai';
    if (!is_dir($dir)) {
        fwrite(STDERR, "ERROR: schema directory not found: schemas/ai\n");
        return 2;
    }

    $files = glob($dir . '/*.schema.json') ?: [];
    sort($files);
    if ($files === []) {
        fwrite(STDERR, "ERROR: no *.schema.json files found under schemas/ai\n");
        return 2;
    }

    $errors = [];
    $checked = 0;

    foreach ($files as $file) {
        $rel = 'schemas/ai/' . basename($file);
        $raw = @file_get_contents($file);
        if ($raw === false) {
            $errors[] = "{$rel}: unreadable";
            continue;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errors[] = "{$rel}: not valid JSON (" . json_last_error_msg() . ')';
            continue;
        }

        if (!isset($decoded['$schema']) || !is_string($decoded['$schema']) || $decoded['$schema'] === '') {
            $errors[] = "{$rel}: missing non-empty \"\$schema\"";
        }
        if (!isset($decoded['$id']) || !is_string($decoded['$id']) || $decoded['$id'] === '') {
            $errors[] = "{$rel}: missing non-empty \"\$id\" (a schema with no \$id cannot be referenced)";
        }
        if (!isset($decoded['title']) || !is_string($decoded['title']) || trim($decoded['title']) === '') {
            $errors[] = "{$rel}: missing non-empty \"title\"";
        }

        $checked++;
    }

    if ($errors === []) {
        fwrite(STDOUT, "OK: {$checked} schema(s) under schemas/ai are well-formed and addressable\n");
        return 0;
    }

    fwrite(STDERR, "ERROR: schema integrity violation(s):\n");
    foreach ($errors as $message) {
        fwrite(STDERR, ' - ' . $message . "\n");
    }

    return 1;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(aiSchemasMain($argv));
}
