#!/usr/bin/env bats

REPO_ROOT="$(cd "$(dirname "$BATS_TEST_FILENAME")/../.." && pwd)"
TREE_SCRIPT="$REPO_ROOT/scripts/ai/repomix-context-tree.sh"
RUNNER_SCRIPT="$REPO_ROOT/scripts/ai/run-repomix-context.sh"
FIXTURE_BIN="$REPO_ROOT/tests/fixtures/bin"

setup() {
    command -v jq >/dev/null 2>&1 || skip "jq not in PATH"

    TMP_REPO="$(mktemp -d)"
    mkdir -p "$TMP_REPO/docs" "$TMP_REPO/php" "$TMP_REPO/generated"
    printf '%s\n' '# doc' >"$TMP_REPO/docs/readme.md"
    printf '%s\n' '<?php echo 1;' >"$TMP_REPO/php/app.php"
    printf '%s\n' '{"k":1}' >"$TMP_REPO/generated/cache.json"

    cat >"$TMP_REPO/.repomixignore" <<'EOF'
generated/**
EOF

    git -C "$TMP_REPO" init --quiet
    git -C "$TMP_REPO" config user.email test@example.com
    git -C "$TMP_REPO" config user.name Tester
    git -C "$TMP_REPO" add .
    git -C "$TMP_REPO" commit --quiet -m "init"

    chmod +x "$FIXTURE_BIN/scc" "$FIXTURE_BIN/repomix"
    export PATH="$FIXTURE_BIN:$PATH"
}

teardown() {
    rm -rf "$TMP_REPO" 2>/dev/null || true
}

@test "repomix-context-tree help exits 0" {
    run bash "$TREE_SCRIPT" --help
    [ "$status" -eq 0 ]
}

@test "repomix-context-tree script is syntactically valid" {
    run bash -n "$TREE_SCRIPT"
    [ "$status" -eq 0 ]
}

@test "plan generates contracted files and human sections" {
    run bash "$TREE_SCRIPT" plan "$TMP_REPO" --style xml --context-window 128000
    [ "$status" -eq 0 ]

    [ -f "$TMP_REPO/.repomix-context/tree-context/index.md" ]
    [ -f "$TMP_REPO/.repomix-context/tree-context/index.json" ]
    [ -f "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv" ]
    [ -f "$TMP_REPO/.repomix-context/tree-context/tree-plan.json" ]
    [ -f "$TMP_REPO/.repomix-context/tree-context/tree-manifest.json" ]
    [ -d "$TMP_REPO/.repomix-context/tree-context/bundles" ]

    run grep -F "# Context Index" "$TMP_REPO/.repomix-context/tree-context/index.md"
    [ "$status" -eq 0 ]
    run grep -F "## Top-Level Routes" "$TMP_REPO/.repomix-context/tree-context/index.md"
    [ "$status" -eq 0 ]
    run grep -F "## Next Steps For AI Agents" "$TMP_REPO/.repomix-context/tree-context/index.md"
    [ "$status" -eq 0 ]
    run grep -F "## Wiring Locations" "$TMP_REPO/.repomix-context/tree-context/index.md"
    [ "$status" -eq 0 ]
}

@test "tree-plan.tsv has required headers and at least one pack" {
    run bash "$TREE_SCRIPT" plan "$TMP_REPO" --style xml
    [ "$status" -eq 0 ]

    run awk -F'\t' 'NR==1 {print $1" "$2" "$3" "$4" "$5" "$6" "$7}' "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv"
    [ "$status" -eq 0 ]
    [ "$output" = "route type decision estimated_tokens budget output reason" ]

    run awk -F'\t' 'NR>1 && $3=="pack" {found=1} END{exit(found?0:1)}' "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv"
    [ "$status" -eq 0 ]
}

@test "low budget scenario produces split and child index" {
    export FAKE_SCC_SCENARIO="low-budget"
    run bash "$TREE_SCRIPT" plan "$TMP_REPO" --style xml --context-window 20000 --reserved-output 4000 --instruction-overhead 4000 --safety-factor 0.5
    [ "$status" -eq 0 ]

    run awk -F'\t' 'NR>1 && $3=="split" {print $6; found=1} END{exit(found?0:1)}' "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv"
    [ "$status" -eq 0 ]
    child_rel="$output"
    [ -f "$TMP_REPO/.repomix-context/tree-context/$child_rel" ]
}

@test "json outputs are valid" {
    run bash "$TREE_SCRIPT" plan "$TMP_REPO" --style xml
    [ "$status" -eq 0 ]

    run jq . "$TMP_REPO/.repomix-context/tree-context/tree-plan.json"
    [ "$status" -eq 0 ]
    run jq . "$TMP_REPO/.repomix-context/tree-context/index.json"
    [ "$status" -eq 0 ]
    run jq . "$TMP_REPO/.repomix-context/tree-context/tree-manifest.json"
    [ "$status" -eq 0 ]
}

@test "runner script syntax valid" {
    run bash -n "$RUNNER_SCRIPT"
    [ "$status" -eq 0 ]
}

@test "trailing-slash dir pattern excludes nested files (P0)" {
    backup_dir="$TMP_REPO/.ai-backups/install-x/files"
    mkdir -p "$backup_dir"
    printf '%s\n' '# backup snapshot' >"$backup_dir/y.md"

    cat >"$TMP_REPO/.repomixignore" <<'EOF'
generated/**
.ai-backups/
EOF

    git -C "$TMP_REPO" add -A
    git -C "$TMP_REPO" commit --quiet -m "add backup dir"

    run bash "$TREE_SCRIPT" plan "$TMP_REPO" --style xml
    [ "$status" -eq 0 ]

    # No route or plan row should reference the ignored backup directory.
    run grep -F ".ai-backups" "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv"
    [ "$status" -ne 0 ]
}

@test "hardcoded excludes apply with missing .repomixignore (P1)" {
    rm -f "$TMP_REPO/.repomixignore"
    mkdir -p "$TMP_REPO/.ai-backups/install-x/files" "$TMP_REPO/.ai-logs" "$TMP_REPO/.repomix-context/leftover"
    printf '%s\n' '# snapshot' >"$TMP_REPO/.ai-backups/install-x/files/y.md"
    printf '%s\n' '{"run":1}' >"$TMP_REPO/.ai-logs/run.jsonl"
    printf '%s\n' '{"old":1}' >"$TMP_REPO/.repomix-context/leftover/old.json"

    git -C "$TMP_REPO" add -A
    git -C "$TMP_REPO" commit --quiet -m "add ephemeral state without repomixignore"

    run bash "$TREE_SCRIPT" plan "$TMP_REPO" --style xml
    [ "$status" -eq 0 ]

    run grep -F ".ai-backups" "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv"
    [ "$status" -ne 0 ]
    run grep -F ".ai-logs" "$TMP_REPO/.repomix-context/tree-context/tree-plan.tsv"
    [ "$status" -ne 0 ]
}
