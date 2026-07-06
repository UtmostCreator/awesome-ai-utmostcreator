#!/usr/bin/env bash
# =============================================================================
# AI Workflow Kit — One-Tap Installer
# https://github.com/UtmostCreator/awesome-ai-utmostcreator
#
# Usage:
#   bash install-ai-kit.sh /path/to/your-project
#   bash install-ai-kit.sh /path/to/your-project "my-project-name"
#   bash install-ai-kit.sh /path/to/your-project "my-project-name" --force
#   bash install-ai-kit.sh /path/to/your-project --force
#   bash install-ai-kit.sh /path/to/your-project --strict-placeholders
#
# Options:
#   --force   Overwrite existing files. Use on reinstalls to push updated
#             templates to a target that was previously installed.
#             Core base policy files are protected and will NOT be overwritten
#             unless you also pass --allow-core-overwrite to the PHP installer
#             directly.
#   --strict-placeholders  Fail install when required placeholders remain
#             unresolved (disables default --allow-placeholders passthrough).
#
# Examples:
#   bash install-ai-kit.sh /Users/you/Herd/project-name
#   bash install-ai-kit.sh /Users/you/Herd/project-name project-name
#   bash install-ai-kit.sh /Users/you/Herd/project-name --force
#   bash install-ai-kit.sh /Users/you/Herd/project-name project-name --force
#   bash install-ai-kit.sh /Users/you/Herd/project-name --strict-placeholders
# =============================================================================
set -euo pipefail

# ── Arguments ────────────────────────────────────────────────────────────────
TARGET=""
PROJECT_NAME=""
FORCE_FLAG=""
ALLOW_PLACEHOLDERS_FLAG="--allow-placeholders"
# Defaults preserve historical behavior (full dual-runtime governance install) when no
# --profile/--runtime is passed, while the single-runtime wrapper scripts
# (install-opencode-kit.sh / install-copilot-kit.sh / install-claude-kit.sh) can now
# actually scope the install by forwarding these flags. See
# docs/tickets/arch-todo-install-verification-fixes-20260706-011500.
PROFILE="full-governance"
RUNTIME="both"

# Parse arguments. Supports both the positional form
#   install-ai-kit.sh /path/to/project [project-name] [--force]
# and the flag form used by the wrapper scripts
#   install-ai-kit.sh --runtime opencode --profile opencode --target /path
while (($# > 0)); do
    case "$1" in
    --force) FORCE_FLAG="--force"; shift ;;
    --strict-placeholders) ALLOW_PLACEHOLDERS_FLAG=""; shift ;;
    --target) TARGET="${2:-}"; shift 2 ;;
    --target=*) TARGET="${1#*=}"; shift ;;
    --profile) PROFILE="${2:-}"; shift 2 ;;
    --profile=*) PROFILE="${1#*=}"; shift ;;
    --runtime) RUNTIME="${2:-}"; shift 2 ;;
    --runtime=*) RUNTIME="${1#*=}"; shift ;;
    --project-name) PROJECT_NAME="${2:-}"; shift 2 ;;
    --project-name=*) PROJECT_NAME="${1#*=}"; shift ;;
    --*) shift ;; # ignore unknown flags for forward-compatibility
    *)
        # First bare positional is the target path; second is the project name.
        if [[ -z "$TARGET" ]]; then
            TARGET="$1"
        else
            PROJECT_NAME="$1"
        fi
        shift
        ;;
    esac
done

if [[ -z "$TARGET" ]]; then
    echo "Usage: bash install-ai-kit.sh /path/to/project [project-name] [--force] [--strict-placeholders]"
    echo "       bash install-ai-kit.sh --target /path/to/project [--profile <name>] [--runtime <name>]"
    echo ""
    echo "Options:"
    echo "  --target <dir>   Target project root (positional first arg also accepted)."
    echo "  --profile <name> Install profile (default: full-governance)."
    echo "  --runtime <name> Runtime override: github-copilot|opencode|claude-code|both (default: both)."
    echo "  --project-name <n> Override inferred project name."
    echo "  --force   Overwrite existing managed files in the target."
    echo "            Use on reinstalls to pick up updated templates."
    echo "  --strict-placeholders"
    echo "            Fail if required placeholders remain unresolved."
    echo "            Default behavior allows placeholder-first bootstrap installs."
    echo ""
    echo "Examples:"
    echo "  bash install-ai-kit.sh /Users/you/Herd/project-name"
    echo "  bash install-ai-kit.sh /Users/you/Herd/project-name project-name"
    echo "  bash install-ai-kit.sh /Users/you/Herd/project-name --force"
    echo "  bash install-ai-kit.sh /Users/you/Herd/project-name project-name --force"
    echo "  bash install-ai-kit.sh /Users/you/Herd/project-name --strict-placeholders"
    echo "  bash install-ai-kit.sh --target /Users/you/Herd/project-name --runtime opencode --profile opencode"
    exit 1
fi

if [[ ! -d "$TARGET" ]]; then
    echo "ERROR: Target directory does not exist: $TARGET"
    exit 1
fi

TARGET="$(cd "$TARGET" && pwd)"
PROJECT_NAME="${PROJECT_NAME:-$(basename "$TARGET")}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Prerequisites check ───────────────────────────────────────────────────────
echo ""
echo "==> Checking prerequisites..."
MISSING=()
for bin in php composer git jq; do
    command -v "$bin" >/dev/null 2>&1 || MISSING+=("$bin")
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
    echo "ERROR: Missing required tools: ${MISSING[*]}"
    echo "       Run: bash scripts/ai/install-mandatory-tools.sh"
    exit 1
fi
php -r 'if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "ERROR: PHP 8.2+ required (current: " . PHP_VERSION . ")\n       On macOS with Herd: already satisfied. Otherwise: brew install php\n"); exit(1); }'

echo "    php $(php -r 'echo PHP_VERSION;')"
echo "    $(composer --version)"
echo "    $(git --version)"
echo "    $(jq --version)"

echo ""
echo "==> Validating source install surface before writing target..."
php tools/ai/validate-install-surface.php --strict

# ── Composer (if not already installed) ──────────────────────────────────────
if [[ ! -d "$SCRIPT_DIR/vendor" ]]; then
    echo ""
    echo "==> Installing Composer dependencies..."
    cd "$SCRIPT_DIR"
    composer install --no-interaction --quiet
fi

cd "$SCRIPT_DIR"

check_target_path() {
    local relative_path="$1"

    if [[ -e "$TARGET/$relative_path" ]]; then
        echo "    OK  $relative_path"
        return 0
    fi

    echo "    MISS $relative_path"
    return 1
}

# ── Preflight ────────────────────────────────────────────────────────────────
echo ""
echo "==> Running preflight check..."
php tools/ai/ai.php preflight || true

# ── Install ───────────────────────────────────────────────────────────────────
echo ""
echo "==> Installing AI workflow kit"
echo "    Target:  $TARGET"
echo "    Project: $PROJECT_NAME"
echo "    Profile: $PROFILE  |  Runtime: $RUNTIME"
echo ""

php tools/ai/install-ai-kit.php \
    --target "$TARGET" \
    --profile "$PROFILE" \
    --runtime "$RUNTIME" \
    --project-name "$PROJECT_NAME" \
    --backup \
    --verify-after \
    --non-interactive \
    ${ALLOW_PLACEHOLDERS_FLAG} \
    ${FORCE_FLAG}

# ── Validate install surface ──────────────────────────────────────────────────
echo ""
echo "==> Validating install surface..."
(cd "$TARGET" && php tools/ai/validate-install-surface.php --strict)

# ── Validate config and catalog ───────────────────────────────────────────────
echo ""
echo "==> Validating AI config and catalog..."
(cd "$TARGET" && php tools/ai/validate-ai-config.php)
(cd "$TARGET" && php tools/ai/validate-ai-catalog.php)

# ── Checking installed target surface (paths present) ────────────────────────
echo ""
echo "==> Checking installed target surface..."
target_failures=0
for relative_path in \
    AGENTS.md \
    docs/ai/project-context.md \
    docs/ai/POST-INSTALL.md \
    scripts/ai/ai-doc-check.sh \
    scripts/ai/ai-verify.sh \
    .github/copilot-instructions.md \
    .github/instructions/frontend.instructions.md \
    .github/instructions/testing.instructions.md \
    opencode.jsonc; do
    check_target_path "$relative_path" || target_failures=$((target_failures + 1))
done
if [[ $target_failures -gt 0 ]]; then
    echo "    WARN: $target_failures expected install surface path(s) missing"
fi

echo ""
echo "==> Running target-local documentation checks..."
if [[ -f "$TARGET/scripts/ai/ai-doc-check.sh" ]]; then
    (
        cd "$TARGET" &&
            bash scripts/ai/ai-doc-check.sh markdownlint docs/ai .github AGENTS.md .github/copilot-instructions.md &&
            bash scripts/ai/ai-doc-check.sh links docs/ai .github AGENTS.md .github/copilot-instructions.md
    ) || true
else
    echo "    Skipped: scripts/ai/ai-doc-check.sh not installed in target"
fi

echo ""
echo "==> Skipping broad target-local verification smoke check..."
echo "    Install-safe validators already ran above."
echo "    Run full repo verification manually after install if needed:"
echo "      cd $TARGET && AI_ALLOW_NO_TIMEOUT=1 VERIFY_SECRETS=0 bash scripts/ai/ai-verify.sh ."

# ── Repomix context bundle ────────────────────────────────────────────────────
echo ""
if command -v repomix >/dev/null 2>&1 && command -v rg >/dev/null 2>&1 && command -v scc >/dev/null 2>&1; then
    echo "==> Generating repomix context bundle (depth=2, top=0, min-code=25, min-files=1, context-window=1000000)..."
    (
        cd "$TARGET" &&
            SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh . \
                --depth 2 --top 0 --min-code 25 --min-files 1 \
                --context-window 1000000 --reserved-output 25000 --instruction-overhead 30000 --safety-factor 0.8
    ) 2>/dev/null || true
else
    echo "==> Skipping repomix bundle — missing: $(command -v repomix >/dev/null 2>&1 || echo 'repomix ')$(command -v rg >/dev/null 2>&1 || echo 'rg ')$(command -v scc >/dev/null 2>&1 || echo 'scc')"
    echo "    To enable: npm install -g repomix && brew install ripgrep scc"
fi

# ── Advisor (AI workflow readiness score) ─────────────────────────────────────
echo ""
if [[ -f "$TARGET/tools/ai/advisor/scorer.php" ]]; then
    echo "==> Running AI workflow advisor..."
    (cd "$TARGET" && php tools/ai/ai.php advisor --all 2>/dev/null) ||
        echo "    Advisor skipped (run manually: cd $TARGET && php tools/ai/ai.php advisor --all)"
else
    echo "==> Advisor not installed in target (advisor-pack not in profile or tools/ai/ not shipped)"
fi

# ── Repo tool inventory ────────────────────────────────────────────────────────
echo ""
echo "==> Generating tool inventory for target..."
(cd "$TARGET" && bash scripts/ai/repo-tool-inventory.sh 2>/dev/null) || true

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo "================================================================="
echo "  Install complete"
echo "================================================================="
echo ""
echo "  Installed into: $TARGET"
echo ""
echo "  Required next steps (manual — required before using AI agents):"
echo ""
echo "  1. Fill placeholders:"
echo "       $TARGET/docs/ai/project-context.md        ← every <PLACEHOLDER>"
echo "       $TARGET/.github/instructions/frontend.instructions.md  ← <FRONTEND_PATH_GLOB>"
echo "       $TARGET/.github/instructions/testing.instructions.md   ← <TEST_PATH_GLOB>"
echo ""
echo "  2. Guided setup helper (recommended):"
echo "       OpenCode command: post-install-setup"
echo "       Workflow / skill name: post-install-setup"
echo ""
echo "  3. Re-audit after filling:"
echo "       rg -n '<[A-Z0-9_]+>' $TARGET/AGENTS.md $TARGET/docs/ai $TARGET/.github $TARGET/.opencode"
echo ""
echo "  4. Wire git hooks in target (optional):"
echo "       cd $TARGET && lefthook install"
echo ""
echo "  5. Full post-install checklist:"
echo "       cat $TARGET/docs/ai/POST-INSTALL.md"
echo ""
echo "  Kit descriptors (manifest.json, catalog.json, etc.) install under .ai/ so they never"
echo "  collide with your project's own root files. To inspect or copy one out to root:"
echo "       cd $TARGET && php tools/ai/ai.php descriptors --list"
echo "       cd $TARGET && php tools/ai/ai.php descriptors --copy-out --name manifest.json --apply"
echo ""
echo "  Reinstall tip: if files were skipped (skip_identical_existing / skip_existing_unmanaged)"
echo "  and you want to push updated templates, rerun with --force:"
echo "       bash install-ai-kit.sh $TARGET $PROJECT_NAME --force"
echo ""
echo "  Manual repomix + advisor run (already run above when tools are present):"
echo "       cd $TARGET && SECRETS_SCAN=0 bash scripts/ai/run-repomix-context.sh . --depth 2 --top 0 --min-code 25 --min-files 1 --context-window 1000000 --reserved-output 25000 --instruction-overhead 30000 --safety-factor 0.8"
echo "       cd $TARGET && php tools/ai/ai.php advisor --all"
echo ""
