# shellcheck shell=bash
# shellcheck disable=SC2154,SC2034  # cross-module globals via dynamic scope
# ai-diff-context/90-main.sh — subcommand dispatch (ai_diff_context_main).
#
# Sourced by scripts/ai/ai-diff-context.sh (thin loader). Not an entrypoint.
# Runs inside a function so the original top-level dispatch/exit flow is
# preserved exactly. Behavior is byte-for-byte identical to the monolith.

ai_diff_context_main() {
    agent_session_init "ai-diff-context"
    require_bins jq

    cmd="${1:-}"
    [[ -n "$cmd" ]] || {
        usage
        exit 1
    }
    shift || true

    case "$cmd" in
    since) cmd_since "$@" ;;
    unstaged) cmd_unstaged "$@" ;;
    pr) cmd_pr "$@" ;;
    recent) cmd_recent "$@" ;;
    touched) cmd_touched "$@" ;;
    --help | -h) usage ;;
    *)
        usage
        die "unknown command: $cmd"
        ;;
    esac
}
