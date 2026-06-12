#!/usr/bin/env bash
# 70-secrets.sh — secret-scanning helpers.
#
# Purpose: gitleaks wrapper, SECRETS_SCAN=0 bypass, and clean-failure on detect.
# Allowed dependencies: 05-core.sh (log_warn, die). No repomix, context packing,
#   rollback, or git reset.

[[ "${AI_LIB_SECRETS_LOADED:-0}" == "1" ]] && return 0
AI_LIB_SECRETS_LOADED=1

secrets_scan() {
    local target="${1:-.}"
    if command -v gitleaks >/dev/null 2>&1; then
        gitleaks detect --source "$target" --redact --no-banner --exit-code 1 >/dev/null 2>&1
    else
        log_warn "gitleaks not installed; skipping secrets scan"
        return 0
    fi
}

require_clean_secret_scan() {
    local target="${1:-.}"

    if [[ "${SECRETS_SCAN:-1}" != "1" ]]; then
        log_warn "SECRETS_SCAN disabled"
        return 0
    fi

    if ! secrets_scan "$target"; then
        die "secrets detected; refusing to continue"
    fi
}
