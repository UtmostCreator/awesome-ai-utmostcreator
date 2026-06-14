# shellcheck shell=bash
# Shipped AI-kit file predicates for the AI verification gate.
#
# This module is sourced by scripts/ai/ai-verify.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. It is sourced
# AFTER common.sh so it may use common.sh helpers, and BEFORE 10-scope.sh so the
# scope helpers can call should_skip_shipped_ai_kit_* predicates.
#
# These predicates decide whether a shipped kit file (scripts/ai/*.sh,
# tools/ai/*.php, install-*.sh, etc.) should be excluded from verification in an
# installed target repository while remaining in scope inside the kit's own
# authoring repo. Behavior is byte-for-byte identical to the previous monolithic
# ai-verify.sh; only the file layout changed.

is_shipped_ai_kit_shell_file() {
    case "$1" in
    install-ai-kit.sh | \
        .github/hooks/scripts/*.sh | \
        scripts/ai/*.sh | \
        scripts/hooks/*.sh | \
        tools/ai/install-*.sh | \
        tools/ai/install/*.sh)
        return 0
        ;;
    esac

    return 1
}

# Shipped AI-kit PHP files. The kit ships its tooling under tools/ai/**, so in an
# installed target repository those files are vendored support code, not the
# user's project code to lint with pint/phpstan/psalm. A case-glob '*' matches
# '/', so this single pattern covers every nesting depth under tools/ai/.
is_shipped_ai_kit_php_file() {
    case "$1" in
    tools/ai/*.php)
        return 0
        ;;
    esac

    return 1
}

# A shipped AI-kit shell file should be skipped only in an installed target
# repository. Inside the kit's own authoring repo these scripts ARE the product
# under test, so they must remain part of changed/branch verification here.
should_skip_shipped_ai_kit_shell_file() {
    is_shipped_ai_kit_shell_file "$1" && ! is_ai_kit_source_repo
}

# A shipped AI-kit PHP file should be skipped only in an installed target
# repository; inside the kit's own authoring repo it remains in scope.
should_skip_shipped_ai_kit_php_file() {
    is_shipped_ai_kit_php_file "$1" && ! is_ai_kit_source_repo
}

# True only when running inside the AI-kit's own authoring repository, where the
# shipped scripts/ai/*.sh wrappers and install-*.sh scripts ARE the product
# under test and should be linted/formatted. Installed target repositories
# receive scripts/ai/* but never the kit package source authoring layout, so in
# a target the shipped wrappers must not become part of that project's
# verification burden.
#
# Detection requires the authoring-only artifacts together (not a single
# vendorable file like catalog.json), matching scripts/hooks/pre-commit.sh so
# "delivered vs source" is determined the same way across the kit. Override with
# AI_KIT_SELF_VERIFY=1 (force self-verify) or 0 (force target mode).
is_ai_kit_source_repo() {
    case "${AI_KIT_SELF_VERIFY:-auto}" in
    1) return 0 ;;
    0) return 1 ;;
    esac
    [[ -d packages/ai-universal-rules/templates &&
        -f packages/ai-universal-rules/package-lock.ai.json &&
        -f tools/ai/ai.php &&
        -f tools/ai/generate-ai-catalog.php ]]
}
