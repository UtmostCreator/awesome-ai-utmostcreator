<?php

declare(strict_types=1);

require_once __DIR__ . '/ai_catalog_lib.php';

$checkOnly = in_array('--check', $argv, true);
$root = aiRepoRoot();
$catalog = aiCollectCatalog($root);
$json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$messages = [];
$ok = true;

$ok = aiCompareOrWrite($root, aiResolvePackageBase($root) . 'catalog.json', $json, $checkOnly, $messages) && $ok;
$ok = aiCompareOrWrite($root, 'docs/ai/catalog.md', aiRenderRootCatalogMarkdown($catalog), $checkOnly, $messages) && $ok;
$ok = aiCompareOrWrite($root, aiResolvePackageDocsBase($root) . 'BROWSE.md', aiRenderBrowseMarkdown($catalog), $checkOnly, $messages) && $ok;
$ok = aiCompareOrWrite($root, 'llms.txt', aiRenderLlms($catalog), $checkOnly, $messages) && $ok;

foreach ($messages as $message) {
    $stream = str_starts_with($message, 'ERROR:') ? STDERR : STDOUT;
    fwrite($stream, $message . "\n");
}

exit($ok ? 0 : 1);
