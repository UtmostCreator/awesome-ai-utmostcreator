#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ERRORS=0

add_winget_paths() {
    local user_name="${USER:-${USERNAME:-}}"
    local base="/c/Users/${user_name}/AppData/Local/Microsoft/WinGet/Packages"
    [[ -d "$base" ]] || return 0
    local dir
    while IFS= read -r dir; do
        case ":$PATH:" in
        *":$dir:"*) ;;
        *) PATH="$PATH:$dir" ;;
        esac
    done < <(find "$base" -maxdepth 3 -type f -name '*.exe' -printf '%h\n' 2>/dev/null | sort -u)
}

ok() { printf "[OK] %s\n" "$1"; }
warn() { printf "[WARN] %s\n" "$1"; }
fail() {
    printf "[ERROR] %s\n" "$1"
    ERRORS=1
}

check_required_bin() {
    local name="$1"
    if command -v "$name" >/dev/null 2>&1; then
        ok "binary '$name' found"
    else
        fail "binary '$name' missing"
    fi
}

check_optional_bin() {
    local name="$1"
    if command -v "$name" >/dev/null 2>&1; then
        ok "binary '$name' found"
    else
        warn "binary '$name' missing"
    fi
}

check_file() {
    local rel="$1"
    if [[ -f "$ROOT_DIR/$rel" ]]; then
        ok "file '$rel' present"
    else
        fail "file '$rel' missing"
    fi
}

echo "== app-configs doctor =="
add_winget_paths

echo "-- Required binaries --"
for bin in bash git rg php; do
    check_required_bin "$bin"
done

echo "-- Optional binaries --"
for bin in just code repomix scc bats actionlint shellcheck shfmt lychee jq yq; do
    check_optional_bin "$bin"
done

echo "-- Core files --"
for file in \
    README.md \
    AGENTS.md \
    docs/ai/project-context.md \
    docs/ai/workflow.md \
    docs/software-and-cli-tools.md \
    docs/vscode-extensions.md \
    configs/shell/.zshrc \
    .github/copilot-instructions.md \
    justfile \
    scripts/hooks/pre-commit.sh \
    scripts/hooks/commit-msg.sh; do
    check_file "$file"
done

echo "-- AI adapter checks --"
if rg -n '\bgithub\.copilot\b' "$ROOT_DIR/docs/vscode-extensions.md" >/dev/null 2>&1; then
    ok "VS Code Copilot base extension documented"
else
    fail "docs/vscode-extensions.md is missing github.copilot"
fi

if rg -n 'copilot-cli|GitHub Copilot CLI' "$ROOT_DIR/docs/software-and-cli-tools.md" >/dev/null 2>&1; then
    ok "Copilot CLI documented"
else
    fail "docs/software-and-cli-tools.md is missing Copilot CLI guidance"
fi

echo "-- AI workflow validation --"
(
    cd "$ROOT_DIR"
    php tools/ai/validate-ai-config.php
    php tools/ai/validate-ai-catalog.php
    php tools/ai/generate-ai-catalog.php --check
)

echo "-- Secret scanner availability --"
if command -v gitleaks >/dev/null 2>&1; then
    ok "gitleaks found"
elif command -v trufflehog >/dev/null 2>&1; then
    ok "trufflehog found"
else
    warn "no secret scanner found (install gitleaks or trufflehog)"
fi

if [[ "$ERRORS" -ne 0 ]]; then
    exit 1
fi

echo "== doctor finished =="
