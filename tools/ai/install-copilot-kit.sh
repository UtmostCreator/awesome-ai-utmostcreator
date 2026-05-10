#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

usage() {
    cat <<'EOF'
Usage:
  tools/ai/install-copilot-kit.sh [options]

Compatibility wrapper:
  This command forwards to tools/ai/install-ai-kit.sh with a
  GitHub Copilot runtime profile.

Examples:
  tools/ai/install-copilot-kit.sh --target ../my-repo
  tools/ai/install-copilot-kit.sh --profile copilot-guarded --force
EOF
}

PROFILE='copilot'
ARGS=()

while (($# > 0)); do
    case "$1" in
    --help | -h)
        usage
        bash "$SCRIPT_DIR/install-ai-kit.sh" --help
        exit 0
        ;;
    --profile)
        PROFILE="$2"
        shift 2
        ;;
    --profile=*)
        PROFILE="${1#*=}"
        shift
        ;;
    *)
        ARGS+=("$1")
        shift
        ;;
    esac
done

case "$PROFILE" in
minimal)
    TARGET_PROFILE='minimal'
    ;;
copilot)
    TARGET_PROFILE='copilot'
    ;;
copilot-guarded)
    TARGET_PROFILE='guarded'
    ;;
*)
    printf 'Error: unsupported profile %s\n' "$PROFILE" >&2
    exit 1
    ;;
esac

exec bash "$SCRIPT_DIR/install-ai-kit.sh" --runtime github-copilot --profile "$TARGET_PROFILE" "${ARGS[@]}"
