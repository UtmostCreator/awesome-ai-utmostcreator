<?php

declare(strict_types=1);

// config-validator.php: assertion/predicate functions used by tools/ai/validate-ai-config.php.
// Extracted verbatim (behavior-preserving move; see
// docs/tickets/arch-todo-validate-ai-config-extraction-20260706-223128/plan.md, Phase 2).
// `validateOpenCodePermissions` calls `requirePermissionValue`, so both live in this one file.

/**
 * True when the target install manifest indicates a given pack was installed, either via the
 * recorded packs list or a managed file entry whose pack matches. Keeps the required-path
 * contract aligned with what the target's profile actually shipped.
 */
function aiValidateConfigManifestHasPack(array $manifest, string $pack): bool
{
    $packs = $manifest['packs'] ?? null;
    if (is_array($packs) && in_array($pack, array_map('strval', $packs), true)) {
        return true;
    }
    $files = $manifest['files'] ?? null;
    if (is_array($files)) {
        foreach ($files as $meta) {
            if (is_array($meta) && (string) ($meta['pack'] ?? '') === $pack) {
                return true;
            }
        }
    }
    return false;
}

function validateOpenCodePermissions(array $config, array &$errors): void
{
    $permission = $config['permission'] ?? null;

    if (!is_array($permission)) {
        $errors[] = 'opencode.jsonc missing permission object';
        return;
    }

    if (($permission['*'] ?? null) !== 'ask') {
        $errors[] = 'opencode.jsonc permission.* must be ask';
    }

    $bash = $permission['bash'] ?? null;
    if (!is_array($bash)) {
        $errors[] = 'opencode.jsonc permission.bash must be an object';
    } else {
        requirePermissionValue($bash, '*', ['ask', 'deny'], 'permission.bash.*', $errors);

        foreach (['git status*', 'git diff*', 'git log*'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['allow'], "permission.bash {$pattern}", $errors);
        }

        foreach ([
            'bash scripts/ai/ai-search.sh *',
            'AI_OUTPUT=json bash scripts/ai/ai-search.sh *',
            'env AI_OUTPUT=json bash scripts/ai/ai-search.sh *',
            'bash scripts/ai/preview-file.sh *',
            'AI_OUTPUT=json bash scripts/ai/preview-file.sh *',
            'env AI_OUTPUT=json bash scripts/ai/preview-file.sh *',
            'bash scripts/ai/query-usage.sh *',
            'bash scripts/ai/ai-diff-context.sh *',
            'bash scripts/ai/git-forensics.sh *',
            'bash scripts/ai/ai-doc-check.sh *',
            'bash scripts/ai/ai-test-select.sh *',
            'bash scripts/ai/ai-task.sh *',
            'bash scripts/ai/ai-structured.sh *',
        ] as $pattern) {
            requirePermissionValue($bash, $pattern, ['allow', 'ask'], "safe wrapper {$pattern}", $errors);
        }

        foreach ([
            'bash scripts/ai/ai-verify.sh *',
            'bash scripts/ai/pack-context.sh *',
            'bash scripts/ai/run-repomix-context.sh *',
            'bash scripts/ai/repomix-context-tree.sh *',
            'bash scripts/ai/repomix-scc-router.sh *',
            'bash scripts/ai/ai-edit.sh *',
            'bash scripts/ai/ai-rollback.sh *',
            'bash scripts/ai/install-mandatory-tools.sh *',
        ] as $pattern) {
            requirePermissionValue($bash, $pattern, ['ask'], "mutating or broad wrapper {$pattern}", $errors);
        }

        foreach (['grep *', 'rg *', 'find *', 'fd *', 'cat *', 'sed *', 'awk *'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['ask'], "raw search/read command {$pattern}", $errors);
        }

        foreach (['rm *', 'chown *', 'sudo *', 'git push*'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['deny'], "destructive command {$pattern}", $errors);
        }

        foreach (['mv *', 'cp *', 'chmod *', 'git reset*', 'git clean*'] as $pattern) {
            requirePermissionValue($bash, $pattern, ['ask'], "mutating command {$pattern}", $errors);
        }
    }

    $read = $permission['read'] ?? null;
    if (!is_array($read)) {
        $errors[] = 'opencode.jsonc permission.read must be an object';
    } else {
        requirePermissionValue($read, '*', ['allow'], 'permission.read.*', $errors);
        foreach (['.env', '.env.*', '*.pem', '*.key', '*.crt'] as $pattern) {
            requirePermissionValue($read, $pattern, ['deny'], "permission.read {$pattern}", $errors);
        }
    }

    $edit = $permission['edit'] ?? null;
    if (!is_array($edit)) {
        $errors[] = 'opencode.jsonc permission.edit must be an object';
    } else {
        requirePermissionValue($edit, '*', ['ask', 'deny'], 'permission.edit.*', $errors);
        foreach (['docs/ai/generated/**', '.opencode/**', '.github/agents/**', '.github/instructions/**', '.github/prompts/**', '.github/prompts-optional/**', '.github/skills/**', '.github/workflows/**', '.github/copilot-instructions.md', '.github/pull_request_template.md', '.env', '.env.*', '*.pem', '*.key', '*.crt'] as $pattern) {
            requirePermissionValue($edit, $pattern, ['deny'], "permission.edit {$pattern}", $errors);
        }
    }

    // Native read tools (grep/glob/list) are allowed broadly (P2a): they bypass the
    // bash matcher entirely and still honor the read secret-denies (.env/*.pem/...),
    // so allowing them removes approval friction for safe searches without widening
    // any execution surface. They must not be 'ask'/'deny' downgraded silently here.
    foreach (['grep', 'glob', 'list'] as $tool) {
        $toolPermission = $permission[$tool] ?? null;
        if (!is_array($toolPermission)) {
            $errors[] = "opencode.jsonc permission.{$tool} must be an object";
            continue;
        }

        requirePermissionValue($toolPermission, '*', ['allow'], "permission.{$tool}.*", $errors);
    }

    $skill = $permission['skill'] ?? null;
    if (!is_array($skill)) {
        $errors[] = 'opencode.jsonc permission.skill must be an object';
    } else {
        requirePermissionValue($skill, '*', ['ask'], 'permission.skill.*', $errors);
        foreach (['ai-search', 'ai-verification', 'ai-context'] as $skillName) {
            requirePermissionValue($skill, $skillName, ['allow'], "permission.skill {$skillName}", $errors);
        }
    }

    foreach (['external_directory', 'doom_loop'] as $guard) {
        if (($permission[$guard] ?? null) !== 'ask') {
            $errors[] = "opencode.jsonc permission.{$guard} must be ask";
        }
    }
}

function requirePermissionValue(array $permissions, string $pattern, array $allowedValues, string $label, array &$errors): void
{
    $value = $permissions[$pattern] ?? null;

    if (!is_string($value) || !in_array($value, $allowedValues, true)) {
        $errors[] = $label . ' must be ' . implode(' or ', $allowedValues);
    }
}
