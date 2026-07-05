<?php

declare(strict_types=1);

require_once __DIR__ . '/core.php';

/** @return array<string,list<array{permission:string,pattern:string,effect:string}>> */
function aiPermissionVerifyTiers(): array
{
    return [
        'verify-none' => [],
        'verify-focused' => aiPermissionEntries('bash', [
            'bash scripts/ai/ai-doc-check.sh *' => 'allow',
            'bash scripts/ai/ai-verify.sh *' => 'ask',
            'AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
            'env AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
            'bash scripts/ai/ai-test-select.sh *' => 'allow',
            'bash scripts/ai/run-repo-tests.sh*' => 'allow',
        ]),
        'verify-full' => aiPermissionEntries('bash', [
            'bash scripts/ai/ai-doc-check.sh *' => 'allow',
            'bash scripts/ai/ai-verify.sh *' => 'ask',
            'AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
            'env AI_VERIFY_SCOPE=changed VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh *' => 'allow',
            'bash scripts/ai/ai-test-select.sh *' => 'allow',
            'bash scripts/ai/run-repo-tests.sh*' => 'allow',
            'composer test*' => 'allow',
            'composer test:fast*' => 'allow',
        ]),
    ];
}
