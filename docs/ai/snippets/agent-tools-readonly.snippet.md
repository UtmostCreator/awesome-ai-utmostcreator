    # --- shipped CLI tool access (shared snippet: agent-tools-readonly) ---
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
    'shellcheck *': allow
    # --- repomix freshness check ---
    'bash scripts/ai/repomix-freshness.sh *': allow
    'bash scripts/ai/repomix-ensure-fresh.sh *': ask
