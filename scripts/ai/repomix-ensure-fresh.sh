#!/usr/bin/env bash
# Ensure the Repomix context bundle is fresh before an agent relies on it.
#
# This is the single entry point agents should call instead of reading
# .repomix-context bundles directly or repeatedly reasoning about freshness.
# It checks freshness deterministically (via repomix-freshness.sh) and, only
# when explicitly permitted, regenerates a stale/expired/missing bundle.
#
# Behaviour (token-stable, never silent):
#   fresh                -> exit 0, print state, do nothing
#   stale (>= warn days) -> exit 0, recommend regen (regenerate only if asked)
#   expired (> max days) -> regenerate IF permitted, else exit 3 with command
#   missing manifest     -> regenerate IF permitted, else exit 4 with command
#
# Regeneration is:
#   - never silent (always announced)
#   - opt-in only: requires --regen, or REPOMIX_AUTO_REGEN=1, or an interactive
#     yes/no confirmation; in non-interactive mode without opt-in it does NOT
#     prompt and exits with a copy-paste recommendation instead
#   - root-only: always runs against the resolved repository root
#
# The actual regeneration command (run-repomix-context.sh) remains ask-gated by
# repository tool policy; this wrapper does not bypass that gate.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

ROOT="."
REGEN="${REPOMIX_AUTO_REGEN:-0}"     # 1 = allowed to regenerate without prompt
ASSUME_NO="0"

usage() {
    cat <<'EOF'
Usage:
  repomix-ensure-fresh.sh [root] [--regen] [--no-regen]

Options:
  --regen        permit regeneration of stale/expired/missing context
  --no-regen     never regenerate; only report and recommend
  (env) REPOMIX_AUTO_REGEN=1   same as --regen
  (env) REPOMIX_WARN_DAYS / REPOMIX_MAX_DAYS   thresholds (default 2 / 7)

Behaviour:
  - fresh   -> exit 0
  - stale   -> exit 0 (recommend regen; regenerate only if permitted)
  - expired -> regenerate if permitted, else exit 3
  - missing -> regenerate if permitted, else exit 4
  Non-interactive without --regen/REPOMIX_AUTO_REGEN never prompts; it exits
  with a recommended command instead.

Regeneration always runs against the repository root only:
  SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh .
EOF
}

args=()
for arg in "$@"; do
    case "$arg" in
    --help | -h)
        usage
        exit 0
        ;;
    --regen)
        REGEN="1"
        ;;
    --no-regen)
        REGEN="0"
        ASSUME_NO="1"
        ;;
    *)
        args+=("$arg")
        ;;
    esac
done
if [[ ${#args[@]} -gt 0 ]]; then
    ROOT="${args[0]}"
fi

root_abs="$(cd "$ROOT" && pwd)"
freshness_script="$SCRIPT_DIR/repomix-freshness.sh"
runner_script="$SCRIPT_DIR/run-repomix-context.sh"
regen_cmd="SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh ."

[[ -f "$freshness_script" ]] || die "missing freshness checker: $freshness_script"

# Determine freshness state via the dedicated checker (deterministic exit codes).
set +e
freshness_out="$(AI_OUTPUT=text bash "$freshness_script" "$root_abs" 2>&1)"
freshness_code=$?
set -e

state="unknown"
case "$freshness_code" in
0)
    # fresh or stale; distinguish by message
    if printf '%s' "$freshness_out" | head -n1 | grep -qi '^stale'; then
        state="stale"
    else
        state="fresh"
    fi
    ;;
3) state="expired" ;;
4) state="missing" ;;
*) state="unknown" ;;
esac

printf '%s\n' "$freshness_out"

regenerate() {
    section "Regenerating Repomix context (root: $root_abs)"
    [[ -f "$runner_script" ]] || die "missing runner: $runner_script"
    # Root-only: always run against the resolved repo root.
    if ( cd "$root_abs" && SECRETS_SCAN=0 bash "$runner_script" . ); then
        echo "OK: Repomix context regenerated"
        return 0
    fi
    die "Repomix context regeneration failed"
}

want_regen() {
    # Returns 0 if regeneration is permitted now.
    if [[ "$REGEN" == "1" ]]; then
        return 0
    fi
    if [[ "$ASSUME_NO" == "1" ]]; then
        return 1
    fi
    # Interactive prompt only when attached to a TTY; never silent.
    if [[ -t 0 && -t 1 ]]; then
        printf 'Regenerate Repomix context now? [y/N] '
        read -r reply || reply=""
        case "$reply" in
        y | Y | yes | YES) return 0 ;;
        *) return 1 ;;
        esac
    fi
    # Non-interactive and not explicitly permitted: do not prompt, do not regen.
    return 1
}

case "$state" in
fresh)
    exit 0
    ;;
stale)
    # Usable; recommend regen but never force it.
    if want_regen; then
        regenerate
    else
        echo "recommend: $regen_cmd"
    fi
    exit 0
    ;;
expired | missing)
    if want_regen; then
        regenerate
        exit 0
    fi
    echo "recommend: $regen_cmd"
    echo "Repomix context is ${state}; not regenerated (no permission). Provide --regen or run the command above."
    [[ "$state" == "expired" ]] && exit 3
    exit 4
    ;;
*)
    echo "recommend: $regen_cmd"
    exit 1
    ;;
esac
