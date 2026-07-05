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
 * `refactorer` and `implementer` share this exact 7-pack proof-tooling combination.
 *
 * @return list<string>
 */
function aiPermissionPackSetFullProof(): array
{
    return [
        'proof.php_lint',
        'proof.phpunit_direct',
        'proof.js_test_lint_typecheck',
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
