#!/usr/bin/env bash
# Tests for scripts/ai/ai-edit.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/ai/ai-edit.sh"
BASH_BIN="${BASH_BIN:-$(command -v bash)}"

PASS=0
FAIL=0
SKIP=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

run_test() {
    local name="$1"; shift
    local rc=0
    "$@" >/dev/null 2>&1 || rc=$?
    if ((rc == 0)); then
        PASS=$((PASS + 1))
        printf '  \033[0;32m✓\033[0m %s\n' "$name"
    else
        FAIL=$((FAIL + 1))
        printf '  \033[0;31m✗\033[0m %s\n' "$name"
    fi
}

skip_test() {
    SKIP=$((SKIP + 1))
    printf '  \033[0;33m⊘\033[0m %s (skipped: %s)\n' "$1" "$2"
}

make_repo() {
    local dir="$1"
    mkdir -p "$dir"
    git -C "$dir" init -q
    git -C "$dir" config user.email "ai-edit-test@example.test"
    git -C "$dir" config user.name "AI Edit Test"
    printf 'OldName\nKeep\n' >"$dir/a.txt"
    git -C "$dir" add a.txt
    git -C "$dir" commit -q -m "init"
}

run_edit() {
    local work="$1"; shift
    (
        cd "$work"
        AI_LOG_DIR="$TMP/logs" \
        AI_EVENT_LOG="$TMP/logs/events.jsonl" \
        "$BASH_BIN" "$SCRIPT" "$@"
    )
}

# sd planning (dry-run, no-match, limit, exclude) only needs rg + jq; the
# sd binary is only invoked when --apply mutates files. Gate the two layers
# independently so plan-only coverage stays green even when sd is absent.
need_sd_plan() {
    command -v rg >/dev/null 2>&1 && command -v jq >/dev/null 2>&1
}

need_sd_apply() {
    need_sd_plan && command -v sd >/dev/null 2>&1
}

printf 'ai-edit.sh\n'

test_help() {
    "$BASH_BIN" "$SCRIPT" --help 2>&1 | grep -q 'Status values'
}
run_test "help prints AI-facing contract sections" test_help

test_format_help() {
    "$BASH_BIN" "$SCRIPT" --format=help 2>&1 | grep -q 'Machine contract'
}
run_test "--format=help works without mode" test_format_help

test_introspect() {
    # Parity with ai-search.sh: --introspect is intercepted by common.sh and
    # delegated to the static introspector, emitting ai.sh-introspect/v1 with
    # tool=sh-introspect and meta.target_executed=false. It must NOT run the
    # edit logic. The three edit modes are still parsed out statically.
    "$BASH_BIN" "$SCRIPT" --introspect \
        | jq -e '.schema=="ai.sh-introspect/v1"
            and .tool=="sh-introspect"
            and .meta.target_executed==false
            and ((.modes|map(.name)) as $m
                 | ($m|index("ast-grep")) and ($m|index("comby")) and ($m|index("sd")))' >/dev/null
}
run_test "--introspect emits static contract (ai.sh-introspect/v1)" test_introspect

test_missing_mode() {
    ! "$BASH_BIN" "$SCRIPT" >/dev/null 2>&1
}
run_test "missing mode fails" test_missing_mode

test_unknown_mode() {
    local work="$TMP/unknown"
    make_repo "$work"
    ! run_edit "$work" nonexistent --format json >/tmp/ai-edit-unknown.json 2>/dev/null
}
run_test "unknown mode fails" test_unknown_mode

if need_sd_plan; then
    test_sd_no_matches_json() {
        local work="$TMP/no-matches" out
        make_repo "$work"
        out="$(run_edit "$work" sd Missing Replacement . --format json)"
        jq -e '.status=="no_matches" and .plannedChanges==[] and .errors==[]' <<<"$out" >/dev/null
    }
    run_test "sd no matches returns no_matches JSON" test_sd_no_matches_json

    test_sd_dry_run_json_does_not_modify() {
        local work="$TMP/dry-run" out
        make_repo "$work"
        out="$(run_edit "$work" sd OldName NewName . --format json)"
        jq -e '.status=="dry_run" and (.plannedChanges|length)==1 and .plannedChanges[0].replacements==1' <<<"$out" >/dev/null
        grep -q 'OldName' "$work/a.txt"
        ! grep -q 'NewName' "$work/a.txt"
    }
    run_test "sd dry-run plans exact change and does not modify" test_sd_dry_run_json_does_not_modify

    test_sd_max_files_blocks() {
        local work="$TMP/max-files" out rc=0
        make_repo "$work"
        printf 'OldName\n' >"$work/b.txt"
        git -C "$work" add b.txt
        git -C "$work" commit -q -m "second"

        out="$(run_edit "$work" sd OldName NewName . --max-files 1 --format json 2>/dev/null)" || rc=$?
        ((rc != 0))
        jq -e '.status=="limit_exceeded" and (.errors|length)>=1' <<<"$out" >/dev/null
        grep -q 'OldName' "$work/a.txt"
        grep -q 'OldName' "$work/b.txt"
    }
    run_test "sd max-files blocks before mutation" test_sd_max_files_blocks

    test_sd_max_replacements_blocks() {
        local work="$TMP/max-replacements" out rc=0
        make_repo "$work"
        printf 'OldName OldName OldName\n' >"$work/a.txt"
        git -C "$work" add a.txt
        git -C "$work" commit -q -m "many"

        out="$(run_edit "$work" sd OldName NewName . --max-replacements 1 --format json 2>/dev/null)" || rc=$?
        ((rc != 0))
        jq -e '.status=="limit_exceeded" and (.errors|length)>=1' <<<"$out" >/dev/null
        grep -q 'OldName' "$work/a.txt"
    }
    run_test "sd max-replacements blocks before mutation" test_sd_max_replacements_blocks

    test_sd_exclude_prevents_match() {
        local work="$TMP/exclude" out
        make_repo "$work"
        out="$(run_edit "$work" sd OldName NewName . --exclude 'a.txt' --format json)"
        jq -e '.status=="no_matches" and .plannedChanges==[]' <<<"$out" >/dev/null
        grep -q 'OldName' "$work/a.txt"
    }
    run_test "sd --exclude prevents replacement planning" test_sd_exclude_prevents_match

    test_ai_output_json_env() {
        local work="$TMP/env-json" out
        make_repo "$work"
        out="$(
            cd "$work"
            AI_OUTPUT=json \
            AI_LOG_DIR="$TMP/logs-env" \
            AI_EVENT_LOG="$TMP/logs-env/events.jsonl" \
            "$BASH_BIN" "$SCRIPT" sd OldName NewName .
        )"
        jq -e '.schema=="ai.edit/v1" and .status=="dry_run"' <<<"$out" >/dev/null
    }
    run_test "AI_OUTPUT=json emits JSON" test_ai_output_json_env
else
    skip_test "sd no matches returns no_matches JSON" "requires rg, jq"
    skip_test "sd dry-run plans exact change and does not modify" "requires rg, jq"
    skip_test "sd max-files blocks before mutation" "requires rg, jq"
    skip_test "sd max-replacements blocks before mutation" "requires rg, jq"
    skip_test "sd --exclude prevents replacement planning" "requires rg, jq"
    skip_test "AI_OUTPUT=json emits JSON" "requires rg, jq"
fi

if need_sd_apply; then
    test_sd_apply_json_modifies_file() {
        local work="$TMP/apply" out
        make_repo "$work"
        out="$(run_edit "$work" sd OldName NewName . --apply --no-verify --format json)"
        jq -e '.status=="applied" and .apply==true and (.plannedChanges|length)==1' <<<"$out" >/dev/null
        grep -q 'NewName' "$work/a.txt"
    }
    run_test "sd apply modifies file and returns applied JSON" test_sd_apply_json_modifies_file
else
    skip_test "sd apply modifies file and returns applied JSON" "requires rg, sd, jq"
fi

# --- patch mode -------------------------------------------------------------
# patch mode needs only git + jq (git apply ships with git), so it does not
# depend on rg/sd availability.
need_patch() {
    command -v git >/dev/null 2>&1 && command -v jq >/dev/null 2>&1
}

# Build a unified diff that turns a.txt's "OldName" line into "NewName" and
# write it to $1, relative to the repo at $2.
make_patch() {
    local out="$1" work="$2"
    cat >"$out" <<'EOF'
diff --git a/a.txt b/a.txt
index 0000000..1111111 100644
--- a/a.txt
+++ b/a.txt
@@ -1,2 +1,2 @@
-OldName
+NewName
 Keep
EOF
}

if need_patch; then
    test_patch_dry_run_plans_no_modify() {
        local work="$TMP/patch-dry" out
        make_repo "$work"
        make_patch "$work/change.patch" "$work"
        out="$(run_edit "$work" patch change.patch . --format json)"
        jq -e '.status=="dry_run"
            and (.plannedChanges|length)==1
            and .plannedChanges[0].path=="a.txt"
            and .plannedChanges[0].operation=="patch"' <<<"$out" >/dev/null
        grep -q 'OldName' "$work/a.txt"
        ! grep -q 'NewName' "$work/a.txt"
    }
    run_test "patch dry-run plans changed file and does not modify" test_patch_dry_run_plans_no_modify

    test_patch_apply_modifies_file() {
        local work="$TMP/patch-apply" out
        make_repo "$work"
        make_patch "$work/change.patch" "$work"
        out="$(run_edit "$work" patch change.patch . --apply --no-verify --format json)"
        jq -e '.status=="applied" and .apply==true and (.plannedChanges|length)==1' <<<"$out" >/dev/null
        grep -q 'NewName' "$work/a.txt"
    }
    run_test "patch apply modifies file and returns applied JSON" test_patch_apply_modifies_file

    test_patch_stdin_apply() {
        local work="$TMP/patch-stdin" out
        make_repo "$work"
        make_patch "$work/change.patch" "$work"
        out="$(
            cd "$work"
            AI_OUTPUT=json \
            AI_LOG_DIR="$TMP/logs-patch-stdin" \
            AI_EVENT_LOG="$TMP/logs-patch-stdin/events.jsonl" \
            "$BASH_BIN" "$SCRIPT" patch - . --apply --no-verify <change.patch
        )"
        jq -e '.status=="applied"' <<<"$out" >/dev/null
        grep -q 'NewName' "$work/a.txt"
    }
    run_test "patch reads diff from stdin and applies" test_patch_stdin_apply

    test_patch_unsafe_path_blocked() {
        local work="$TMP/patch-unsafe" out rc=0
        make_repo "$work"
        cat >"$work/evil.patch" <<'EOF'
diff --git a/.git/config b/.git/config
--- a/.git/config
+++ b/.git/config
@@ -1 +1 @@
-x
+y
EOF
        out="$(run_edit "$work" patch evil.patch . --format json 2>/dev/null)" || rc=$?
        ((rc != 0))
        jq -e '.status=="blocked" and (.errors|length)>=1' <<<"$out" >/dev/null
    }
    run_test "patch with .git path is blocked" test_patch_unsafe_path_blocked

    test_patch_secret_path_blocked() {
        local work="$TMP/patch-secret" out rc=0
        make_repo "$work"
        cat >"$work/secret.patch" <<'EOF'
diff --git a/.env b/.env
new file mode 100644
--- /dev/null
+++ b/.env
@@ -0,0 +1 @@
+TOKEN=abc
EOF
        out="$(run_edit "$work" patch secret.patch . --format json 2>/dev/null)" || rc=$?
        ((rc != 0))
        jq -e '.status=="blocked"' <<<"$out" >/dev/null
        [[ ! -f "$work/.env" ]]
    }
    run_test "patch targeting .env is blocked" test_patch_secret_path_blocked

    test_patch_does_not_apply_blocked() {
        local work="$TMP/patch-conflict" out rc=0
        make_repo "$work"
        cat >"$work/bad.patch" <<'EOF'
diff --git a/a.txt b/a.txt
--- a/a.txt
+++ b/a.txt
@@ -1,2 +1,2 @@
-DoesNotExist
+Replacement
 Keep
EOF
        out="$(run_edit "$work" patch bad.patch . --format json 2>/dev/null)" || rc=$?
        ((rc != 0))
        jq -e '.status=="blocked"' <<<"$out" >/dev/null
        grep -q 'OldName' "$work/a.txt"
    }
    run_test "patch that does not apply cleanly is blocked" test_patch_does_not_apply_blocked

    test_patch_introspect_lists_mode() {
        "$BASH_BIN" "$SCRIPT" --introspect \
            | jq -e '(.modes|map(.name)) as $m | ($m|index("patch"))' >/dev/null
    }
    run_test "--introspect lists patch mode" test_patch_introspect_lists_mode
else
    skip_test "patch dry-run plans changed file and does not modify" "requires git, jq"
    skip_test "patch apply modifies file and returns applied JSON" "requires git, jq"
    skip_test "patch reads diff from stdin and applies" "requires git, jq"
    skip_test "patch with .git path is blocked" "requires git, jq"
    skip_test "patch targeting .env is blocked" "requires git, jq"
    skip_test "patch that does not apply cleanly is blocked" "requires git, jq"
    skip_test "--introspect lists patch mode" "requires git, jq"
fi

printf '\n=== Results ===\n'
printf '  Passed: %d  Failed: %d  Skipped: %d\n' "$PASS" "$FAIL" "$SKIP"

if ((FAIL == 0)); then
    printf '\033[0;32mPASSED\033[0m\n'
else
    printf '\033[0;31mFAILED\033[0m\n'
    exit 1
fi
