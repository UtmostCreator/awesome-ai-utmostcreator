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
