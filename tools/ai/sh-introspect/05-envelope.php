<?php

declare(strict_types=1);

/**
 * Canonical empty envelope. Every key in the binding contract is present.
 *
 * @return array<string,mixed>
 */
function shIntrospectEmptyEnvelope(string $file): array
{
    return [
        'schema' => SH_INTROSPECT_SCHEMA,
        'status' => 'ok',
        'tool' => SH_INTROSPECT_TOOL,
        'kind' => 'unknown',
        // `file` is kept as the absolute path string for backward compatibility.
        // `path` is the additive structured form {absolute, relative}.
        'file' => $file,
        'path' => shIntrospectPathObject($file),
        'functions' => [],
        // Documented envelope status vocabulary, parsed from the usage block
        // ("Status is one of: ...") when present; [] otherwise. Never invented.
        'status_values' => [],
        // Top-level human-help metadata parsed from the usage block: summary,
        // usage line, and the JSON-output env note. Keys are present only when
        // derivable from usage text.
        'help' => [],
        'modes' => [],
        'mode_contracts' => [],
        'params' => [],
        'positionals' => [],
        'case_labels' => [],
        'json_keys' => [],
        'json_key_candidates' => [],
        'json_paths' => [],
        'output_schemas' => [],
        'env_inputs' => [],
        'sources' => [],
        'dependencies' => [],
        'unknown_option_handlers' => [],
        'commands' => [],
        'side_effects' => [],
        'risk_summary' => [
            'max_risk' => 'unknown',
            'has_mutation' => false,
            'has_dynamic_execution' => false,
            'has_unresolved_source' => false,
        ],
        'risk_findings' => [],
        'examples' => [],
        'examples_by_mode' => [],
        'warnings' => [],
        'errors' => [],
        'meta' => [
            'parser' => 'php-static',
            'confidence' => 0,
            'target_executed' => false,
        ],
    ];
}

/**
 * Build the structured path object {absolute, relative}. The relative path is
 * resolved against the current working directory when the absolute path lives
 * underneath it; otherwise it falls back to the absolute path.
 *
 * @return array{absolute:string,relative:string}
 */
function shIntrospectPathObject(string $file): array
{
    $absolute = $file;
    $relative = $file;

    $cwd = getcwd();
    if ($cwd !== false && $file !== '') {
        $prefix = rtrim($cwd, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($file, $prefix)) {
            $relative = substr($file, strlen($prefix));
        }
    }

    return ['absolute' => $absolute, 'relative' => $relative];
}

/**
 * @param array<string,mixed> $envelope
 */
function shIntrospectEncode(array $envelope): string
{
    return json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
