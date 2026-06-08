    # --- shipped CLI tool access (shared snippet: agent-tools-execute) ---
    # --- read-only ai.php subcommands (advisory; write only to docs/ai/generated) ---
    'php tools/ai/ai.php placeholders*': allow
    'php tools/ai/ai.php verify*': allow
    'php tools/ai/ai.php preflight*': allow
    'php tools/ai/ai.php list': allow
    'php tools/ai/ai.php next*': allow
    'php tools/ai/ai.php freshness*': allow
    'php tools/ai/ai.php packs*': allow
    'php tools/ai/ai.php env-check*': allow
    'php tools/ai/ai.php install-docs --check': allow
    'scc *': allow
    'tokei *': allow
    'ast-grep *': allow
    'bat *': allow
    'fx *': allow
    'glow *': allow
    'difft *': allow
    'delta *': allow
    'lychee *': allow
    'actionlint*': allow
    'shfmt -d *': allow
    'semgrep *': allow
    'repomix *': ask
    'files-to-prompt *': ask
    'code2prompt *': ask
    # --- repomix freshness check ---
    'bash scripts/ai/repomix-freshness.sh *': allow
    'bash scripts/ai/repomix-ensure-fresh.sh *': ask
