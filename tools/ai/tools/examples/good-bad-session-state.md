# Good / Bad: Session State

## Best (repository wrapper)

```bash
# Create a checkpoint before risky work
bash scripts/ai/session-checkpoint.sh create "before refactor"

# List available checkpoints
bash scripts/ai/session-checkpoint.sh list

# Restore a checkpoint (requires approval)
bash scripts/ai/session-checkpoint.sh restore latest

# File-watch loop for continuous verification during edits
bash scripts/ai/watch-loop.sh "vendor/bin/phpunit --filter=SpecificTest" app/Services
```

## Good

```bash
git stash push -m "pre-refactor"
git status --short
git diff --stat
```

## Bad

```bash
# No state saved before editing
vim file.php
# realize mistake
# cannot recover
```

Why bad:

- no recovery point
- no way to compare before/after
- work can be lost on a bad edit
