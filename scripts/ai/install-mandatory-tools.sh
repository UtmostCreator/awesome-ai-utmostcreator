#!/usr/bin/env bash
set -euo pipefail

# Installs mandatory CLI tools used by the repository's AI scripts.

DRY_RUN=0

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
    need_cmd winget || {
        printf 'Error: winget is required on Windows.\n' >&2
        exit 1
    }

    run_cmd winget install --id Git.Git -e --accept-source-agreements --accept-package-agreements || true
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
    need_cmd brew || {
        printf 'Error: Homebrew is required on macOS.\n' >&2
        exit 1
    }

    run_cmd brew update
    run_cmd brew install git php ripgrep jq scc node
    run_cmd npm install -g repomix
}

install_linux() {
    need_cmd apt-get || {
        printf 'Error: this Linux installer targets Ubuntu/Debian (apt-get).\n' >&2
        exit 1
    }

    run_cmd sudo apt-get update
    run_cmd sudo apt-get install -y git php-cli ripgrep jq nodejs npm

    if run_cmd sudo apt-get install -y scc; then
        :
    elif need_cmd go; then
        run_cmd go install github.com/boyter/scc/v3@latest
    else
        printf 'Warning: failed to install scc via apt and Go is not available.\n' >&2
    fi

    run_cmd npm install -g repomix
}

verify_tools() {
    local missing=()
    local required=(bash git php rg jq scc repomix)

    for tool in "${required[@]}"; do
        need_cmd "$tool" || missing+=("$tool")
    done

    if ((${#missing[@]} > 0)); then
        printf 'Missing required tools: %s\n' "${missing[*]}" >&2
        return 1
    fi

    printf 'All mandatory tools are installed: %s\n' "${required[*]}"
}

OS_KIND="$(detect_os)"
printf 'Detected OS: %s\n' "$OS_KIND"

case "$OS_KIND" in
windows) install_windows ;;
macos) install_macos ;;
linux) install_linux ;;
*)
    printf 'Error: unsupported OS for this installer.\n' >&2
    exit 1
    ;;
esac

verify_tools
