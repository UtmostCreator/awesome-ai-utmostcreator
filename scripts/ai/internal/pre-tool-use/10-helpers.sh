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

# F-1 fail-safe: invoked directly (not via a trap) from pre-tool-use.sh when
# the pre_tool_use_decide subshell exited without ever printing valid decision
# JSON — an internal crash (for example jq failing on a well-formed but
# resource-heavy payload), not an intentional deny/ask/allow. Classifies the
# raw payload with a dependency-free, best-effort text match (no jq/yq — those
# may be exactly what failed) so a hook-internal error never silently denies a
# read-only command with no output. Deny (with a remediation message) whenever
# read-only cannot be confirmed; this stays fail-closed for anything mutating
# or unknown.
#
# Review finding (fixed): the classifier must only look at the actual
# executed-args object (`toolArgs`/`toolArgsRaw`/`tool_input`, mirroring the
# same field fallback order `pre_tool_use_decide` uses), never the whole raw
# payload as unstructured text. Matching anywhere in the payload lets an
# unrelated sibling key (for example a decoy field that happens to contain the
# literal text `"command":"git status"`) flip a genuinely mutating command
# (in the real `toolArgs.command`) to "allow". Scoping first closes that gap;
# see the smuggled-payload regression test in tests/shell/pre-tool-use.bats.
#
# Second review finding (fixed): scoping to first-text-occurrence among
# `toolArgs`/`toolArgsRaw`/`tool_input` is not the same as the primary path's
# actual `.toolArgs // .toolArgsRaw // .tool_input` priority — `toolArgs` must
# win whenever present, regardless of where it sits in the raw text, so each
# field is tried independently in priority order instead of taking whichever
# occurs first.
#
# Third review finding (fixed): a single `toolArgs` object with a duplicate
# `command`/`commandLine`/`text` key (valid JSON; jq/JSON parsing is
# last-key-wins) let the scoped-but-unstructured regex match on an earlier,
# read-only-looking decoy value while the real, mutating value was the later
# (winning) one. `grep -c` occurrence counting on the scoped object closes
# this: more than one key match inside the same scope is ambiguous input for
# a text-only classifier, so it denies rather than guessing which one wins.
pre_tool_use_error_fallback() {
    local failed_exit_code="$1"
    local raw_input="${2:-}"
    local args_scope="" field key_matches

    if [[ -n "$raw_input" ]]; then
        for field in toolArgs toolArgsRaw tool_input; do
            args_scope="$(printf '%s' "$raw_input" | grep -Eo "\"$field\"[[:space:]]*:[[:space:]]*\\{[^}]*\\}" | head -n 1)"
            [[ -n "$args_scope" ]] && break
        done

        if [[ -n "$args_scope" ]]; then
            key_matches="$(printf '%s' "$args_scope" | grep -Eio '"(command|commandLine|text)"[[:space:]]*:' | wc -l)"
            if [[ "$key_matches" -eq 1 ]] &&
                { printf '%s' "$args_scope" | grep -Eiq '"(command|commandLine|text)"[[:space:]]*:[[:space:]]*"(git[[:space:]]+(status|diff|log|show|branch|grep|blame|ls-files|rev-parse|describe)|ls|pwd|rg|fd|cat|wc)([[:space:]"]|$)' ||
                    printf '%s' "$args_scope" | grep -Eiq '"(command|commandLine|text)"[[:space:]]*:[[:space:]]*"(bash[[:space:]]+)?(\./)?scripts/ai/(ai-search|preview-file|query-usage|ai-verify|run-repo-tests|run-test-focused|git-branch-origin)\.sh([[:space:]"]|$)'; }; then
                printf '{"permissionDecision":"allow","permissionDecisionReason":"internal hook error (exit %s) after the command was classified as read-only by the fail-safe fallback; allowing. See docs/ai/tool-policy.md to diagnose the hook failure."}\n' "$failed_exit_code"
                exit 0
            fi
        fi
    fi

    printf '{"permissionDecision":"deny","permissionDecisionReason":"internal hook error (exit %s); could not confirm the command is read-only, so denying by default. Re-run scripts/ai/pre-tool-use.sh with the same JSON payload manually to diagnose."}\n' "$failed_exit_code"
    exit 1
}

deny() {
    emit_decision_json "deny" "$1"
}

allow() {
    emit_decision_json "allow"
}

ask() {
    emit_decision_json "ask" "$1"
}

json_escape() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\"/\\\"}"
    s="${s//$'\n'/\\n}"
    s="${s//$'\r'/\\r}"
    s="${s//$'\t'/\\t}"
    printf '%s' "$s"
}

emit_decision_json() {
    local decision="$1"
    local reason="${2:-}"

    if command -v jq >/dev/null 2>&1; then
        if [[ -n "$reason" ]]; then
            jq -cn --arg decision "$decision" --arg reason "$reason" \
                '{permissionDecision:$decision, permissionDecisionReason:$reason}'
        else
            jq -cn --arg decision "$decision" '{permissionDecision:$decision}'
        fi
        return 0
    fi

    if [[ -n "$reason" ]]; then
        printf '{"permissionDecision":"%s","permissionDecisionReason":"%s"}\n' \
            "$(json_escape "$decision")" "$(json_escape "$reason")"
    else
        printf '{"permissionDecision":"%s"}\n' "$(json_escape "$decision")"
    fi
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

is_edit_tool() {
        case "$1" in
        edit/editFiles | editFiles | edit_file | editFile)
                return 0
                ;;
        *)
                return 1
                ;;
        esac
}

edit_payload_requests_delete() {
        local tool_args_raw="$1"

        command -v jq >/dev/null 2>&1 || return 1

        jq -e '
                [
                    .. | objects | select(
                        (.delete? == true)
                        or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "delete")
                        or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "remove")
                        or (((.patch? // .diff? // .content? // "") | tostring | test("(?m)^\\*\\*\\* Delete File:")))
                    )
                ] | length > 0
        ' <<<"$tool_args_raw" >/dev/null 2>&1
}

edit_payload_requests_rename() {
        local tool_args_raw="$1"

        command -v jq >/dev/null 2>&1 || return 1

        jq -e '
                [
                    .. | objects | select(
                        (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "rename")
                        or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "move")
                        or ((.from? | type == "string") and (.to? | type == "string"))
                        or (((.patch? // .diff? // .content? // "") | tostring | test("(?m)^\\*\\*\\* (Rename|Move) File:")))
                    )
                ] | length > 0
        ' <<<"$tool_args_raw" >/dev/null 2>&1
}

edit_payload_uses_create_delete_fallback() {
        local tool_args_raw="$1"

        command -v jq >/dev/null 2>&1 || return 1

        jq -e '
                (
                    [
                        .. | objects | select(
                            (.delete? == true)
                            or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "delete")
                            or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "remove")
                        )
                    ] | length > 0
                )
                and
                (
                    [
                        .. | objects | select(
                            (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "create")
                            or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "add")
                            or (((.type? // .operation? // .kind? // .action? // "") | tostring | ascii_downcase) == "new")
                        )
                    ] | length > 0
                )
        ' <<<"$tool_args_raw" >/dev/null 2>&1
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

        if [[ "$token" == "bash" || "$token" == "sh" || "$token" == "zsh" || "$token" == */bash || "$token" == */sh || "$token" == */zsh ]]; then
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
        if [[ "$executed_script" == "$path" || "$executed_script" == "./$path" || "$executed_script" == "$PWD/$path" || "$executed_script" == */"$path" ]]; then
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
