# shellcheck shell=bash
# Helper functions for the pre-tool-use policy gate.
#
# This module is sourced by scripts/ai/pre-tool-use.sh (the thin root loader);
# it is NOT an entrypoint and must not be executed directly. All policy
# decisions are emitted by these helpers; the procedural evaluation order lives
# in 20-decide.sh.
#
# Behavior is byte-for-byte identical to the previous monolithic
# pre-tool-use.sh; only the file layout changed.

usage() {
    cat <<'EOF'
Usage:
  pre-tool-use.sh < tool-request.json

Reads a JSON tool request from stdin and emits a permission decision when policy applies.
EOF
}

deny() {
    jq -cn --arg reason "$1" '{permissionDecision:"deny", permissionDecisionReason:$reason}'
}

allow() {
    jq -cn '{permissionDecision:"allow"}'
}

is_terminal_tool() {
    case "$1" in
    bash | runTerminalCommand | execute/runInTerminal)
        return 0
        ;;
    *)
        return 1
        ;;
    esac
}

executed_script_token() {
    local compact="$1"
    local token next_token
    local tokens=()
    local index

    read -r -a tokens <<<"$compact"
    for ((index = 0; index < ${#tokens[@]}; index++)); do
        token="${tokens[$index]}"

        if [[ "$token" == "env" ]]; then
            continue
        fi

        if [[ "$token" =~ ^[A-Za-z_][A-Za-z0-9_]*= ]]; then
            continue
        fi

        if [[ "$token" == "bash" || "$token" == "sh" || "$token" == "zsh" ]]; then
            next_token="${tokens[$((index + 1))]:-}"
            [[ -n "$next_token" ]] && printf '%s\n' "$next_token"
            return 0
        fi

        printf '%s\n' "$token"
        return 0
    done

    return 1
}

registered_script_paths() {
    local registry_file="${AI_SCRIPT_REGISTRY_FILE:-${COPILOT_SCRIPT_REGISTRY_FILE:-docs/ai/script-registry.json}}"

    if ! command -v jq >/dev/null 2>&1 || [[ ! -f "$registry_file" ]]; then
        return 1
    fi

    jq -r '
        [
          (.scripts // {} | to_entries[]?.value.installed_path),
          (.scripts // {} | to_entries[]?.value.source_path),
          (.scripts[]? | select(.approval == "allow") | .path)
        ]
        | flatten
        | map(select(type == "string" and . != ""))
        | unique[]
    ' "$registry_file" 2>/dev/null
}

maintenance_mode_active() {
    [[ -f "$MAINTENANCE_STATE_FILE" ]] || return 1
    command -v jq >/dev/null 2>&1 || return 1

    jq -e '(.enabled // false) == true and ((.expires_at_epoch // 0) > now)' "$MAINTENANCE_STATE_FILE" >/dev/null 2>&1
}

allow_registered_script() {
    local compact="$1"
    local path executed_script

    executed_script="$(executed_script_token "$compact" || true)"
    [[ -n "$executed_script" ]] || return 1

    if ! command -v jq >/dev/null 2>&1; then
        return 1
    fi

    while IFS= read -r path; do
        [[ -n "$path" ]] || continue
        if [[ "$executed_script" == "$path" || "$executed_script" == "./$path" ]]; then
            return 0
        fi
    done < <(registered_script_paths)

    return 1
}

evaluate_policy_yaml() {
    local compact="$1"

    command -v yq >/dev/null 2>&1 || return 1
    [[ -f "$POLICY_FILE" ]] || return 1

    local encoded rule pattern reason

    policy_match() {
        local pattern="$1"
        PATTERN="$pattern" perl -e 'my $pattern = $ENV{PATTERN}; my $input = do { local $/; <STDIN> }; exit(($input =~ /$pattern/m) ? 0 : 1);' <<<"$compact"
    }

    while IFS= read -r encoded; do
        [[ -n "$encoded" ]] || continue
        rule="$(printf '%s' "$encoded" | base64 -d)"
        pattern="$(printf '%s' "$rule" | yq -r '.pattern')"
        reason="$(printf '%s' "$rule" | yq -r '.reason')"
        if policy_match "$pattern"; then
            deny "$reason"
            exit 0
        fi
    done < <(yq -r '.deny[]? | @base64' "$POLICY_FILE" 2>/dev/null || true)

    if [[ "${AI_STRICT_ALLOWLIST:-${COPILOT_STRICT_ALLOWLIST:-0}}" != '1' ]]; then
        while IFS= read -r encoded; do
            [[ -n "$encoded" ]] || continue
            rule="$(printf '%s' "$encoded" | base64 -d)"
            pattern="$(printf '%s' "$rule" | yq -r '.pattern')"
            if policy_match "$pattern"; then
                allow
                exit 0
            fi
        done < <(yq -r '.allow[]? | @base64' "$POLICY_FILE" 2>/dev/null || true)
    fi

    while IFS= read -r encoded; do
        [[ -n "$encoded" ]] || continue
        rule="$(printf '%s' "$encoded" | base64 -d)"
        pattern="$(printf '%s' "$rule" | yq -r '.pattern')"
        reason="$(printf '%s' "$rule" | yq -r '.reason')"
        if policy_match "$pattern"; then
            jq -cn --arg reason "$reason" '{permissionDecision:"ask", permissionDecisionReason:$reason}'
            exit 0
        fi
    done < <(yq -r '.confirm[]? | @base64' "$POLICY_FILE" 2>/dev/null || true)

    return 1
}
