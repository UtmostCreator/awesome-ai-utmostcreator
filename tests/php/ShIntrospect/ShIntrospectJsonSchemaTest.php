<?php

declare(strict_types=1);

namespace Tests\ShIntrospect;

use Tests\Support\ShIntrospectTestCase;

/**
 * JSON key confidence, json_paths, and output_schemas extraction.
 */
class ShIntrospectJsonSchemaTest extends ShIntrospectTestCase
{
    public function testJsonKeysAreConfirmedHighConfidenceOnly(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['json_keys']);
        foreach ($env['json_keys'] as $key) {
            $this->assertGreaterThanOrEqual(
                80,
                (int) $key['confidence'],
                "json_keys must only carry confirmed (>=80) keys; '{$key['name']}' is lower"
            );
        }
    }

    public function testLowConfidenceKeysMoveToCandidates(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['json_key_candidates']);
        // The real script has plenty of best-effort code-scan keys.
        $this->assertNotEmpty($env['json_key_candidates'], 'expected some json_key_candidates');
        foreach ($env['json_key_candidates'] as $key) {
            $this->assertLessThan(
                80,
                (int) $key['confidence'],
                "json_key_candidates must only carry low-confidence keys; '{$key['name']}' is high"
            );
        }
        // No name should appear in both lists.
        $confirmed = array_map(static fn(array $k): string => (string) $k['name'], $env['json_keys']);
        $candidates = array_map(static fn(array $k): string => (string) $k['name'], $env['json_key_candidates']);
        $this->assertSame([], array_intersect($confirmed, $candidates), 'a key must not be both confirmed and candidate');
    }

    public function testJsonPathsAreJsonPathFormattedWithConfidence(): void
    {
        $env = $this->targetEnvelope();
        $this->assertNotEmpty($env['json_paths'], 'ai-search must expose nested json_paths');
        foreach ($env['json_paths'] as $p) {
            $this->assertArrayHasKey('path', $p);
            $this->assertArrayHasKey('confidence', $p);
            $this->assertStringStartsWith('$.', (string) $p['path'], 'paths must be JSONPath-formatted');
            $this->assertIsInt($p['confidence']);
        }
    }

    public function testJsonPathsIncludeTopLevelNestedAndArrayElementPaths(): void
    {
        $env = $this->targetEnvelope();
        $paths = array_map(static fn(array $p): string => (string) $p['path'], $env['json_paths']);
        // Top-level.
        $this->assertContains('$.schema', $paths);
        $this->assertContains('$.status', $paths);
        // Nested (jq-object).
        $this->assertContains('$.limits.max_results', $paths);
        $this->assertContains('$.meta.truncated', $paths);
        // Array-element.
        $this->assertContains('$.results[].path', $paths);
        // No prose `$.of.*` leakage and every parent is a real key.
        foreach ($paths as $p) {
            $this->assertStringStartsNotWith('$.of.', $p, "prose path '{$p}' must not be emitted");
        }
    }

    public function testJsonPathsConstrainParentsToConfirmedKeys(): void
    {
        $env = $this->targetEnvelope();
        $topKeys = array_map(static fn(array $k): string => (string) $k['name'], $env['json_keys']);
        foreach ($env['json_paths'] as $p) {
            // Extract the parent segment of `$.parent...`.
            if (preg_match('/^\$\.([A-Za-z_][A-Za-z0-9_]*)/', (string) $p['path'], $m)) {
                $this->assertContains(
                    $m[1],
                    $topKeys,
                    "json_path parent '{$m[1]}' must be a confirmed top-level key"
                );
            }
        }
    }

    public function testOutputSchemasIsArrayAndDescribesEnvelopeWhenPresent(): void
    {
        $env = $this->targetEnvelope();
        $this->assertIsArray($env['output_schemas']);
        $this->assertNotEmpty($env['output_schemas'], 'ai-search has a documented envelope schema');
        $schema = $env['output_schemas'][0];
        $this->assertSame('envelope', $schema['name']);
        $this->assertIsArray($schema['keys']);
        $this->assertContains('schema', $schema['keys']);
        $this->assertContains('status', $schema['keys']);
    }

    public function testOutputSchemasEmptyWhenNoEnvelopeDocumented(): void
    {
        $env = $this->fixtureEnvelope(<<<'SH'
#!/usr/bin/env bash
greet() { echo "hi"; }
SH);
        $this->assertSame([], $env['output_schemas'], 'no documented envelope => empty output_schemas');
    }

    public function testOutputSchemasIncludeModeSpecificResultSchemas(): void
    {
        $env = $this->targetEnvelope();
        $byName = [];
        foreach ($env['output_schemas'] as $s) {
            $byName[(string) $s['name']] = $s;
        }

        // file-list-result covers the file-list modes and omits matches/limits.
        $this->assertArrayHasKey('file-list-result', $byName);
        $this->assertContains('changed-files', $byName['file-list-result']['modes']);
        $this->assertContains('staged-files', $byName['file-list-result']['modes']);
        $this->assertContains('results', $byName['file-list-result']['keys']);
        $this->assertNotContains('matches', $byName['file-list-result']['keys']);

        // content-search-result covers text-like modes and carries matches+meta.
        $this->assertArrayHasKey('content-search-result', $byName);
        $this->assertContains('text', $byName['content-search-result']['modes']);
        $this->assertContains('matches', $byName['content-search-result']['keys']);
        $this->assertContains('meta', $byName['content-search-result']['keys']);
    }

    public function testModeSpecificOutputSchemasArePresent(): void
    {
        $env = $this->targetEnvelope();
        $byName = [];
        foreach ($env['output_schemas'] as $s) {
            $byName[(string) $s['name']] = $s;
        }
        $expected = [
            'diff-result' => ['path', 'marker', 'new_line', 'text', 'scope'],
            'history-result' => ['commit', 'author', 'date', 'message', 'path'],
            'symbols-result' => ['kind', 'name', 'path', 'start', 'end', 'language'],
            'todo-result' => ['tag', 'line', 'text'],
            'unsafe-patterns-result' => ['rule', 'severity'],
            'doctor-result' => ['available', 'missing', 'warnings', 'root', 'git_available'],
        ];
        foreach ($expected as $schema => $fields) {
            $this->assertArrayHasKey($schema, $byName, "expected mode-specific schema '{$schema}'");
            $this->assertSame('mode-doc', $byName[$schema]['source']);
            foreach ($fields as $field) {
                $this->assertContains(
                    $field,
                    $byName[$schema]['element_fields'],
                    "schema '{$schema}' must document element field '{$field}'"
                );
            }
        }
    }
}
