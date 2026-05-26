<?php

declare(strict_types=1);

require_once __DIR__ . '/registry.php';

function aiAdvisorSecretScan(string $root): array
{
    $tracked = [];
    exec('git -C ' . escapeshellarg($root) . ' ls-files', $tracked);

    $denyNames = [
        '.env', 'id_rsa', 'id_ed25519',
    ];
    $denySuffix = ['.pem', '.key'];
    $patterns = [
        ['pattern' => '/AKIA[0-9A-Z]{16}/', 'blocking' => true],
        ['pattern' => '/ghp_[A-Za-z0-9_]{30,}/', 'blocking' => true],
        ['pattern' => '/sk-[A-Za-z0-9]{20,}/', 'blocking' => true],
        ['pattern' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/', 'blocking' => true],
        ['pattern' => '/(password|secret|token)\s*=\s*["\']?[A-Za-z0-9_\-\/+=]{12,}/i', 'blocking' => false],
    ];

    $findings = [];
    foreach ($tracked as $rel) {
        $rel = (string) $rel;
        $base = basename($rel);
        if (in_array($base, $denyNames, true) || str_starts_with($base, '.env.')) {
            $findings[] = ['file' => $rel, 'reason' => 'sensitive filename', 'blocking' => true];
            continue;
        }
        foreach ($denySuffix as $suffix) {
            if (str_ends_with(strtolower($base), $suffix)) {
                $findings[] = ['file' => $rel, 'reason' => 'sensitive extension', 'blocking' => true];
                continue 2;
            }
        }

        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($abs)) {
            continue;
        }
        if (filesize($abs) > 1024 * 1024) {
            continue;
        }
        $content = (string) file_get_contents($abs);
        foreach ($patterns as $rule) {
            $pattern = (string) ($rule['pattern'] ?? '');
            if ($pattern === '') {
                continue;
            }
            if (preg_match($pattern, $content) === 1) {
                $findings[] = ['file' => $rel, 'reason' => 'secret-like pattern: ' . $pattern, 'blocking' => (bool) ($rule['blocking'] ?? true)];
                break;
            }
        }
    }

    $dir = aiAdvisorGeneratedDir($root);
    $blocked = false;
    foreach ($findings as $finding) {
        if (!empty($finding['blocking'])) {
            $blocked = true;
            break;
        }
    }
    $out = ['blocked' => $blocked, 'findings' => $findings, 'count' => count($findings)];
    aiAdvisorWriteJson($dir . DIRECTORY_SEPARATOR . 'advisor-secret-findings.json', $out);
    return $out;
}
