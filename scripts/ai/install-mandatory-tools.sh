#!/usr/bin/env bash
set -euo pipefail

# Installs mandatory CLI tools used by the repository's AI scripts.

DRY_RUN=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
VSCODE_EXTENSIONS_DOC="$REPO_ROOT/docs/vscode-extensions.md"

if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
fi

run_cmd() {
    if [[ "$DRY_RUN" -eq 1 ]]; then
        printf '[dry-run] %s\n' "$*"
        return 0
    fi
    "$@"
}

need_cmd() {
    command -v "$1" >/dev/null 2>&1
}

# Require a package manager to be present. In --dry-run mode this never aborts:
# it prints a dry-run marker describing what would be required, so the planned
# steps can still be previewed on hosts that lack the manager (e.g. NixOS).
require_pkg_manager() {
    local manager="$1" platform="$2"
    if need_cmd "$manager"; then
        return 0
    fi
    if [[ "$DRY_RUN" -eq 1 ]]; then
        printf '[dry-run] would require %s for %s installs\n' "$manager" "$platform"
        return 1
    fi
    printf 'Error: %s is required on %s.\n' "$manager" "$platform" >&2
    exit 1
}

detect_os() {
    local uname_out
    uname_out="$(uname -s 2>/dev/null || true)"
    case "$uname_out" in
    Darwin*)
        printf 'macos\n'
        ;;
    Linux*)
        printf 'linux\n'
        ;;
    MINGW* | MSYS* | CYGWIN*)
        printf 'windows\n'
        ;;
    *)
        if [[ "${OS:-}" == "Windows_NT" ]]; then
            printf 'windows\n'
        else
            printf 'unknown\n'
        fi
        ;;
    esac
}

install_windows() {
    require_pkg_manager winget Windows || return 0

    run_cmd winget install --id Git.Git -e --accept-source-agreements --accept-package-agreements || true
    run_cmd winget install --id Microsoft.VisualStudioCode -e --accept-source-agreements --accept-package-agreements || true
    run_cmd winget install --id PHP.PHP.8.3 -e --accept-source-agreements --accept-package-agreements || true
    run_cmd winget install --id BurntSushi.ripgrep.MSVC -e --accept-source-agreements --accept-package-agreements || true
    run_cmd winget install --id jqlang.jq -e --accept-source-agreements --accept-package-agreements || true
    run_cmd winget install --id BenBoyter.scc -e --accept-source-agreements --accept-package-agreements || true
    run_cmd winget install --id OpenJS.NodeJS.LTS -e --accept-source-agreements --accept-package-agreements || true

    if need_cmd npm; then
        run_cmd npm install -g repomix
    else
        printf 'Warning: npm not found after Node.js install; install repomix manually.\n' >&2
    fi
}

install_macos() {
    require_pkg_manager brew macOS || return 0

    run_cmd brew update
    run_cmd brew install --cask visual-studio-code || true
    run_cmd brew install git php ripgrep jq scc node
    run_cmd npm install -g repomix
}

install_linux() {
    require_pkg_manager apt-get 'Ubuntu/Debian Linux' || return 0

    run_cmd sudo apt-get update
    run_cmd sudo apt-get install -y code || printf 'Warning: failed to install VS Code via apt; install it manually before extension install.\n' >&2
    run_cmd sudo apt-get install -y git php-cli ripgrep jq nodejs npm fd-find shellcheck

    if run_cmd sudo apt-get install -y scc; then
        :
    elif need_cmd go; then
        run_cmd go install github.com/boyter/scc/v3@latest
    else
        printf 'Warning: failed to install scc via apt and Go is not available.\n' >&2
    fi

    # gitleaks (required by advisor + secret-scan strict mode)
    if ! need_cmd gitleaks; then
        if run_cmd bash -c "mkdir -p \"$HOME/.local/bin\" && curl -L --fail https://github.com/gitleaks/gitleaks/releases/latest/download/gitleaks_linux_x64.tar.gz -o \"${TMPDIR:-/tmp}/gitleaks.tar.gz\" && tar -xzf \"${TMPDIR:-/tmp}/gitleaks.tar.gz\" -C \"${TMPDIR:-/tmp}\" gitleaks && install -m 0755 \"${TMPDIR:-/tmp}/gitleaks\" \"$HOME/.local/bin/gitleaks\""; then
            :
        else
            printf 'Warning: failed to install gitleaks from release archive.\n' >&2
        fi
    fi

    # ast-grep via npm (user-local prefix to avoid sudo)
    if ! need_cmd ast-grep; then
        run_cmd bash -c "mkdir -p \"$HOME/.local\" && npm config set prefix \"$HOME/.local\" && npm install -g @ast-grep/cli"
    fi

    # fd alias if package shipped as fdfind
    if ! need_cmd fd && need_cmd fdfind; then
        run_cmd bash -c "mkdir -p \"$HOME/.local/bin\" && ln -sf \"$(command -v fdfind)\" \"$HOME/.local/bin/fd\""
    fi

    run_cmd npm install -g repomix
}

install_vscode_extensions() {
    if [[ ! -f "$VSCODE_EXTENSIONS_DOC" ]]; then
        printf 'Warning: VS Code extension source not found: %s\n' "$VSCODE_EXTENSIONS_DOC" >&2
        return 0
    fi

    if ! need_cmd code && [[ "$DRY_RUN" -eq 0 ]]; then
        printf 'Warning: code CLI not found; skipping VS Code extension install.\n' >&2
        return 0
    fi

    local extension count=0
    while IFS= read -r extension; do
        [[ -n "$extension" ]] || continue
        run_cmd code --install-extension "$extension" || true
        count=$((count + 1))
    done < <(sed -n 's/^code --install-extension //p' "$VSCODE_EXTENSIONS_DOC")

    if [[ "$count" -eq 0 ]]; then
        printf 'Warning: no VS Code extensions found in %s\n' "$VSCODE_EXTENSIONS_DOC" >&2
    else
        printf 'VS Code extensions queued from %s: %d\n' "$VSCODE_EXTENSIONS_DOC" "$count"
    fi
}

verify_tools() {
    local missing=()
    local required=(bash git php rg jq scc repomix)

    for tool in "${required[@]}"; do
        need_cmd "$tool" || missing+=("$tool")
    done

    if ((${#missing[@]} > 0)); then
        if [[ "$DRY_RUN" -eq 1 ]]; then
            printf '[dry-run] would verify tools; currently missing: %s\n' "${missing[*]}"
            return 0
        fi
        printf 'Missing required tools: %s\n' "${missing[*]}" >&2
        return 1
    fi

    printf 'All mandatory tools are installed: %s\n' "${required[*]}"
}

OS_KIND="$(detect_os)"
printf 'Detected OS: %s\n' "$OS_KIND"
printf 'Package manager hints include brew/apt/winget by platform.\n'

case "$OS_KIND" in
windows) install_windows ;;
macos) install_macos ;;
linux) install_linux ;;
*)
    printf 'Error: unsupported OS for this installer.\n' >&2
    exit 1
    ;;
esac

install_vscode_extensions
verify_tools
