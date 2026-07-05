<?php

declare(strict_types=1);

/**
 * Render-metadata builders for the repeated `{extra_scalars, quote}` shape compositions.php
 * passes to aiPermissionRenderOpenCodeBlock() (render-adapters.php). This is generator-only
 * presentation metadata, not part of the composed permission model — see compositions.php's
 * own doc block for that distinction.
 *
 * @return array{extra_scalars:array<string,string>,quote:string}
 */
function aiPermissionRenderTaskAsk(string $quote = 'single'): array
{
    return ['extra_scalars' => ['task' => 'ask'], 'quote' => $quote];
}

/** @return array{extra_scalars:array<string,string>,quote:string} */
function aiPermissionRenderTaskAllow(string $quote = 'single'): array
{
    return ['extra_scalars' => ['task' => 'allow'], 'quote' => $quote];
}

/** @return array{extra_scalars:array<string,string>,quote:string} */
function aiPermissionRenderNoTask(string $quote = 'single'): array
{
    return ['extra_scalars' => [], 'quote' => $quote];
}

/**
 * script-runner's unique extra-scalar set (webfetch/websearch/external_directory/task/ask)
 * — kept as a dedicated one-off builder rather than generalized, since no other agent
 * shares this combination.
 *
 * @return array{extra_scalars:array<string,string>,quote:string}
 */
function aiPermissionRenderScriptRunner(): array
{
    return [
        'extra_scalars' => [
            'webfetch' => 'allow',
            'websearch' => 'allow',
            'external_directory' => 'allow',
            'task' => 'deny',
            'ask' => 'allow',
        ],
        'quote' => 'double',
    ];
}

/**
 * ui-builder's unique render shape (Slice D, docs/tickets/arch-todo-optional-agent-
 * permission-composition-20260705T221434Z/plan.md) — no `task:` key at all (ground truth
 * confirmed absent, same as implementer/refactorer), plus a `webfetch: deny` scalar no
 * other composed agent carries. Kept as a dedicated one-off builder (same precedent as
 * aiPermissionRenderScriptRunner()) since no other agent shares this combination.
 *
 * @return array{extra_scalars:array<string,string>,quote:string}
 */
function aiPermissionRenderUiBuilder(): array
{
    return ['extra_scalars' => ['webfetch' => 'deny'], 'quote' => 'single'];
}

/**
 * architecture-plan-writer's unique render shape (Slice C,
 * docs/tickets/arch-todo-complete-permission-composition-migration/plan.md) — the only
 * migrated agent needing `task:` BEFORE `edit:` and a nested `external_directory:` mapping
 * AFTER `edit:` but before `doom_loop:`/`bash:`. Kept as a dedicated one-off builder (same
 * precedent as aiPermissionRenderScriptRunner()) since no other agent shares this shape.
 *
 * @return array{extra_scalars:array<string,string>,extra_scalars_before_edit:array<string,string>,external_directory:array<string,string>,quote:string}
 */
function aiPermissionRenderArchitecturePlanWriter(): array
{
    return [
        'extra_scalars_before_edit' => ['task' => 'deny'],
        'external_directory' => [
            '~/Projects/awesome-ai-utmostcreator/docs/tickets/**' => 'allow',
            '$HOME/Projects/awesome-ai-utmostcreator/docs/tickets/**' => 'allow',
        ],
        'extra_scalars' => ['doom_loop' => 'ask'],
        'quote' => 'single',
    ];
}
