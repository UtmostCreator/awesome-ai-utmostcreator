# Good / Bad: Shell Scripts

## Best (repository wrapper)

```bash
# Source common.sh for Bash 4+ enforcement, logging, and shared utilities
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

# Use repository-standard patterns
common_require_core    # ensures bash 4+, jq, git are available
common_log "INFO" "Starting operation"
common_die "something went wrong"
```

## Good

```bash
bash -n scripts/install.sh
shellcheck scripts/install.sh
shfmt -d scripts/install.sh
bats tests/install.bats
```

## Bad

```bash
bash scripts/install.sh
sed -i 's/foo/bar/g' scripts/*.sh
```

Why bad:

- runs before syntax/lint validation
- broad edits can break quoting and portability
