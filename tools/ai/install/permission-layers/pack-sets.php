<?php

declare(strict_types=1);

/**
 * Named, stable bundles of pack NAMES (not new packs) for agents that share an identical
 * multi-pack combination. Only add a bundle here when 2+ agents use the exact same
 * combination — a bundle used by a single agent is premature abstraction, the same
 * discipline packs.php's own atomic-pack precedent already documents (agents mix and match
 * independently more often than they share a fixed bundle). Do not add vague catch-all
 * bundle names; every bundle here must have one clear, stable meaning.
 */

/**
 * `refactorer` and `implementer` share this proof-tooling combination. Reconciled to 4
 * packs (Slice D, docs/tickets/arch-todo-complete-permission-composition-migration/plan.md):
 * `proof.php_lint`/`proof.phpunit_direct`/`proof.js_test_lint_typecheck` were dropped because
 * both agents now source PHP/JS commands via language overlays instead (refactorer:
 * `php-lint`+`php-phpunit`+`js-core`; implementer: the coarse `php`+`js-ts` union) — see each
 * agent's own composition entry for its overlay wiring. The remaining 4 (kit-tooling, not
 * language-specific) stay as explicit packs since both agents still need them identically.
 *
 * @return list<string>
 */
function aiPermissionPackSetFullProof(): array
{
    return [
        'proof.validate_script',
        'proof.generate_check',
        'proof.markdown',
        'proof.security',
    ];
}

/**
 * `architect` and `refactorer` share this exact 2-pack deny combination.
 *
 * @return list<string>
 */
function aiPermissionPackSetCommonReadDeny(): array
{
    return [
        'core.safe_read.deny_common_generics',
        'core.safe_read.deny_file_probe',
    ];
}
