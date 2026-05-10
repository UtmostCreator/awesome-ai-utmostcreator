# Approval Required Commands

Never run these without explicit user approval.

---

## Destructive Filesystem

```bash
rm -rf
find . -delete
git clean -fdx
Remove-Item -Recurse -Force
del /s /q
```

Preview first:

```bash
git clean -nd
git status --short
git diff --stat
```

---

## Git History / Publishing

```bash
git reset
git checkout
git switch
git merge
git rebase
git push
git clean -fdx
```

Read-only Git is allowed:

```bash
git status --short
git diff
git log --oneline
git show
git blame
git grep
git ls-files
```

---

## Dependency / Runtime Mutation

```bash
npm install
pnpm install
pnpm add
composer update
composer require
mise install
brew install
cargo install
uv tool install
```

Inspect first:

```bash
jq '.scripts' package.json
jq '.packageManager' package.json
composer validate
mise current
```

---

## Services / Infrastructure

```bash
docker compose up
docker compose down
docker system prune
colima start
sudo
ssh
scp
rsync --delete
```

Inspect first:

```bash
docker compose ps
docker system df
colima status
```

---

## Network Execution

```bash
curl URL | sh
curl URL | bash
wget -O- URL | sh
```

Allowed safer inspection:

```bash
curl -I URL
curl -sS URL | head
```

---

## Database Mutation

Requires explicit approval:

```sql
DELETE
UPDATE
TRUNCATE
DROP
ALTER
```

Inspect first:

```sql
SELECT ...
DESCRIBE ...
SHOW TABLES;
```
