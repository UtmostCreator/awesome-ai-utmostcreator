# Good / Bad: Task Running

## Best (repository wrapper)

```bash
# Discover all available project tasks
bash scripts/ai/ai-task.sh list

# JSON output for parsing
bash scripts/ai/ai-task.sh json

# Discover available tools and their locations
bash scripts/ai/repo-tool-inventory.sh
```

## Good

```bash
just --list
jq '.scripts' package.json
composer run-script --list
just verify
```

## Bad

```bash
npm test
make test
composer test
```

without checking available project tasks.

Why bad:

- command may not exist
- wrong package manager
- misses project-specific verify flow
