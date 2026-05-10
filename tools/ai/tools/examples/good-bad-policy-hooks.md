# Good / Bad: Policy Hooks

## Best (repository wrapper)

Policy hooks are automatic — they run via `.github/hooks/tool-policy.json` and write evidence under `.ai-logs/`.

```bash
# pre-tool-use.sh is invoked automatically before command execution
# It checks: allowed commands, denied commands, approval-required commands

# post-tool-use.sh is invoked automatically after command execution
# It logs: command, exit code, duration, evidence path
```

Do not call these scripts directly. They are wired through the tool-policy hook system.

## Good

```bash
# Read-only commands — always allowed
git status --short
rg -n "pattern" app
fd "*.php" app
bash scripts/ai/preview-file.sh path/to/file

# Check what hooks are configured
jq '.' .github/hooks/tool-policy.json
```

## Bad

```bash
# Running mutation commands without going through the hook gate
rm -rf vendor
git push --force
sed -i 's/foo/bar/g' $(find . -type f)
docker system prune
```

Why bad:

- bypasses safety gates
- no audit trail in `.ai-logs/`
- destructive commands without approval check
- no evidence of what changed or why
