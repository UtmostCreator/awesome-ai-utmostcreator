#!/usr/bin/env bash
set -euo pipefail

# ship-audit.sh — detect forbidden install-pack targets before packaging.
# Caveat: this is not a release gate yet. Some generated directories, especially
# docs/ai/generated/, are intentionally gitignored/untracked, so this audit only
# proves forbidden paths are absent from the installer pack registry.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/ai/common.sh
source "$SCRIPT_DIR/common.sh"

usage() {
    printf '%s\n' \
        'Usage: scripts/ai/ship-audit.sh [root]' \
        '' \
        'Checks installer pack targets for forbidden ship/report paths listed in' \
        'scripts/ai/internal/config/exclude-dirs.txt.'
}

case "${1:-}" in
--help | -h)
    usage
    exit 0
    ;;
esac

root="${1:-.}"
root="$(cd "$root" && pwd)"
config="$SCRIPT_DIR/internal/config/exclude-dirs.txt"
forbidden=()
ai_load_config_list forbidden "$config" docs/ai/generated docs/tickets .ai-logs vendor dist node_modules

require_bins php

mapfile -t pack_targets < <(
    php -r '
        $root = $argv[1];
        require $root . "/tools/ai/install/packs.php";
        foreach (aiInstallerPackRegistry() as $items) {
            foreach ($items as $item) {
                $target = $item["target"] ?? "";
                if (is_string($target) && $target !== "") {
                    echo rtrim($target, "/") . PHP_EOL;
                }
            }
        }
    ' "$root"
)

violations=()
for path in "${forbidden[@]}"; do
    path="${path%/}"
    [[ -n "$path" ]] || continue
    for target in "${pack_targets[@]}"; do
        [[ "$target" == "$path" || "$target" == "$path/"* ]] || continue
        violations+=("$path -> $target")
    done
done

if ((${#violations[@]} > 0)); then
    printf 'ERROR: forbidden installer pack targets detected:\n' >&2
    printf ' - %s\n' "${violations[@]}" >&2
    exit 1
fi

printf 'OK: no forbidden installer pack targets detected\n'
