#!/usr/bin/env bats
# Tests for scripts/ai/pre-tool-use.sh
#
# Input contract: {"toolName":"bash","toolArgs":{"command":"..."}}
# Terminal tools are evaluated by command policy; edit/editFiles payloads are
# evaluated for rename/delete approval boundaries.
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

@test "allows non-destructive edit payload" {
    output=$(_hook '{"toolName":"edit/editFiles","toolArgs":{"edits":[{"type":"modify","filePath":"README.md","newText":"updated"}]}}')
    [ "$(_decision "$output")" = "allow" ]
}

@test "asks for delete edit payload" {
    output=$(_hook '{"toolName":"edit/editFiles","toolArgs":{"edits":[{"type":"delete","filePath":"README.md"}]}}')
    [ "$(_decision "$output")" = "ask" ]
}

@test "asks for rename edit payload" {
    output=$(_hook '{"toolName":"edit/editFiles","toolArgs":{"edits":[{"type":"rename","from":"README.md","to":"README-old.md"}]}}')
    [ "$(_decision "$output")" = "ask" ]
}

@test "asks for create plus delete rename fallback payload" {
    output=$(_hook '{"toolName":"edit/editFiles","toolArgs":{"edits":[{"type":"create","filePath":"README-old.md","contents":"copy"},{"type":"delete","filePath":"README.md"}]}}')
    [ "$(_decision "$output")" = "ask" ]
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

# ---- F-1 fail-safe: internal hook error must never silently deny ----
#
# These simulate jq failing partway through decision logic on a well-formed
# payload (for example a timeout or OOM on a resource-heavy request) with a
# fake `jq` that succeeds only for the initial `-e .` validity check and
# fails for every other call. Before the F-1 fix, this crashed pre-tool-use.sh
# with no decision JSON at all (fail-closed with no diagnosis, even for a
# read-only command). After the fix, a valid decision is always emitted.

_with_broken_jq() {
    local payload="$1"
    local fakebin
    fakebin="$(mktemp -d)"
    local real_jq
    real_jq="$(command -v jq)"
    cat >"$fakebin/jq" <<EOF
#!/usr/bin/env bash
if [[ "\$1" == "-e" && "\$2" == "." ]]; then
    exec "$real_jq" "\$@"
fi
echo "fake jq: simulated internal failure" >&2
exit 1
EOF
    chmod +x "$fakebin/jq"
    echo "$payload" | PATH="$fakebin:$PATH" bash "$SCRIPT"
    local exit_code=$?
    rm -rf "$fakebin"
    return $exit_code
}

@test "F-1: internal jq failure on a read-only command still allows" {
    output="$(_with_broken_jq '{"toolName":"bash","toolArgs":{"command":"git status"}}')"
    [ "$(_decision "$output")" = "allow" ]
}

@test "F-1: internal jq failure on a mutating command still denies" {
    output="$(_with_broken_jq '{"toolName":"bash","toolArgs":{"command":"rm -rf /tmp/whatever"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: internal jq failure always emits valid decision JSON" {
    output="$(_with_broken_jq '{"toolName":"bash","toolArgs":{"command":"git status"}}')"
    echo "$output" | jq . >/dev/null
    echo "$output" | jq -e '.permissionDecisionReason' >/dev/null
}

@test "F-1: fail-safe fallback ignores a decoy read-only field outside toolArgs" {
    # Reviewer-found bypass: a sibling key elsewhere in the payload that
    # happens to contain a read-only-looking "command" value must not flip
    # the real (mutating) toolArgs.command to allow. The fallback must scope
    # its match to toolArgs/toolArgsRaw/tool_input only.
    output="$(_with_broken_jq '{"toolName":"bash","toolArgs":{"command":"rm -rf /important-data"},"decoyField":{"command":"git status"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback allows cat/wc with a real argument" {
    # Regression for a dead-code regex bug: the cat/wc alternatives previously
    # embedded an extra space that, combined with the trailing anchor, only
    # matched the bare literal "cat"/"wc" with no argument.
    output="$(_with_broken_jq '{"toolName":"bash","toolArgs":{"command":"cat README.md"}}')"
    [ "$(_decision "$output")" = "allow" ]
}

@test "helper module source failure still emits valid deny JSON" {
    local fakeroot
    fakeroot="$(mktemp -d)"
    mkdir -p "$fakeroot/scripts/ai/internal/pre-tool-use"
    cp "$SCRIPT" "$fakeroot/scripts/ai/pre-tool-use.sh"
    cp "$REPO_ROOT/scripts/ai/internal/pre-tool-use/20-decide.sh" "$fakeroot/scripts/ai/internal/pre-tool-use/"
    printf '%s\n' 'this is not valid bash syntax {{{ [[[' >"$fakeroot/scripts/ai/internal/pre-tool-use/10-helpers.sh"

    output="$(echo '{"toolName":"bash","toolArgs":{"command":"git status"}}' | bash "$fakeroot/scripts/ai/pre-tool-use.sh" 2>/dev/null || true)"
    rm -rf "$fakeroot"

    echo "$output" | jq . >/dev/null
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback enforces toolArgs priority over toolArgsRaw/tool_input" {
    # Reviewer-found bypass: the primary path resolves .toolArgs // .toolArgsRaw
    # // .tool_input in that priority order regardless of position in the raw
    # text. A decoy tool_input appearing before the real toolArgs in raw text
    # must not win just because it comes first.
    output="$(_with_broken_jq '{"tool_input":{"command":"git status"},"toolArgs":{"command":"rm -rf /important-data"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback denies on a duplicate command key within toolArgs" {
    # Reviewer-found bypass: valid JSON allows a duplicate key inside the same
    # scoped object (JSON/jq semantics are last-key-wins). A text-only
    # classifier cannot safely determine which value wins, so it must deny
    # rather than match on whichever occurrence happens to look read-only.
    output="$(_with_broken_jq '{"toolArgs":{"command":"git status","command":"rm -rf /important-data"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback still allows toolArgsRaw when toolArgs is absent" {
    output="$(_with_broken_jq '{"toolArgsRaw":{"command":"git log"}}')"
    [ "$(_decision "$output")" = "allow" ]
}

@test "F-1: fail-safe fallback denies a nested decoy key before the real command inside toolArgs" {
    # 3rd-round reviewer finding: a flat [^}]*-bounded regex stops at the
    # first nested "}", truncating the real command key out of scope so a
    # nested decoy object wins. The depth-aware extractor must capture the
    # full toolArgs value (including the nested decoy), which the existing
    # duplicate-key ambiguity check then denies rather than needing to pick a
    # winner.
    output="$(_with_broken_jq '{"toolArgs":{"decoy":{"command":"git status"},"command":"rm -rf /important-data"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback denies a decoy toolArgs nested in a wrapper before the real top-level one" {
    # 3rd-round reviewer finding: a flat regex with no nesting-depth concept
    # let a same-named decoy key nested inside an unrelated sibling object win
    # just because it appeared earlier in raw text. Only a top-level (depth-1)
    # toolArgs key may match.
    output="$(_with_broken_jq '{"wrapper":{"toolArgs":{"command":"git status"}},"toolArgs":{"command":"rm -rf /important-data"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback denies when toolArgs is a non-object value" {
    output="$(_with_broken_jq '{"toolArgs":"not-an-object","tool_input":{"command":"rm -rf /important-data"}}' || true)"
    [ "$(_decision "$output")" = "deny" ]
}

@test "F-1: fail-safe fallback falls through past a null toolArgs to tool_input" {
    output="$(_with_broken_jq '{"toolArgs":null,"tool_input":{"command":"git log"}}')"
    [ "$(_decision "$output")" = "allow" ]
}

@test "F-1: fail-safe fallback handles whitespace-spanning toolArgs" {
    output="$(_with_broken_jq '{
      "toolArgs"  :  {
        "command" : "git status"
      }
    }')"
    [ "$(_decision "$output")" = "allow" ]
}
