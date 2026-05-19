<?php

declare(strict_types=1);

/**
 * Rank PHPUnit tests by wall-clock duration from a JUnit XML report.
 *
 * Usage:
 *   composer test:profile         # produces docs/ai/generated/phpunit-junit.xml
 *   composer test:slow [N]        # prints top N (default 20) slowest tests
 *
 * Output: TSV table with milliseconds, class, and test name; sorted descending.
 */

$argvCopy = $_SERVER['argv'] ?? [];
$junitPath = __DIR__ . '/../../../docs/ai/generated/phpunit-junit.xml';
$limit = 20;

foreach (array_slice($argvCopy, 1) as $arg) {
    if (ctype_digit($arg)) {
        $limit = (int) $arg;
    } elseif (is_file($arg)) {
        $junitPath = $arg;
    }
}

if (!is_file($junitPath)) {
    fwrite(STDERR, "ERROR: junit report not found at: {$junitPath}\n");
    fwrite(STDERR, "       Run `composer test:profile` first.\n");
    exit(1);
}

$xml = @simplexml_load_file($junitPath);
if ($xml === false) {
    fwrite(STDERR, "ERROR: failed to parse junit XML at: {$junitPath}\n");
    exit(1);
}

$rows = [];
foreach ($xml->xpath('//testcase') as $case) {
    $time = (float) ($case['time'] ?? 0.0);
    $rows[] = [
        'ms' => (int) round($time * 1000),
        'class' => (string) ($case['classname'] ?? ''),
        'name' => (string) ($case['name'] ?? ''),
    ];
}

usort($rows, static fn ($a, $b) => $b['ms'] <=> $a['ms']);
$rows = array_slice($rows, 0, $limit);

printf("%-10s\t%-60s\t%s\n", 'ms', 'class', 'test');
printf("%s\n", str_repeat('-', 100));
foreach ($rows as $r) {
    printf("%-10d\t%-60s\t%s\n", $r['ms'], $r['class'], $r['name']);
}

$totalMs = array_sum(array_column($rows, 'ms'));
printf("\nShown total: %d ms across top %d tests.\n", $totalMs, count($rows));
