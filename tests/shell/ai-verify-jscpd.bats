#!/usr/bin/env bats
# Tests for the optional jscpd duplication guardrail
# (scripts/ai/internal/ai-verify/35-jscpd.sh), sourced by scripts/ai/ai-verify.sh.
#
# Hermetic: stubs a fake `jscpd` binary on PATH that writes a canned JSON
# report, so these tests never fetch the real tool from the network and never
# depend on actual code duplication in the repo.

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-verify.sh"

setup() {
    STUB_BIN="$(mktemp -d)"
    export PATH="$STUB_BIN:$PATH"
}

teardown() {
    rm -rf "$STUB_BIN" 2>/dev/null || true
}

# Writes a fake `jscpd` to $STUB_BIN that ignores its arguments and writes a
# jscpd-report.json with the given duplication percentage to the --output dir.
stub_jscpd() {
    local percentage="$1"
    cat >"$STUB_BIN/jscpd" <<EOF
#!/usr/bin/env bash
set -euo pipefail
out=""
args=("\$@")
for ((i = 0; i < \${#args[@]}; i++)); do
    if [[ "\${args[i]}" == "--output" ]]; then
        out="\${args[i+1]}"
    fi
done
mkdir -p "\$out"
cat >"\$out/jscpd-report.json" <<JSON
{"statistics":{"total":{"percentage":${percentage}}}}
JSON
EOF
    chmod +x "$STUB_BIN/jscpd"
}

@test "jscpd check is skipped by default (VERIFY_JSCPD unset)" {
    run env AI_VERIFY_TEST_MODE=1 bash "$SCRIPT" .
    [[ "$output" == *"Skipping jscpd duplication check. Use VERIFY_JSCPD=1 to enable."* ]]
}

@test "jscpd check is skipped when VERIFY_JSCPD=0" {
    run env AI_VERIFY_TEST_MODE=1 VERIFY_JSCPD=0 bash "$SCRIPT" .
    [[ "$output" == *"Skipping jscpd duplication check"* ]]
}

@test "jscpd check reports OK under the warn threshold" {
    stub_jscpd 1
    # VERIFY_LINECOUNT=0 isolates this test from any pre-existing, unrelated
    # line-count FAIL elsewhere in the repo so $status reflects jscpd alone.
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 VERIFY_JSCPD=1 JSCPD_PATHS=. JSCPD_WARN_PCT=5 bash "$SCRIPT" .
    [[ "$output" == *"jscpd duplication = 1% (under 5%)"* ]]
    [ "$status" -eq 0 ]
}

@test "jscpd check WARNs at/above JSCPD_WARN_PCT without JSCPD_FAIL_PCT set" {
    stub_jscpd 10
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 VERIFY_JSCPD=1 JSCPD_PATHS=. JSCPD_WARN_PCT=5 bash "$SCRIPT" .
    [[ "$output" == *"jscpd duplication = 10% >= 5%"* ]]
    [ "$status" -eq 0 ]
}

@test "jscpd check FAILs and exits non-zero when JSCPD_FAIL_PCT is crossed" {
    stub_jscpd 10
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 VERIFY_JSCPD=1 JSCPD_PATHS=. JSCPD_WARN_PCT=5 JSCPD_FAIL_PCT=8 bash "$SCRIPT" .
    [[ "$output" == *"jscpd duplication = 10% >= 8% (fail threshold)"* ]]
    [ "$status" -eq 1 ]
}

@test "jscpd check degrades gracefully when jscpd/npx produce no report" {
    cat >"$STUB_BIN/jscpd" <<'EOF'
#!/usr/bin/env bash
exit 2
EOF
    chmod +x "$STUB_BIN/jscpd"
    run env AI_VERIFY_TEST_MODE=1 VERIFY_LINECOUNT=0 VERIFY_JSCPD=1 JSCPD_PATHS=. bash "$SCRIPT" .
    [[ "$output" == *"jscpd produced no report"* ]]
    [ "$status" -eq 0 ]
}
