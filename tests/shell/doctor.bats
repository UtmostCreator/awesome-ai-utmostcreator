#!/usr/bin/env bats
# Tests for scripts/doctor.sh
#
# After the A3 fix (removing stale .lefthook.yml / .husky/ checks), doctor.sh
# should exit 0 in a fully valid environment and exit non-zero when required
# binaries are absent.
#
# Runs the real doctor.sh from the repo root to exercise all live checks.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/doctor.sh"

setup() {
    export HOME
    HOME="$(mktemp -d)"
    export XDG_CONFIG_HOME
    XDG_CONFIG_HOME="$(mktemp -d)"
    export GIT_CONFIG_GLOBAL=/dev/null
}

teardown() {
    rm -rf "$HOME" "$XDG_CONFIG_HOME" 2>/dev/null || true
}

# ---- happy path ----

@test "doctor.sh exits 0 against the live repo" {
    # This test verifies the whole chain: binaries, files, AI PHP validators.
    run bash "$SCRIPT"
    [ "$status" -eq 0 ]
}

@test "doctor.sh prints == app-configs doctor ==" {
    run bash "$SCRIPT"
    [[ "$output" == *"app-configs doctor"* ]]
}

@test "doctor.sh prints OK for required binaries" {
    run bash "$SCRIPT"
    [[ "$output" == *"[OK] binary 'bash' found"* ]]
}

@test "doctor.sh prints OK for core files" {
    run bash "$SCRIPT"
    [[ "$output" == *"[OK] file 'README.md' present"* ]]
}

# ---- optional binary absence ----

@test "doctor.sh exits 0 when optional binary bats is absent" {
    # Wrap PATH to exclude bats if present; doctor.sh should warn, not fail.
    FAKE_BIN="$(mktemp -d)"

    # Copy required binaries into fake bin
    for bin in bash git rg php; do
        src="$(command -v "$bin" 2>/dev/null || true)"
        if [[ -n "$src" ]]; then
            ln -sf "$src" "$FAKE_BIN/$bin"
        fi
    done

    run env PATH="$FAKE_BIN:$PATH" bash "$SCRIPT"
    # Optional missing = WARN not FAIL, exit 0
    [ "$status" -eq 0 ]

    rm -rf "$FAKE_BIN"
}

@test "doctor.sh warns about missing optional binary without failing" {
    run bash "$SCRIPT"
    # bats may or may not be installed — either OK or WARN is acceptable
    # The test verifies no ERROR line for optional binaries
    [[ "$output" != *"[ERROR] binary 'bats' missing"* ]]
}

# ---- stale file checks have been removed ----

@test "doctor.sh does not check for .lefthook.yml" {
    run bash "$SCRIPT"
    [[ "$output" != *".lefthook.yml"* ]]
}

@test "doctor.sh does not check for .husky/pre-commit" {
    run bash "$SCRIPT"
    [[ "$output" != *".husky"* ]]
}

# ---- required binary absence (simulated) ----

@test "doctor.sh exits non-zero when rg is absent" {
    FAKE_BIN="$(mktemp -d)"

    # Install all required binaries except rg
    for bin in bash git php; do
        src="$(command -v "$bin" 2>/dev/null || true)"
        if [[ -n "$src" ]]; then
            ln -sf "$src" "$FAKE_BIN/$bin"
        fi
    done
    # rg intentionally not linked

    run env PATH="$FAKE_BIN" bash "$SCRIPT"
    [ "$status" -ne 0 ]

    rm -rf "$FAKE_BIN"
}
