    # --- shipped CLI tool access (shared snippet: agent-tools-execute) ---
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
