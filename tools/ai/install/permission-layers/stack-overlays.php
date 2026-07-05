<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';
require_once __DIR__ . '/language-overlays.php';
require_once __DIR__ . '/script-tiers.php'; // for aiPermissionUniqueEntries()
require_once __DIR__ . '/../stack-registry.php';

/**
 * Turn a selected stack id into layer-shaped permission entries by resolving its
 * `permission_overlays.language_overlays` reference against the existing language
 * overlay layers (tools/ai/install/permission-layers/language-overlays.php).
 *
 * Stack descriptors never define permission patterns directly — they only point at
 * an existing overlay name — so a stack can never grant anything outside the
 * reviewed language-overlay set (docs/tickets/arch-todo-dynamic-stack-permission-selection-*,
 * AC-8: "Permission composition accepts stack overlays without weakening hard-deny floor").
 *
 * @param list<string> $stackIds
 * @param array<string,array<string,mixed>>|null $registry Optional pre-loaded registry
 *        (tests may pass a fixture registry); defaults to the shipped + project-local
 *        registry for $targetRoot.
 * @return list<array{permission:string,pattern:string,effect:string}>
 */
function aiPermissionStackOverlayEntries(array $stackIds, ?string $targetRoot = null, ?array $registry = null): array
{
    $registry ??= aiStackLoadRegistry($targetRoot);
    $overlays = aiPermissionLanguageOverlays();

    $entries = [];
    foreach ($stackIds as $stackId) {
        if (!isset($registry[$stackId])) {
            throw new InvalidArgumentException(sprintf('Unknown stack id in permission overlay: %s', $stackId));
        }

        $overlayNames = $registry[$stackId]['permission_overlays']['language_overlays'] ?? [];
        foreach ($overlayNames as $overlayName) {
            if (!array_key_exists($overlayName, $overlays)) {
                throw new InvalidArgumentException(sprintf(
                    "Stack '%s' references unknown language overlay '%s'.",
                    $stackId,
                    $overlayName
                ));
            }
            foreach ($overlays[$overlayName] as $entry) {
                $entries[] = $entry;
            }
        }
    }

    return aiPermissionUniqueEntries($entries);
}
