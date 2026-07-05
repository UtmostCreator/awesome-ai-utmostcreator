<?php

declare(strict_types=1);

/**
 * Typed rule constructors for permission-layer composition specs (refactor pass,
 * docs/tickets/arch-todo-permission-packs-handoff-20260705-141148/plan.md — "review and
 * refactor" continuation). Replaces raw `['permission' => ..., 'pattern' => ...,
 * 'effect' => ...]` array literals scattered through compositions.php with named, typed
 * constructors, so every rule is built from one of a small number of reviewed shapes
 * instead of hand-typed each time (fewer typo-becomes-policy-bug opportunities).
 *
 * The optional `$why` parameter is carried on the rule array for human review and future
 * validators, but is never required and never rendered: aiPermissionApplyLayer()
 * (compose.php) only reads the `permission`/`pattern`/`effect` keys when building the
 * composed model, so adding `why` is fully backward compatible and changes zero rendered
 * output. Prefer `$why` for genuinely non-obvious exceptions; a self-explanatory pattern
 * (e.g. a straightforward tool allow) does not need one.
 *
 * @return array{permission:string,pattern:string,effect:string,why?:string}
 */
function aiPermissionRule(string $permission, string $pattern, string $effect, string $why = ''): array
{
    $rule = ['permission' => $permission, 'pattern' => $pattern, 'effect' => $effect];
    if ($why !== '') {
        $rule['why'] = $why;
    }

    return $rule;
}

/** @return array{permission:string,pattern:string,effect:string,why?:string} */
function aiPermissionBashAllow(string $pattern, string $why = ''): array
{
    return aiPermissionRule('bash', $pattern, 'allow', $why);
}

/** @return array{permission:string,pattern:string,effect:string,why?:string} */
function aiPermissionBashAsk(string $pattern, string $why = ''): array
{
    return aiPermissionRule('bash', $pattern, 'ask', $why);
}

/** @return array{permission:string,pattern:string,effect:string,why?:string} */
function aiPermissionBashDeny(string $pattern, string $why = ''): array
{
    return aiPermissionRule('bash', $pattern, 'deny', $why);
}

/** @return array{permission:string,pattern:string,effect:string,why?:string} */
function aiPermissionEditAllow(string $pattern, string $why = ''): array
{
    return aiPermissionRule('edit', $pattern, 'allow', $why);
}

/** @return array{permission:string,pattern:string,effect:string,why?:string} */
function aiPermissionEditDeny(string $pattern, string $why = ''): array
{
    return aiPermissionRule('edit', $pattern, 'deny', $why);
}
