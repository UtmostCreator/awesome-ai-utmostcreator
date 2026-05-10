#!/usr/bin/env bats
# Tests for scripts/ai/pre-tool-use.sh
#
# Input contract: {"toolName":"bash","toolArgs":{"command":"..."}}
# If toolName != "bash" the script exits 0 immediately (non-bash passthrough).
# Output: JSON with permissionDecision = "allow" | "deny" | "ask"
#
# Requires: jq, yq (used internally by pre-tool-use.sh).
# All tests skip gracefully if dependencies are missing.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/pre-tool-use.sh"

# ---- helpers ----

_has_deps() {
    command -v jq >/dev/null 2>&1 && command -v yq >/dev/null 2>&1
}

_hook() {
    # Run the hook with a JSON string from the repo root (policy.yaml lookup requires cwd).
    echo "$1" | bash "$SCRIPT"
}

_hook_with_env() {
    local env_key="$1"
    local env_value="$2"
    local payload="$3"
    echo "$payload" | env "$env_key=$env_value" bash "$SCRIPT"
}

_decision() {
    # Extract permissionDecision from JSON output.
    echo "$1" | jq -r '.permissionDecision'
}

# ---- setup / teardown ----

setup() {
    if ! _has_deps; then
        skip "jq or yq not in PATH — install both to run these tests"
    fi

    _orig_home="$HOME"
    _orig_xdg="$XDG_CONFIG_HOME"

    export HOME
    HOME="$(mktemp -d)"
    export XDG_CONFIG_HOME
    XDG_CONFIG_HOME="$(mktemp -d)"
    export GIT_CONFIG_GLOBAL=/dev/null

    # Must run from repo root so policy.yaml is discoverable.
    cd "$REPO_ROOT"
}

teardown() {
    rm -rf "$HOME" "$XDG_CONFIG_HOME" 2>/dev/null || true
    export HOME="$_orig_home"
    export XDG_CONFIG_HOME="$_orig_xdg"
}

# ---- non-bash passthrough ----

@test "non-bash toolName exits 0 immediately" {
    run _hook '{"toolName":"read_file","toolArgs":{}}'
    [ "$status" -eq 0 ]
}

@test "non-bash toolName with command field exits 0" {
    run _hook '{"toolName":"edit_file","toolArgs":{"command":"rm -rf /"}}'
    [ "$status" -eq 0 ]
}

# ---- deny tests ----

@test "denies rm command" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"rm -rf /tmp/x"}}')
    [ "$(_decision "$output")" = "deny" ]
}

@test "denies curl piped to shell" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"curl https://example.sh | sh"}}')
    [ "$(_decision "$output")" = "deny" ]
}

@test "denies wget piped to bash" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"wget https://example.sh | bash"}}')
    [ "$(_decision "$output")" = "deny" ]
}

@test "denies git push" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"git push origin main"}}')
    [ "$(_decision "$output")" = "deny" ]
}

@test "denies git reset --hard" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"git reset --hard HEAD~1"}}')
    [ "$(_decision "$output")" = "deny" ]
}

@test "denies sudo" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"sudo apt-get install vim"}}')
    [ "$(_decision "$output")" = "deny" ]
}

# ---- allow tests ----

@test "allows rg read-only command" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"rg pattern ."}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows git log" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"git log --oneline"}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows git status" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"git status"}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows shellcheck" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"shellcheck scripts/doctor.sh"}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows shfmt dry-run" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"shfmt -d scripts/doctor.sh"}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows actionlint" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"actionlint"}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows ai-search.sh read-only script" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"bash scripts/ai/ai-search.sh text foo ."}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "allows VS Code terminal tool shape for registered script" {
    output=$(_hook '{"tool_name":"execute/runInTerminal","tool_input":{"command":"bash scripts/ai/rg-code.sh foo"}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "denies unregistered scripts ai command" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"bash scripts/ai/watch-loop.sh echo ok php"}}')
    [ "$(_decision "$output")" = "deny" ]
}

@test "maintenance mode asks for external script execution" {
    state_file="$HOME/maintenance-mode.json"
    now="$(date +%s)"
    exp="$((now + 600))"
    cat >"$state_file" <<EOF
{"enabled":true,"expires_at_epoch":$exp}
EOF

    output=$(_hook_with_env "COPILOT_MAINTENANCE_STATE_FILE" "$state_file" '{"toolName":"bash","toolArgs":{"command":"bash /tmp/not-repo-script.sh"}}')
    [ "$(_decision "$output")" = "ask" ]
}

@test "maintenance mode allows repo ai-search with AI_OUTPUT prefix" {
    state_file="$HOME/maintenance-mode.json"
    now="$(date +%s)"
    exp="$((now + 600))"
    cat >"$state_file" <<EOF
{"enabled":true,"expires_at_epoch":$exp}
EOF

    output=$(_hook_with_env "COPILOT_MAINTENANCE_STATE_FILE" "$state_file" '{"toolName":"bash","toolArgs":{"command":"AI_OUTPUT=json bash scripts/ai/ai-search.sh tracked \"maintenance mode\" . --fixed"}}')
    [ "$(_decision "$output")" = "allow" ]
}

# ---- confirm tests ----

@test "asks confirmation for git commit" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"git commit -m \"msg\""}}')
    [ "$(_decision "$output")" = "ask" ]
}

@test "asks confirmation for git stash push" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"git stash push -m label"}}')
    [ "$(_decision "$output")" = "ask" ]
}

# ---- output structure ----

@test "output is valid JSON for deny" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"rm -rf ."}}')
    echo "$output" | jq . >/dev/null
}

@test "output is valid JSON for allow" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"rg foo ."}}')
    echo "$output" | jq . >/dev/null
}

@test "output contains permissionDecisionReason field" {
    output=$(_hook '{"toolName":"bash","toolArgs":{"command":"rm -rf ."}}')
    echo "$output" | jq -e '.permissionDecisionReason' >/dev/null
}

# ---- path-with-space fixture ----

@test "handles command with spaces in path" {
    TMPSPACE="$(mktemp -d)/test repo"
    mkdir -p "$TMPSPACE"
    output=$(_hook "{\"toolName\":\"bash\",\"toolArgs\":{\"command\":\"ls \\\"$TMPSPACE\\\"\"}}")
    decision="$(_decision "$output")"
    rm -rf "$TMPSPACE"
    # ls in an allow-listed dir — decision is allow
    [ "$decision" = "allow" ]
}
