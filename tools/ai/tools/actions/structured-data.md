# Structured Data

Use for JSON, YAML, lockfiles, workflow files, config files, and generated manifests.

---

## Preferred Commands

Instead of grep in JSON use:

```bash
jq '.scripts' package.json
jq -r '.name' package.json
jq empty composer.json
```

Instead of grep in YAML use:

```bash
yq '.jobs | keys' .github/workflows/*.yml
yq -r '.services.app.image' docker-compose.yml
```

Instead of grep in lockfiles use:

```bash
jq '.packages[] | select(.name=="laravel/framework") | .version' composer.lock
```

---

## Use When

- reading `package.json`
- reading `composer.json` / `composer.lock`
- reading GitHub Actions YAML
- reading Docker Compose YAML
- validating JSON output

---

## Avoid

```bash
grep '"scripts"' package.json
grep 'uses:' .github/workflows/*.yml
```

Example: [`../examples/good-bad-structured-data.md`](../examples/good-bad-structured-data.md)
