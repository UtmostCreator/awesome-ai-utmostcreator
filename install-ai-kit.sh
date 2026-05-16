#!/usr/bin/env bash
# =============================================================================
# AI Workflow Kit — One-Tap Installer
# https://github.com/UtmostCreator/awesome-ai-utmostcreator
#
# Usage:
#   bash install-ai-kit.sh /path/to/your-project
#   bash install-ai-kit.sh /path/to/your-project "my-project-name"
#
# Examples:
#   bash install-ai-kit.sh /Users/you/Herd/headless-cms
#   bash install-ai-kit.sh /Users/you/Herd/headless-cms headless-cms
# =============================================================================
set -euo pipefail

# ── Arguments ────────────────────────────────────────────────────────────────
TARGET="${1:-}"
PROJECT_NAME="${2:-}"

if [[ -z "$TARGET" ]]; then
    echo "Usage: bash install-ai-kit.sh /path/to/project [project-name]"
    echo ""
    echo "Examples:"
    echo "  bash install-ai-kit.sh /Users/you/Herd/headless-cms"
    echo "  bash install-ai-kit.sh /Users/you/Herd/headless-cms headless-cms"
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

# ── Preflight ────────────────────────────────────────────────────────────────
echo ""
echo "==> Running preflight check..."
php tools/ai/ai.php preflight || true

# ── Install ───────────────────────────────────────────────────────────────────
echo ""
echo "==> Installing AI workflow kit"
echo "    Target:  $TARGET"
echo "    Project: $PROJECT_NAME"
echo "    Profile: full-governance  |  Runtime: both (Copilot + OpenCode)"
echo ""

php tools/ai/install-ai-kit.php \
    --target "$TARGET" \
    --profile full-governance \
    --runtime both \
    --project-name "$PROJECT_NAME" \
    --backup \
    --verify-after \
    --non-interactive

# ── Validate install surface ──────────────────────────────────────────────────
echo ""
echo "==> Validating install surface..."
php tools/ai/validate-install-surface.php --strict --target "$TARGET" 2>/dev/null || \
    php tools/ai/validate-install-surface.php --target "$TARGET"

# ── Validate config and catalog ───────────────────────────────────────────────
echo ""
echo "==> Validating AI config and catalog..."
php tools/ai/validate-ai-config.php --target "$TARGET" 2>/dev/null || \
    php tools/ai/validate-ai-config.php
php tools/ai/validate-ai-catalog.php --target "$TARGET" 2>/dev/null || \
    php tools/ai/validate-ai-catalog.php

# ── Placeholder audit ─────────────────────────────────────────────────────────
echo ""
echo "==> Auditing placeholders in target..."
php tools/ai/ai.php placeholders --target "$TARGET" 2>/dev/null || \
    php tools/ai/ai.php placeholders || true

# ── Repomix context bundle ────────────────────────────────────────────────────
echo ""
if command -v repomix >/dev/null 2>&1 && command -v rg >/dev/null 2>&1 && command -v scc >/dev/null 2>&1; then
    echo "==> Generating repomix context bundle (full params)..."
    SECRETS_SCAN=0 bash "$SCRIPT_DIR/scripts/ai/run-repomix-context.sh" "$TARGET" \
        --depth 3 --top 0 --min-code 0 --min-files 0 2>/dev/null || true
elif command -v repomix >/dev/null 2>&1 && command -v rg >/dev/null 2>&1; then
    echo "==> Generating repomix context bundle (scc not found, skipping complexity routing)..."
    SECRETS_SCAN=0 bash "$SCRIPT_DIR/scripts/ai/run-repomix-context.sh" "$TARGET" \
        --depth 3 --top 0 --min-code 0 --min-files 0 2>/dev/null || true
else
    echo "==> Skipping repomix bundle — missing: $(command -v repomix >/dev/null 2>&1 || echo 'repomix ') $(command -v rg >/dev/null 2>&1 || echo 'rg')"
    echo "    To enable: npm install -g repomix && brew install ripgrep scc"
fi

# ── Advisor (AI workflow readiness score) ─────────────────────────────────────
echo ""
if [[ -f "$TARGET/tools/ai/advisor/scorer.php" ]]; then
    echo "==> Running AI workflow advisor..."
    # Advisor uses the repo root it's installed in; run from target with its own PHP files
    (cd "$TARGET" && php "$SCRIPT_DIR/tools/ai/ai.php" advisor --all 2>/dev/null) || \
    (cd "$TARGET" && php "$TARGET/tools/ai/ai.php" advisor --all 2>/dev/null) || \
    echo "    Advisor skipped (run manually: cd $TARGET && php tools/ai/ai.php advisor --all)"
else
    echo "==> Advisor not installed in target (advisor-pack not in profile or tools/ai/ not shipped)"
fi

# ── Repo tool inventory ────────────────────────────────────────────────────────
echo ""
echo "==> Generating tool inventory for target..."
(cd "$TARGET" && bash "$SCRIPT_DIR/scripts/ai/repo-tool-inventory.sh" 2>/dev/null) || true

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
echo "  2. Re-audit after filling:"
echo "       cd $SCRIPT_DIR && php tools/ai/ai.php placeholders --fail --target $TARGET"
echo ""
echo "  3. Wire git hooks in target (optional):"
echo "       cd $TARGET && lefthook install"
echo ""
echo "  4. Full post-install checklist:"
echo "       cat $TARGET/docs/ai/POST-INSTALL.md"
echo ""
